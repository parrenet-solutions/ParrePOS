<?php

namespace App\Invoices;

use App\Core\Validation\Validator;
use App\Shared\Exceptions\HttpException;

class InvoiceService
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function validateCreate(array $payload): array
    {
        $this->validator->required($payload, ['customer_id', 'items']);

        if (!is_array($payload['items']) || count($payload['items']) === 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Items requeridos');
        }

        $items = [];
        foreach ($payload['items'] as $item) {
            if (!is_array($item)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Item inválido');
            }

            $this->validator->required($item, ['name', 'qty', 'unit_price', 'tax_rate']);
            $qty = (float) $item['qty'];
            if ($qty <= 0) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'qty inválido');
            }

            $unitPrice = (float) $item['unit_price'];
            $discount = isset($item['discount']) ? (float) $item['discount'] : 0.0;
            $taxRate = (float) $item['tax_rate'];
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

        $customerId = (int) $payload['customer_id'];
        if ($customerId <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'customer_id inválido');
        }

        return [
            'customer_id' => $customerId,
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

    public function formatInvoiceNumber(string $series, int $sequence): string
    {
        return $series . str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
    }
}
