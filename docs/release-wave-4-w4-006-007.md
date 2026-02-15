# Acta Técnica Parcial - Wave 4 (W4-006 y W4-007)

## W4-006 Validaciones fiscales RD más completas

Implementado:
- Validación de emisión fiscal por tipo NCF:
  - permitidos: `B01`, `B02`, `B14`, `B15`
- Para `B01/B14` exige documento de cliente.
- Validación documento cliente:
  - Cédula (11 dígitos + checksum)
  - RNC (9 dígitos + checksum)
- Límites de emisión por tenant:
  - `fiscal.emission_limits.daily_max`
  - `fiscal.emission_limits.monthly_max`
- Error de límite: `409 FISCAL_LIMIT_EXCEEDED`.

Archivos:
- `app/Fiscal/FiscalService.php`
- `app/Fiscal/FiscalRepository.php`
- `app/Invoices/InvoiceController.php`
- `app/Settings/TenantSettingsService.php`
- `app/Settings/TenantSettingsValidator.php`
- `app/Settings/TenantSettingsRepository.php`
- `bin/console.php`

## W4-007 Notificaciones de errores fiscales

Implementado:
- Servicio de alertas operativas fiscal.
- Disparo de alertas cuando documento fiscal queda en:
  - `FAILED`
  - `REJECTED`
- Canales:
  - Email interno (`FISCAL_ALERT_EMAILS`)
  - Webhook interno opcional (`FISCAL_ALERT_WEBHOOK_URL`)
- Integrado en:
  - worker fiscal (`FiscalJobService`)
  - webhook ack fiscal (`FiscalController`)

Archivos:
- `app/Fiscal/FiscalAlertService.php`
- `app/Fiscal/FiscalJobService.php`
- `app/Fiscal/FiscalController.php`
- `app/Bootstrap/App.php`
- `bin/worker.php`
- `.env.example`
- `README.md`

## Validación sugerida

1) Smoke base
```bash
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
```

2) Validación límites fiscales
- Configurar `fiscal.emission_limits.daily_max=1`
- Emitir más de una factura fiscal en el mismo día.
- Esperado: `409 FISCAL_LIMIT_EXCEEDED` en segunda emisión.

3) Validación documento cliente
- Intentar emisión fiscal con cliente/doc inválido.
- Esperado: `422 VALIDATION_ERROR`.

4) Validación alertas
- Forzar `REJECTED` o `FAILED` y verificar:
```sql
SELECT id, tenant_id, email_to, subject, status, created_at
FROM email_outbox
WHERE subject LIKE '%[Fiscal]%'
ORDER BY id DESC
LIMIT 20;
```

## Nota
- Fiscal sigue opcional; tenants no fiscales continúan operando en flujo no fiscal.
