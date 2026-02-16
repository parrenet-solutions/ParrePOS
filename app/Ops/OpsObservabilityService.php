<?php

namespace App\Ops;

use App\Shared\Exceptions\HttpException;

class OpsObservabilityService
{
    public function normalizeRulesPayload(array $payload): array
    {
        $entries = $payload['entries'] ?? null;
        if (!is_array($entries) || $entries === []) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'entries requerido');
        }

        $normalized = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'entrada de regla inválida');
            }

            $code = strtolower(trim((string) ($entry['code'] ?? '')));
            $metricKey = strtolower(trim((string) ($entry['metric_key'] ?? '')));
            $comparator = strtoupper(trim((string) ($entry['comparator'] ?? 'GT')));
            $threshold = (float) ($entry['threshold_value'] ?? 0);
            $severity = strtoupper(trim((string) ($entry['severity'] ?? 'WARN')));
            $channel = strtoupper(trim((string) ($entry['channel'] ?? 'INTERNAL')));
            $target = trim((string) ($entry['target'] ?? ''));
            $enabled = ($entry['enabled'] ?? true) === true;

            if ($code === '' || !preg_match('/^[a-z0-9._-]{3,80}$/', $code)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'code inválido');
            }

            if ($metricKey === '' || !preg_match('/^[a-z0-9._-]{3,80}$/', $metricKey)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'metric_key inválido');
            }

            if (!in_array($comparator, ['GT', 'GTE', 'LT', 'LTE'], true)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'comparator inválido');
            }

            if (!in_array($severity, ['INFO', 'WARN', 'CRITICAL'], true)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'severity inválido');
            }

            if (!in_array($channel, ['INTERNAL', 'EMAIL', 'WEBHOOK'], true)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'channel inválido');
            }

            $normalized[] = [
                'code' => $code,
                'metric_key' => $metricKey,
                'comparator' => $comparator,
                'threshold_value' => $threshold,
                'severity' => $severity,
                'channel' => $channel,
                'target' => $target !== '' ? $target : null,
                'enabled' => $enabled,
            ];
        }

        return $normalized;
    }

    public function compare(float $value, float $threshold, string $comparator): bool
    {
        return match ($comparator) {
            'GT' => $value > $threshold,
            'GTE' => $value >= $threshold,
            'LT' => $value < $threshold,
            'LTE' => $value <= $threshold,
            default => false,
        };
    }
}
