<?php

namespace App\Settings;

use App\Shared\Exceptions\HttpException;

class TenantSettingsValidator
{
    public function validateModules(array $modules): void
    {
        foreach ($modules as $module) {
            if (!is_string($module) || $module === '') {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Modules inválidos');
            }
        }
    }
}
