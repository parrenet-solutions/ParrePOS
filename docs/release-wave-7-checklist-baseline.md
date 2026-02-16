# Checklist Release/Tag - Wave 7 Baseline

Fecha sugerida de corte: `2026-02-16`

## 1) Precondiciones técnicas
- [ ] Árbol de trabajo limpio (`git status` sin cambios pendientes).
- [x] Migraciones en `Pendientes: 0` (`php bin/console.php migrate:status`).
- [ ] Seed dev ejecutado (`php bin/console.php seed:dev`).
- [x] Smoke `--all` en verde (`php tests/smoke/run.php --all`).
- [x] Worker ejecutado sin errores críticos (`php bin/worker.php run`).

## 2) Evidencia documental mínima
- [x] Actas Wave 7 por bloque:
  - `docs/release-wave-7-acta-w7-001-002.md`
  - `docs/release-wave-7-acta-w7-003-004.md`
  - `docs/release-wave-7-acta-w7-005-006.md`
  - `docs/release-wave-7-acta-w7-007-008.md`
  - `docs/release-wave-7-acta-w7-009-010.md`
- [x] Acta final Wave 7 completada (`docs/release-wave-7-acta-final.md`).
- [x] Release notes Wave 7 completadas (`docs/release-notes-wave-7.md`).
- [x] Estado del proyecto actualizado (`docs/project-status.md`).

## 3) Tag de baseline
Estado esperado:
- [ ] Tag `wave-7-baseline` creado localmente.

Comandos sugeridos:
```bash
git checkout wave-1
git pull origin wave-1
git tag -a wave-7-baseline -m "Wave 7 baseline (W7-001..W7-012)"
git push origin refs/heads/wave-1:refs/heads/wave-1
git push origin refs/tags/wave-7-baseline:refs/tags/wave-7-baseline
```

## 4) Verificación remota
- [ ] Rama `wave-1` visible en remoto con commit de cierre Wave 7.
- [ ] Tag `wave-7-baseline` visible en remoto.
- [ ] Acta final + release notes de Wave 7 publicadas.

## 5) Criterio de salida
- [ ] Baseline congelada.
- [ ] Evidencias completas.
- [ ] Autorización para arrancar Wave 8.
