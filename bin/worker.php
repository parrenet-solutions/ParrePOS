<?php

declare(strict_types=1);

use App\Bootstrap\Config;
use App\Bootstrap\Env;
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
//use PDO;
//use Redis;

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

$queue = new JobQueue($redis);
$documentRepository = new DocumentRepository($db);
$invoiceRepository = new InvoiceRepository($db);
$documentService = new DocumentService($documentRepository, $invoiceRepository, new PdfService());
$emailRepository = new EmailRepository($db);
$emailSender = new NullEmailSender();
$invoiceService = new InvoiceService(new \App\Core\Validation\Validator());
$recurringRepository = new RecurringRepository($db);

if ($queue->isAvailable()) {
    processQueue($queue, $documentService, $emailRepository, $emailSender, $recurringRepository, $invoiceRepository, $invoiceService, $db);
} else {
    processPending($documentRepository, $documentService, $emailRepository, $emailSender, $recurringRepository, $invoiceRepository, $invoiceService, $db);
}

fwrite(STDOUT, "Worker finalizado.\n");

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
    foreach ($recurringRepository->listDue() as $rule) {
        $queue->push('jobs:recurring', ['rule_id' => (int) $rule['id']]);
    }

    $loops = 0;
    while ($loops < 200) {
        $processed = false;

        $job = $queue->pop('jobs:pdf');
        if ($job && isset($job['document_id'])) {
            $documentService->generateInvoicePdf((int) $job['document_id']);
            $processed = true;
        }

        $job = $queue->pop('jobs:email');
        if ($job && isset($job['email_id'])) {
            processEmail($emailRepository, $emailSender, (int) $job['email_id']);
            $processed = true;
        }

        $job = $queue->pop('jobs:recurring');
        if ($job && isset($job['rule_id'])) {
            processRecurringRule($recurringRepository, $invoiceRepository, $invoiceService, $db, (int) $job['rule_id']);
            $processed = true;
        }

        if (!$processed) {
            break;
        }
        $loops++;
    }
}

function processPending(
    DocumentRepository $documentRepository,
    DocumentService $documentService,
    EmailRepository $emailRepository,
    NullEmailSender $emailSender,
    RecurringRepository $recurringRepository,
    InvoiceRepository $invoiceRepository,
    InvoiceService $invoiceService,
    PDO $db
): void {
    foreach ($documentRepository->listPending() as $doc) {
        $documentService->generateInvoicePdf((int) $doc['id']);
    }

    foreach ($emailRepository->listPending() as $email) {
        processEmail($emailRepository, $emailSender, (int) $email['id']);
    }

    foreach ($recurringRepository->listDue() as $rule) {
        processRecurringRule($recurringRepository, $invoiceRepository, $invoiceService, $db, (int) $rule['id']);
    }
}

function processEmail(EmailRepository $emailRepository, NullEmailSender $sender, int $emailId): void
{
    $email = $emailRepository->findById($emailId);
    if (!$email || $email['status'] !== 'PENDING') {
        return;
    }

    if ($sender->send($email['email_to'], $email['subject'], $email['body'])) {
        $emailRepository->markSent($emailId);
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
        return;
    }

    $payload = ['customer_id' => (int) $rule['customer_id'], 'items' => $items];
    $validated = $invoiceService->validateCreate($payload);
    $totals = $invoiceService->totals($validated['items']);

    Helpers::transaction($db, function () use ($invoiceRepository, $rule, $totals, $validated) {
        $invoiceId = $invoiceRepository->createDraft((int) $rule['tenant_id'], [
            'customer_id' => (int) $rule['customer_id'],
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
        ]);
        $invoiceRepository->addItems((int) $rule['tenant_id'], $invoiceId, $validated['items']);
    });
}
