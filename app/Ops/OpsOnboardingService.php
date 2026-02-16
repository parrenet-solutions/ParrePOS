<?php

namespace App\Ops;

use App\Shared\Exceptions\HttpException;

class OpsOnboardingService
{
    public function templates(): array
    {
        return [
            [
                'code' => 'retail_basic',
                'name' => 'Retail básico',
                'steps' => [
                    'Configurar tenant (datos fiscales opcionales).',
                    'Crear sucursal principal y caja.',
                    'Configurar catálogo inicial de ítems.',
                    'Probar venta y cierre diario.',
                    'Validar sync y respaldo operativo.',
                ],
            ],
            [
                'code' => 'restaurant_quick',
                'name' => 'Restaurante rápido',
                'steps' => [
                    'Definir áreas/cajas de atención.',
                    'Configurar menú y precios.',
                    'Validar flujo POS con pagos mixtos.',
                    'Probar impresión y hardware bridge (opcional).',
                    'Checklist de salida y soporte inicial.',
                ],
            ],
        ];
    }

    public function defaultChecklist(string $templateCode): array
    {
        foreach ($this->templates() as $template) {
            if ((string) ($template['code'] ?? '') === $templateCode) {
                $items = [];
                foreach ((array) ($template['steps'] ?? []) as $step) {
                    $items[] = [
                        'step' => (string) $step,
                        'done' => false,
                        'completed_at' => null,
                    ];
                }

                return $items;
            }
        }

        throw new HttpException(422, 'VALIDATION_ERROR', 'template_code inválido');
    }

    public function normalizeChecklistPayload(array $payload, array $currentChecklist): array
    {
        $items = $payload['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'items requerido');
        }

        $normalized = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'item inválido');
            }

            $step = trim((string) ($item['step'] ?? ''));
            $done = ($item['done'] ?? false) === true;
            if ($step === '') {
                if (isset($currentChecklist[$index]['step'])) {
                    $step = (string) $currentChecklist[$index]['step'];
                } else {
                    throw new HttpException(422, 'VALIDATION_ERROR', 'step requerido');
                }
            }

            $normalized[] = [
                'step' => $step,
                'done' => $done,
                'completed_at' => $done ? gmdate('c') : null,
            ];
        }

        return $normalized;
    }

    public function progress(array $items): float
    {
        if ($items === []) {
            return 0.0;
        }

        $done = 0;
        foreach ($items as $item) {
            if (is_array($item) && (($item['done'] ?? false) === true)) {
                $done++;
            }
        }

        return round(($done / count($items)) * 100, 2);
    }
}
