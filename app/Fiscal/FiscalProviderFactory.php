<?php

namespace App\Fiscal;

use App\Bootstrap\Config;

class FiscalProviderFactory
{
    public function make(array $fiscalConfig): FiscalProviderInterface
    {
        $provider = strtoupper(trim((string) ($fiscalConfig['provider'] ?? Config::get('FISCAL_PROVIDER_DEFAULT', 'MOCK'))));

        if ($provider === 'DGII') {
            $endpoint = (string) ($fiscalConfig['provider_url'] ?? Config::get('FISCAL_DGII_URL', ''));
            return new DgiiFiscalProvider($endpoint, (int) Config::get('FISCAL_DGII_TIMEOUT', 20));
        }

        return new MockFiscalProvider();
    }
}
