# TODO — Monitoring ETL intégré par page

## ✅ Complété

### Backend
- [x] 1. Migration `create_ra_t_etl_jobs_table`
- [x] 2. Modèle `EtlJob`
- [x] 3. Service `EtlMonitorService`
- [x] 4. Controller `EtlMonitorController` (routes by-types, stats, index)
- [x] 5. Routes API dans `api.php` (`/etl/jobs/by-types`, `/etl/stats`, `/etl/jobs`)
- [x] 6. Intégrer tracking dans `EtlAggFromRaw` (start/finish avec rows_processed)
- [x] 7. Intégrer tracking dans `EtlCdrFromTmp` (start/finish avec processed/inserted/skipped)
- [x] 8. Intégrer tracking dans `ProcessImportJob` (start/update/finish avec progression %)

### Frontend
- [x] 9. Composant `JobStatusBar.jsx` — 3 modes : compact, full, timeline
- [x] 10. Intégrer dans `Import.jsx` — mode COMPLET (avant historique)
- [x] 11. Intégrer dans `CdrOcc.jsx` — mode COMPACT (barre fixe en bas)
- [x] 12. Intégrer dans `CdrMmg.jsx` — mode COMPACT (barre fixe en bas)
- [x] 13. Intégrer dans `Predictions.jsx` — mode TIMELINE (4 étapes)
- [x] 14. Intégrer dans `Alerts.jsx` — mode COMPACT (sous header)
- [x] 15. Intégrer dans `MsisdnSearch.jsx` — mode TIMELINE (4 étapes)
- [x] 16. Intégrer dans `Dashboard.jsx` — mode COMPACT (footer)

## Fichiers créés / modifiés

| Fichier | Action |
|---------|--------|
| `smsplus-api/database/migrations/2026_04_26_000000_create_ra_t_etl_jobs_table.php` | Créé |
| `smsplus-api/app/Models/EtlJob.php` | Créé |
| `smsplus-api/app/Services/EtlMonitorService.php` | Créé |
| `smsplus-api/app/Http/Controllers/Api/EtlMonitorController.php` | Créé |
| `smsplus-api/routes/api.php` | Modifié (ajout routes ETL) |
| `smsplus-api/app/Console/Commands/EtlAggFromRaw.php` | Modifié (tracking) |
| `smsplus-api/app/Console/Commands/EtlCdrFromTmp.php` | Modifié (tracking) |
| `smsplus-api/app/Jobs/ProcessImportJob.php` | Modifié (tracking + progression) |
| `smsplus-front/src/components/JobStatusBar.jsx` | Créé |
| `smsplus-front/src/pages/Import.jsx` | Modifié (JobStatusBar full) |
| `smsplus-front/src/pages/Dashboard.jsx` | Modifié (JobStatusBar compact) |
| `smsplus-front/src/pages/Predictions.jsx` | Modifié (JobStatusBar timeline) |
| `smsplus-front/src/pages/Alerts.jsx` | Modifié (JobStatusBar compact) |
| `smsplus-front/src/pages/MsisdnSearch.jsx` | Modifié (JobStatusBar timeline) |
| `smsplus-front/src/pages/CdrOcc.jsx` | Modifié (JobStatusBar compact fixed) |
| `smsplus-front/src/pages/CdrMmg.jsx` | Modifié (JobStatusBar compact fixed) |

## Prochaines étapes (optionnel)
- [ ] Lancer les migrations : `php artisan migrate`
- [ ] Tester les commandes ETL : `php artisan etl:cdr-from-tmp --dry-run`
- [ ] Tester l'endpoint API : `GET /api/etl/jobs/by-types?types[]=import_occ_csv`
