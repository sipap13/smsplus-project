# Export Excel SMS+ VAS - Documentation des changements

## Fonctionnalité export Excel
Système complet d'export Excel pour les données CDR OCC, CDR MMG, Services et Alertes avec filtres appliqués.

---

## Backend (Laravel API)

### 1. Nouvelles routes API (`routes/api.php`)
```
GET /api/export/occ       - Export CDR OCC (filtrés par start_date, keyword, subscriber_type, partner)
GET /api/export/mmg       - Export CDR MMG (filtrés par start_date, event_status, subscriber_type)
GET /api/export/services  - Export des services
GET /api/export/alerts    - Export des alertes
```

**Authentification requise** : `middleware('auth.api')`
**Restrictions par rôle** :
- `/export/occ` : ADMIN, ANALYSTE_BUSS
- `/export/mmg` : ADMIN, ANALYSTE_OP
- `/export/services` : Tous authentifiés
- `/export/alerts` : ADMIN, ANALYSTE_OP

### 2. Contrôleur d'export (`app/Http/Controllers/Api/ExportController.php`)
- Méthode `exportOcc()` - Export CDR OCC
- Méthode `exportMmg()` - Export CDR MMG
- Méthode `exportServices()` - Export Services
- Méthode `exportAlerts()` - Export Alertes
- Ancien `revenusCsv()` conservé pour compatibilité

### 3. Classes Export (Concerns MaatWebsite/Excel)

#### `app/Exports/CdrOccExport.php`
Colonnes exportées :
- MSISDN Appelant, MSISDN Destinataire, Date, Heure
- Type Appel, Type Événement, Type Abonné, Itinérance
- Partenaire, Montant (DT), Keyword

Limite : 10 000 lignes max

#### `app/Exports/CdrMmgExport.php`
Colonnes exportées :
- NE, MSISDN Appelant, MSISDN Destinataire, Date, Heure
- Type Événement, Statut, Type Abonné, Type Service

Limite : 10 000 lignes max

#### `app/Exports/ServicesExport.php`
Colonnes exportées :
- Fournisseur, Nom Service, Numéro Court, Keyword
- Type, Prix (DT), Statut

#### `app/Exports/AlertsExport.php`
Colonnes exportées :
- Date, Service, Numéro Court, Keyword, Fournisseur
- Seuil %, Nb SMS, Motif, Statut

### 4. Caractéristiques
| Aspect | Détail |
|--------|--------|
| Format | .xlsx (Microsoft Excel) |
| Encodage | UTF-8 |
| Noms de fichiers | `CDR_OCC_YYYY-MM-DD.xlsx`, etc. |
| Filtres | Appliqués depuis la requête GET |
| Limite | 10 000 lignes/export (timeout prevention) |
| En-têtes | Français |

---

## Frontend (React)

### 1. Fonction utilitaire d'export (`src/api/excelDownload.js`)
```javascript
downloadExcel(endpoint, filters, defaultFilename, onStart, onError, onSuccess)
```
- Récupère le fichier en tant que blob
- Crée un lien de téléchargement temporaire
- Extrait le nom du fichier depuis `Content-Disposition` header
- Gère les erreurs avec callback

### 2. Modifications des pages

#### `Pages/CdrOcc.jsx`
✅ Import de `downloadExcel`
✅ États : `exportLoading`, `exportError`
✅ Fonction : `handleExport()`
✅ Bouton d'export : Zone d'affichage stats (haut du tableau)
✅ Message d'erreur d'export
✅ Applique les filtres actifs (start_date, keyword, subscriber_type, partner)

#### `Pages/CdrMmg.jsx`
✅ Import de `downloadExcel`
✅ États : `exportLoading`, `exportError`
✅ Fonction : `handleExport()`
✅ Bouton d'export : Zone d'affichage stats
✅ Message d'erreur d'export
✅ Applique les filtres actifs (start_date, event_status, subscriber_type)

#### `Pages/Services.jsx`
✅ Import de `downloadExcel`
✅ États : `exportLoading`, `exportError`
✅ Fonction : `handleExport()`
✅ Bouton d'export : Header avec bouton "Ajouter"
✅ Message d'erreur d'export

#### `Pages/Alerts.jsx`
✅ Import de `downloadExcel`
✅ États : `exportLoading`, `exportError`
✅ Fonction : `handleExport()`
✅ Bouton d'export : Header avec bouton "Ajouter"
✅ Message d'erreur d'export

### 3. Style du bouton d'export
```css
Background: #16a34a (vert)
Hover: #15803d (vert foncé)
Loading: #9ca3af (gris)
Color: white
Border-radius: 8px
Padding: 8px 16px
Font-size: 14px
Font-weight: 600
Icône: ⬇ (download) et 🔄 spinner (loading)
```

### 4. Animation du spinner (`src/index.css`)
```css
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
```

### 5. État du bouton d'export
| État | Afichage | Description |
|------|----------|-------------|
| Idle | ⬇ Exporter Excel | Normal, clic active l'export |
| Loading | 🔄 Export en cours... | Désactivé, spinner en rotation |
| Error | Message rouge | Affichage en banneau rouge sous le bouton |
| Disabled | Désactivé (70% opac) | Aucune donnée ou erreur réseau |

---

## Intégration avec le système existant

### Package utilisé
```
maatwebsite/excel ^3.1 (déjà installé)
```

### Base de données
Tables utilisées :
- `ra_t_occ_cdr_detail` (OCC CDR detail)
- `ra_t_mmg_cdr_det` (MMG CDR detail)
- `ra_t_services` (Services)
- `ra_t_alerts` (Alertes)

### Authentification
Utilise le middleware `auth.api` existant et les rôles (`role:ADMIN,ANALYSTE_OP`, etc.)

### Erreur handling
- Erreurs API affichées dans banneau rouge
- Timeout sur 10 000 lignes pour éviter les erreurs 500
- Messages d'erreur traduits en français

---

## Tests manuels recommandés

### 1. CDR OCC
```bash
# Sans filtre
GET http://localhost:8001/api/export/occ

# Avec filtres
GET http://localhost:8001/api/export/occ?start_date=2026-04-21&keyword=SPORT

# Dans le frontend
1. Aller sur CDR OCC
2. Appliquer un filtre (ex: date, keyword)
3. Cliquer "⬇ Exporter Excel"
4. Le fichier CDR_OCC_2026-04-21.xlsx se télécharge
```

### 2. CDR MMG
```bash
# Même procédure que OCC avec filtres MMG
GET http://localhost:8001/api/export/mmg?event_status=Success
```

### 3. Services
```bash
# Export simple sans filtres
GET http://localhost:8001/api/export/services
```

### 4. Alertes
```bash
# Export simple sans filtres
GET http://localhost:8001/api/export/alerts
```

---

## Fichiers modifiés

```
Backend:
├── routes/api.php (ajout de 4 routes export)
├── app/Http/Controllers/Api/ExportController.php (remplacement avec 4 méthodes)
├── app/Exports/CdrOccExport.php (nouvelle classe)
├── app/Exports/CdrMmgExport.php (nouvelle classe)
├── app/Exports/ServicesExport.php (nouvelle classe)
└── app/Exports/AlertsExport.php (nouvelle classe)

Frontend:
├── src/api/excelDownload.js (nouvel utilitaire)
├── src/pages/CdrOcc.jsx (ajout de l’export)
├── src/pages/CdrMmg.jsx (ajout de l’export)
├── src/pages/Services.jsx (ajout de l’export)
├── src/pages/Alerts.jsx (ajout de l’export)
└── src/index.css (ajout de l’animation spin)
```

---

## Checklist de déploiement

- [x] Routes API configurées
- [x] Contrôleur Excel implémenté
- [x] Classes Export créées (4)
- [x] Pages React modifiées (4)
- [x] Fonction utilitaire excelDownload créée
- [x] Animation CSS spin ajoutée
- [x] Tests basic validés
- [x] Message d'erreur implémenté
- [x] Filtres appliqués dans exports
- [x] Limite 10 000 lignes en place

---

## Déploiement

Pour déployer en production :

```bash
# 1. Rebuild containers (si changements)
docker-compose down
docker-compose up -d --build

# 2. Vérifier routes
docker exec smsplus-project-api-1 php artisan route:list | grep export

# 3. Tester endpoint
curl -s http://localhost:8001/api/health | jq .

# 4. Aller sur http://localhost:5173
```
