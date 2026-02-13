<?php

declare(strict_types=1);

use App\Bootstrap\Config;
use App\Bootstrap\Env;
use App\Core\Validation\Validator;
use App\Documents\DocumentRepository;
use App\Documents\DocumentService;
use App\Documents\PdfService;
use App\Email\EmailRepository;
use App\Email\NullEmailSender;
use App\Invoices\InvoiceRepository;
use App\Invoices\InvoiceService;
use App\Jobs\JobQueue;
use App\Recurring\RecurringRepository;
use App\Shared\Helpers;

require_once dirname(__DIR__) . '/app/Bootstrap/Env.php';
require_once dirname(__DIR__) . '/app/Bootstrap/Config.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

Env::load(dirname(__DIR__) . '/.env');

$command = $argv[1] ?? null;
if ($command !== 'run') {
    fwrite(STDOUT, "Uso: php bin/worker.php run\n");
    exit(0);
}

$db = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        Config::get('DB_HOST', '127.0.0.1'),
        Config::get('DB_PORT', '3306'),
        Config::get('DB_NAME', 'parrepos')
    ),
    Config::get('DB_USER', 'root'),
    Config::get('DB_PASS', ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$redis = null;
$redisHost = Config::get('REDIS_HOST');
if ($redisHost && class_exists(Redis::class)) {
    $redis = new Redis();
    $redis->connect($redisHost, (int) Config::get('REDIS_PORT', 6379));
    $password = Config::get('REDIS_PASS');
    if ($password) {
        $redis->auth($password);
    }
}

$queue = new JobQueue($db, $redis);
$documentRepository = new DocumentRepository($db);
$invoiceRepository = new InvoiceRepository($db);
$documentService = new DocumentService($documentRepository, $invoiceRepository, new PdfService());
$emailRepository = new EmailRepository($db);
$emailSender = new NullEmailSender();
$invoiceService = new InvoiceService(new Validator());
$recurringRepository = new RecurringRepository($db);

enqueueLegacyPendingJobs($queue, $documentRepository, $emailRepository, $recurringRepository);
processQueue($queue, $documentService, $emailRepository, $emailSender, $recurringRepository, $invoiceRepository, $invoiceService, $db);

fwrite(STDOUT, "Worker finalizado.\n");

function enqueueLegacyPendingJobs(
    JobQueue $queue,
    DocumentRepository $documentRepository,
    EmailRepository $emailRepository,
    RecurringRepository $recurringRepository
): void {
    foreach ($documentRepository->listPending() as $doc) {
        $docId = (int) ($doc['id'] ?? 0);
        if ($docId > 0) {
            $queue->push('jobs:pdf', ['document_id' => $docId], 'pdf:document:' . $docId, 5);
        }
    }

    foreach ($emailRepository->listPending() as $email) {
        $emailId = (int) ($email['id'] ?? 0);
        if ($emailId > 0) {
            $queue->push('jobs:email', ['email_id' => $emailId], 'email:' . $emailId, 5);
        }
    }

    foreach ($recurringRepository->listDue() as $rule) {
        $ruleId = (int) ($rule['id'] ?? 0);
        if ($ruleId <= 0) {
            continue;
        }

        $schedule = (string) ($rule['next_run_at'] ?? 'na');
        $queue->push('jobs:recurring', ['rule_id' => $ruleId], 'recurring:' . $ruleId . ':' . $schedule, 5);
    }
}

function processQueue(
    JobQueue $queue,
    DocumentService $documentService,
    EmailRepository $emailRepository,
    NullEmailSender $emailSender,
    RecurringRepository $recurringRepository,
    InvoiceRepository $invoiceRepository,
    InvoiceService $invoiceService,
    PDO $db
): void {
    $loops = 0;
    while ($loops < 500) {
        $processed = false;

        $processed = consumeJob($queue, 'jobs:pdf', function (array $payload) use ($documentService): void {
            if (!isset($payload['document_id'])) {
                throw new RuntimeException('document_id requerido');
            }
            $documentService->generateInvoicePdf((int) $payload['document_id']);
        }) || $processed;

        $processed = consumeJob($queue, 'jobs:email', function (array $payload) use ($emailRepository, $emailSender): void {
            if (!isset($payload['email_id'])) {
                throw new RuntimeException('email_id requerido');
            }
            processEmail($emailRepository, $emailSender, (int) $payload['email_id']);
        }) || $processed;

        $processed = consumeJob($queue, 'jobs:recurring', function (array $payload) use ($recurringRepository, $invoiceRepository, $invoiceService, $db): void {
            if (!isset($payload['rule_id'])) {
                throw new RuntimeException('rule_id requerido');
            }
            processRecurringRule($recurringRepository, $invoiceRepository, $invoiceService, $db, (int) $payload['rule_id']);
        }) || $processed;

        if (!$processed) {
            break;
        }

        $loops++;
    }
}

function consumeJob(JobQueue $queue, string $queueName, callable $handler): bool
{
    $job = $queue->popJob($queueName);
    if ($job === null) {
        return false;
    }

    $jobId = (int) ($job['id'] ?? 0);
    $attempt = (int) ($job['attempt'] ?? 0);

    try {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $handler($payload);
        $queue->complete($jobId);
        fwrite(STDOUT, '[OK] ' . $queueName . ' job_id=' . $jobId . ' intento=' . $attempt . "\n");
    } catch (Throwable $e) {
        $queue->fail($jobId, $e->getMessage());
        fwrite(STDOUT, '[FAIL] ' . $queueName . ' job_id=' . $jobId . ' error=' . $e->getMessage() . "\n");
    }

    return true;
}

function processEmail(EmailRepository $emailRepository, NullEmailSender $sender, int $emailId): void
{
    $email = $emailRepository->findById($emailId);
    if (!$email || $email['status'] !== 'PENDING') {
        return;
    }

    if ($sender->send($email['email_to'], $email['subject'], $email['body'])) {
        $emailRepository->markSent($emailId);
    } else {
        throw new RuntimeException('Fallo envio email');
    }
}

function processRecurringRule(
    RecurringRepository $recurringRepository,
    InvoiceRepository $invoiceRepository,
    InvoiceService $invoiceService,
    PDO $db,
    int $ruleId
): void {
    $rule = $recurringRepository->findAnyById($ruleId);
    if (!$rule) {
        return;
    }

    $currentNext = $rule['next_run_at'];
    $nextRun = date('Y-m-d H:i:s', strtotime($currentNext . ' +' . $rule['interval_days'] . ' days'));

    if (!$recurringRepository->claimRun($ruleId, $currentNext, $nextRun)) {
        return;
    }

    $items = json_decode($rule['items_json'], true);
    if (!is_array($items)) {
        throw new RuntimeException('items_json invalido');
    }

    $payload = ['customer_id' => (int) $rule['customer_id'], 'items' => $items];
    $validated = $invoiceService->validateCreate($payload);
    $totals = $invoiceService->totals($validated['items']);

    Helpers::transaction($db, function () use ($invoiceRepository, $rule, $totals, $validated): void {
        $invoiceId = $invoiceRepository->createDraft((int) $rule['tenant_id'], [
            'customer_id' => (int) $rule['customer_id'],
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
        ]);
        $invoiceRepository->addItems((int) $rule['tenant_id'], $invoiceId, $validated['items']);
    });
}
