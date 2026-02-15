# Acta Release Wave 3 (W3-011 a W3-017)

**Fecha:** `2026-02-13`  
**Ambiente:** `local`  
**Responsable:** `Equipo POS SaaS`

## 1. Endpoints fiscales de consulta
- Prueba: `GET /api/v1/fiscal/documents/{id}`
- Prueba: `GET /api/v1/fiscal/documents/{id}/events`
- Prueba: `GET /api/v1/fiscal/documents/{id}/acks`
- Resultado: `OK`
- Evidencia:
```text
Punto validado por el equipo: consultas fiscales operativas.
```

## 2. Smoke actualizado
- Comando: `php tests/smoke/run.php --positive`
- Comando: `php tests/smoke/run.php --negative`
- Resultado: `OK`
- Evidencia:
```text
Puntos 1 y 2 confirmados en verde por el equipo.
```

## 3. Webhook fiscal (inválido)
- Prueba: `POST /api/v1/fiscal/webhook/ack` sin header `X-Fiscal-Webhook-Key`
- Resultado: `OK`
- Evidencia:
```text
Punto 4 validado por el equipo: control de seguridad funcionando.
```

## 4. Webhook fiscal (válido)
- Prueba: `POST /api/v1/fiscal/webhook/ack` con header `X-Fiscal-Webhook-Key` correcto
- Resultado: `PENDIENTE DE DATOS`
- Nota:
```text
No existen registros en fiscal_documents en el ambiente de validacion,
por lo que no hay fiscal_document_id para procesar el webhook valido.
No es bloqueo de codigo: requiere generar/emitir una factura fiscal para crear evidencia.
```

## 5. Estado por ticket
- `W3-011` Documento fiscal por id: `COMPLETADO`
- `W3-012` Eventos de documento fiscal: `COMPLETADO`
- `W3-013` Acuses de documento fiscal: `COMPLETADO`
- `W3-014` Webhook de acuse fiscal: `COMPLETADO` (validación positiva pendiente de datos)
- `W3-015` Auditoría fiscal crítica: `COMPLETADO`
- `W3-016` Rate limit endpoints fiscales: `COMPLETADO`
- `W3-017` Docs + smoke fiscal: `COMPLETADO`

## Checklist final
- [x] Endpoints fiscales agregados
- [x] Seguridad webhook inválido validada
- [x] Auditoría fiscal implementada
- [x] Rate limit fiscal implementado
- [x] Docs/requests/smoke actualizados
- [ ] Webhook válido con `fiscal_document_id` real (pendiente de datos)

## Dictamen
- **Estado:** `APROBADO CON NOTA`
- **Nota operativa:**
```text
Se aprueba W3-011..W3-017.
Queda una evidencia pendiente de ejecucion en ambiente con datos fiscales:
webhook valido contra fiscal_document_id existente.
```
