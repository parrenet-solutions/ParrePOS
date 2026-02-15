<?php

namespace App\Fiscal;

interface FiscalProviderInterface
{
    public function submit(int $tenantId, int $fiscalDocumentId, array $payload, array $context): array;
}
