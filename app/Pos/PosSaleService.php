<?php

namespace App\Pos;

use App\Shared\Exceptions\HttpException;

class PosSaleService
{
    public function validateCreate(array $payload): array
    {
        if (!isset($payload['items']) || !is_array($payload['items']) || count($payload['items']) === 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Items requeridos');
        }

        $branchId = (int) ($payload['branch_id'] ?? 0);
        $registerId = (int) ($payload['register_id'] ?? 0);
        $cashSessionId = (int) ($payload['cash_session_id'] ?? 0);

        if ($branchId <= 0 || $registerId <= 0 || $cashSessionId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'branch_id/register_id/cash_session_id inválidos');
        }

        $items = [];
        foreach ($payload['items'] as $item) {
            if (!is_array($item)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Item inválido');
            }

            foreach (['name', 'qty', 'unit_price', 'tax_rate'] as $field) {
                if (!isset($item[$field])) {
                    throw new HttpException(422, 'VALIDATION_ERROR', 'Campo requerido: ' . $field);
                }
            }

            $qty = (float) $item['qty'];
            $unitPrice = (float) $item['unit_price'];
            $discount = isset($item['discount']) ? (float) $item['discount'] : 0.0;
            $taxRate = (float) $item['tax_rate'];

            if ($qty <= 0 || $unitPrice < 0) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'qty/unit_price inválidos');
            }

            if (!in_array($taxRate, [0.0, 0.16, 0.18], true)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'tax_rate inválido');
            }

            $lineSubtotal = ($qty * $unitPrice) - $discount;
            $taxAmount = $lineSubtotal * $taxRate;

            $items[] = [
                'name' => trim((string) $item['name']),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'tax_rate' => $taxRate,
                'line_total' => $lineSubtotal,
                'tax_amount' => $taxAmount,
            ];
        }

        return [
            'branch_id' => $branchId,
            'register_id' => $registerId,
            'cash_session_id' => $cashSessionId,
            'items' => $items,
        ];
    }

    public function totals(array $items): array
    {
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $subtotal += $item['line_total'];
            $taxTotal += $item['tax_amount'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal + $taxTotal, 2),
        ];
    }

    public function formatTicket(int $branchId, int $registerId, int $sequence): string
    {
        return 'T' . $branchId . '-' . $registerId . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function normalizePayments(array $payload, float $total): array
    {
        $payments = $payload['payments'] ?? null;

        if (is_array($payments) && count($payments) > 0) {
            $items = [];
            $sum = 0.0;
            foreach ($payments as $payment) {
                if (!is_array($payment) || !isset($payment['method']) || !isset($payment['amount'])) {
                    throw new HttpException(422, 'VALIDATION_ERROR', 'payments inválidos');
                }
                $method = strtoupper(trim((string) $payment['method']));
                $amount = (float) $payment['amount'];
                if ($method === '' || $amount <= 0) {
                    throw new HttpException(422, 'VALIDATION_ERROR', 'payments inválidos');
                }
                $reference = isset($payment['reference']) ? trim((string) $payment['reference']) : null;
                $items[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $reference,
                ];
                $sum += $amount;
            }

            if ($sum < $total) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'paid_total insuficiente');
            }

            return ['paid_total' => $sum, 'items' => $items];
        }

        $paidTotal = isset($payload['paid_total']) ? (float) $payload['paid_total'] : $total;
        if ($paidTotal < $total) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'paid_total insuficiente');
        }

        return [
            'paid_total' => $paidTotal,
            'items' => [[
                'method' => 'CASH',
                'amount' => $paidTotal,
                'reference' => null,
            ]],
        ];
    }
}
