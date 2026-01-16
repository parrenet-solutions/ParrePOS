<?php

namespace App\Documents;

class PdfService
{
    public function generateInvoicePdf(int $invoiceId, array $invoice, array $items): string
    {
        $lines = [
            'ParrePos - Factura',
            'Factura ID: ' . $invoiceId,
            'Cliente ID: ' . ($invoice['customer_id'] ?? ''),
            'Total: ' . ($invoice['total'] ?? ''),
            '---',
        ];

        foreach ($items as $item) {
            $lines[] = $item['name'] . ' x' . $item['qty'] . ' = ' . $item['line_total'];
        }

        $content = implode("\n", $lines);

        // PDF mínimo (texto plano).
        $stream = "BT /F1 12 Tf 50 750 Td (" . $this->escape($content) . ") Tj ET";

        $objects = [
            1 => "<</Type/Catalog/Pages 2 0 R>>",
            2 => "<</Type/Pages/Count 1/Kids[3 0 R]>>",
            3 => "<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>",
            4 => "<</Length " . strlen($stream) . ">>stream\n" . $stream . "\nendstream",
            5 => "<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<</Size 6/Root 1 0 R>>\nstartxref\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\n"], ['\\\\', '\\(', '\\)', '\\n'], $value);
    }
}
