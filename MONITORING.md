# SMS+ Monitoring with Grafana

This project now ships a Grafana setup with two dashboard layers:

- API observability (`SMS+ API Observability`)
- Business CDR monitoring (`SMS+ CDR Business Monitoring`)

## Start stack

From project root:

```bash
docker compose up -d
```

Services:

- API: `http://localhost:8000`
- Front: `http://localhost:5173`
- Grafana: `http://localhost:3000`

Grafana credentials (local default):

- user: `admin`
- password: `admin`

## Provisioning

Provisioned files:

- Datasource: `monitoring/grafana/provisioning/datasources/datasource.yml`
- Dashboard provider: `monitoring/grafana/provisioning/dashboards/dashboards.yml`
- Dashboards:
  - `monitoring/grafana/dashboards/smsplus-observability.json`
  - `monitoring/grafana/dashboards/smsplus-cdr-business.json`

Datasource points to PostgreSQL service `db:5432` (database `smsplus`).

## API observability layer

Instrumentation middleware `TrackApiRequestMetrics` records request metrics into:

- table: `ra_t_api_request_metrics`

Columns include:

- `path`, `method`, `status_code`, `duration_ms`
- `user_id`, `role`, `error_class`, `created_at`

Dashboard metrics include:

- request throughput
- p95 latency
- availability %
- error rate %
- top endpoints

## Business CDR layer

Dashboard focuses on:

- daily OCC vs MMG volumes
- filtered OCC revenue
- open alerts and MMG/OCC gap alerts
- top keywords and unresolved alerts

## Notes

- On first launch, run traffic through API to populate observability metrics.
- If you already had containers running, restart after changes:

```bash
docker compose down
docker compose up -d
```
