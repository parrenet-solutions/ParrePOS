# Checklist Release/Tag - Wave 5 Baseline

Fecha sugerida de corte: `2026-02-15`

## 1) Precondiciones técnicas
- [ ] Árbol de trabajo limpio (`git status` sin cambios pendientes).
- [ ] Migraciones en `Pendientes: 0` (`php bin/console.php migrate:status`).
- [ ] Seed dev ejecutado (`php bin/console.php seed:dev`).
- [ ] Smoke `--all` en verde (`php tests/smoke/run.php --all`).

## 2) Evidencia documental mínima
- [ ] Actas parciales Wave 5 disponibles:
  - `docs/release-wave-5-acta-w5-001-004.md`
  - `docs/release-wave-5-acta-w5-005-006.md`
  - `docs/release-wave-5-acta-w5-007-008.md`
- [ ] Estado del proyecto actualizado (`docs/project-status.md`).
- [ ] Backlog Wave 5 actualizado (`docs/release-wave-5-backlog.md`).

## 3) Tag de baseline
Comandos sugeridos:
```bash
git checkout wave-1
git pull origin wave-1
git tag -a wave-5-baseline -m "Wave 5 baseline (W5-001..W5-010)"
git push origin refs/heads/wave-1:refs/heads/wave-1
git push origin refs/tags/wave-5-baseline:refs/tags/wave-5-baseline
```

## 4) Verificación remota
- [ ] Rama `wave-1` visible en remoto con el commit de cierre.
- [ ] Tag `wave-5-baseline` visible en remoto.
- [ ] Release notes/acta final de Wave 5 publicada (al cerrar W5-010).

## 5) Criterio de salida
- [ ] Baseline congelada.
- [ ] Evidencias completas.
- [ ] Autorización para arrancar Wave 6.
