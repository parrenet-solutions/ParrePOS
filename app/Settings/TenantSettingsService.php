<?php

namespace App\Settings;

class TenantSettingsService
{
    public function normalizeModules(array $modules): array
    {
        return array_values(array_unique($modules));
    }
}
