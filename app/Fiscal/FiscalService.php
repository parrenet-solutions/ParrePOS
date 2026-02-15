<?php

namespace App\Fiscal;

use App\Settings\TenantSettingsService;
use App\Settings\TenantSettingsValidator;
use App\Shared\Exceptions\HttpException;

class FiscalService
{
    private TenantSettingsService $settingsService;
    private TenantSettingsValidator $settingsValidator;

    public function __construct(
        TenantSettingsService $settingsService,
        TenantSettingsValidator $settingsValidator
    ) {
        $this->settingsService = $settingsService;
        $this->settingsValidator = $settingsValidator;
    }

    public function validateAndNormalizeConfig(array $payload): array
    {
        $fiscal = $payload['fiscal'] ?? $payload;
        if (!is_array($fiscal)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'fiscal inválido');
        }

        $normalized = $this->settingsService->normalizeFiscalConfig($fiscal);
        $this->settingsValidator->validateFiscalConfig($normalized);

        return $normalized;
    }

    public function validateAndNormalizeProfile(array $payload): array
    {
        $profile = $payload['profile'] ?? $payload;
        if (!is_array($profile)) {
            return [
                'legal_name' => '',
                'rnc' => '',
                'dgii_registered' => false,
                'environment' => 'CERT',
                'status' => 'ACTIVE',
            ];
        }

        $legalName = trim((string) ($profile['legal_name'] ?? ''));
        $rnc = preg_replace('/\D+/', '', (string) ($profile['rnc'] ?? '')) ?? '';
        $environment = strtoupper(trim((string) ($profile['environment'] ?? 'CERT')));
        $status = strtoupper(trim((string) ($profile['status'] ?? 'ACTIVE')));
        $dgiiRegistered = (bool) ($profile['dgii_registered'] ?? false);

        if ($legalName === '' && $rnc === '') {
            return [
                'legal_name' => '',
                'rnc' => '',
                'dgii_registered' => $dgiiRegistered,
                'environment' => 'CERT',
                'status' => 'ACTIVE',
            ];
        }

        if ($legalName === '') {
            throw new HttpException(422, 'VALIDATION_ERROR', 'profile.legal_name requerido');
        }

        if (!preg_match('/^[0-9]{9,11}$/', $rnc)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'profile.rnc inválido');
        }

        if (!in_array($environment, ['CERT', 'PROD'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'profile.environment inválido');
        }

        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'profile.status inválido');
        }

        return [
            'legal_name' => $legalName,
            'rnc' => $rnc,
            'dgii_registered' => $dgiiRegistered,
            'environment' => $environment,
            'status' => $status,
        ];
    }

    public function validateIssueConstraints(array $fiscal, array $customer, float $invoiceTotal, int $issuedToday, int $issuedMonth): void
    {
        $ncfType = strtoupper(trim((string) ($fiscal['ncf_type'] ?? '')));
        if (!in_array($ncfType, ['B01', 'B02', 'B14', 'B15'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Tipo NCF no permitido para emisión');
        }

        $customerType = strtoupper(trim((string) ($customer['type'] ?? '')));
        if (!in_array($customerType, ['PERSON', 'BUSINESS'], true)) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Tipo de cliente inválido para fiscal');
        }

        if ($invoiceTotal <= 0) {
            throw new HttpException(422, 'VALIDATION_ERROR', 'Total de factura inválido para fiscal');
        }

        $doc = preg_replace('/\D+/', '', (string) ($customer['doc_number'] ?? '')) ?? '';
        if ($ncfType === 'B01' || $ncfType === 'B14') {
            if ($doc === '') {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Cliente requiere RNC/Cédula para NCF fiscal');
            }
        }

        if ($doc !== '') {
            if (!preg_match('/^[0-9]{9}$|^[0-9]{11}$/', $doc)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Documento de cliente inválido para fiscal');
            }

            if (strlen($doc) === 11 && !$this->isValidCedula($doc)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'Cédula de cliente inválida');
            }

            if (strlen($doc) === 9 && !$this->isValidRnc($doc)) {
                throw new HttpException(422, 'VALIDATION_ERROR', 'RNC de cliente inválido');
            }
        }

        $limits = is_array($fiscal['emission_limits'] ?? null) ? $fiscal['emission_limits'] : [];
        $dailyMax = max(0, (int) ($limits['daily_max'] ?? 0));
        $monthlyMax = max(0, (int) ($limits['monthly_max'] ?? 0));

        if ($dailyMax > 0 && $issuedToday >= $dailyMax) {
            throw new HttpException(409, 'FISCAL_LIMIT_EXCEEDED', 'Límite diario fiscal alcanzado');
        }

        if ($monthlyMax > 0 && $issuedMonth >= $monthlyMax) {
            throw new HttpException(409, 'FISCAL_LIMIT_EXCEEDED', 'Límite mensual fiscal alcanzado');
        }
    }

    private function isValidCedula(string $cedula): bool
    {
        if (!preg_match('/^[0-9]{11}$/', $cedula)) {
            return false;
        }

        $sum = 0;
        $multipliers = [1, 2];
        for ($i = 0; $i < 10; $i++) {
            $digit = (int) $cedula[$i];
            $product = $digit * $multipliers[$i % 2];
            $sum += $product < 10 ? $product : (int) floor($product / 10) + ($product % 10);
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === (int) $cedula[10];
    }

    private function isValidRnc(string $rnc): bool
    {
        if (!preg_match('/^[0-9]{9}$/', $rnc)) {
            return false;
        }

        $weights = [7, 9, 8, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += ((int) $rnc[$i]) * $weights[$i];
        }

        $mod = $sum % 11;
        $check = 11 - $mod;
        if ($check === 11) {
            $check = 0;
        } elseif ($check === 10) {
            $check = 1;
        }

        return $check === (int) $rnc[8];
    }
}
