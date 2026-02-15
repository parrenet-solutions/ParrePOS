# Acta Técnica Parcial - Wave 4 (W4-001 y W4-002)

## Alcance ejecutado

### W4-001 Adapter fiscal desacoplado
- `FiscalProviderInterface`
- `MockFiscalProvider`
- `DgiiFiscalProvider`
- `FiscalProviderFactory`

Resultado:
- El envío fiscal del worker ya no está hardcodeado.
- Selección de provider por tenant config (`fiscal.provider`) con fallback env.
- Fiscal sigue opcional: si `fiscal.enabled=false`, no entra al pipeline fiscal.

### W4-002 Firma y trazabilidad de payload
- Firma HMAC SHA-256 por envío fiscal.
- Hash SHA-256 de request/response.
- Secret por tenant (`fiscal.signing_secret`) con fallback global (`FISCAL_SIGNING_SECRET`).
- Registro en eventos/acks:
  - `request_hash`
  - `response_hash`
  - `signature_prefix` (no se expone firma completa)

## Archivos clave
- `app/Fiscal/FiscalProviderInterface.php`
- `app/Fiscal/MockFiscalProvider.php`
- `app/Fiscal/DgiiFiscalProvider.php`
- `app/Fiscal/FiscalProviderFactory.php`
- `app/Fiscal/FiscalJobService.php`
- `app/Settings/TenantSettingsService.php`
- `app/Settings/TenantSettingsValidator.php`
- `app/Settings/TenantSettingsRepository.php`
- `bin/worker.php`
- `bin/console.php`
- `.env.example`

## Validación recomendada
1) Seed + smoke
```bash
php bin/console.php seed:dev
php tests/smoke/run.php --positive
php tests/smoke/run.php --negative
```

2) Probar provider MOCK (default)
- Config fiscal ON con `provider=MOCK`.
- Emitir factura fiscal.
- Ejecutar worker.
- Verificar `fiscal_documents` en `ACCEPTED`.

3) Verificar trazabilidad hash/firma
```sql
SELECT id, event_type, status, payload_json
FROM fiscal_events
WHERE event_type IN ('SUBMIT_REQUESTED', 'ACK_RECEIVED')
ORDER BY id DESC
LIMIT 10;
```

4) Probar provider DGII (si endpoint disponible)
- Config fiscal ON con `provider=DGII` + `provider_url`.
- Ejecutar emisión + worker.

## Nota
- En ausencia de endpoint DGII real, `MOCK` permite continuidad operativa sin bloquear tenants no fiscales.
