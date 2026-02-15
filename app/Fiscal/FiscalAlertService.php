<?php

namespace App\Fiscal;

use App\Bootstrap\Config;
use App\Email\EmailRepository;
use App\Jobs\JobQueue;

class FiscalAlertService
{
    private EmailRepository $emailRepository;
    private JobQueue $queue;

    public function __construct(EmailRepository $emailRepository, JobQueue $queue)
    {
        $this->emailRepository = $emailRepository;
        $this->queue = $queue;
    }

    public function notifyFailure(int $tenantId, int $fiscalDocumentId, string $status, string $message, array $context = []): void
    {
        if ($tenantId <= 0 || $fiscalDocumentId <= 0) {
            return;
        }

        $status = strtoupper(trim($status));
        if (!in_array($status, ['FAILED', 'REJECTED'], true)) {
            return;
        }

        $emails = $this->parseEmails((string) Config::get('FISCAL_ALERT_EMAILS', ''));
        if ($emails !== []) {
            $subject = '[Fiscal][' . $status . '] Documento #' . $fiscalDocumentId;
            $body = $this->buildBody($tenantId, $fiscalDocumentId, $status, $message, $context);

            foreach ($emails as $email) {
                $emailId = $this->emailRepository->create($tenantId, $email, $subject, $body);
                $this->queue->push(
                    'jobs:email',
                    ['email_id' => $emailId],
                    'email:fiscal-alert:' . $emailId,
                    3
                );
            }
        }

        $webhookUrl = trim((string) Config::get('FISCAL_ALERT_WEBHOOK_URL', ''));
        if ($webhookUrl !== '') {
            $this->sendWebhook($webhookUrl, [
                'tenant_id' => $tenantId,
                'fiscal_document_id' => $fiscalDocumentId,
                'status' => $status,
                'message' => $message,
                'context' => $context,
                'ts' => gmdate('c'),
            ]);
        }
    }

    private function parseEmails(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = explode(',', $raw);
        $emails = [];
        foreach ($parts as $part) {
            $email = trim($part);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }

    private function buildBody(int $tenantId, int $fiscalDocumentId, string $status, string $message, array $context): string
    {
        $lines = [
            'Alerta fiscal',
            'tenant_id=' . $tenantId,
            'fiscal_document_id=' . $fiscalDocumentId,
            'status=' . $status,
            'message=' . $message,
            'ts=' . gmdate('c'),
        ];

        if ($context !== []) {
            $lines[] = 'context=' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $lines);
    }

    private function sendWebhook(string $url, array $payload): void
    {
        if (!function_exists('curl_init')) {
            return;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HEADER => false,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
