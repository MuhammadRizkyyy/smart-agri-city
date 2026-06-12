# Monitoring Stack — Smart Agri City

## Akses

| Service    | URL                          | Credentials   |
|------------|------------------------------|---------------|
| Prometheus | http://localhost:9090        | —             |
| Grafana    | http://localhost:3001        | admin / admin |
| RabbitMQ   | http://localhost:15672       | guest / guest |

## Cara Menjalankan

```bash
# Start semua service termasuk monitoring
docker compose up --build -d

# Cek status scrape targets Prometheus
open http://localhost:9090/targets

# Grafana auto-load dashboard dari provisioning
open http://localhost:3001
```

## Komponen

| Container         | Image                        | Port  |
|-------------------|------------------------------|-------|
| agri-prometheus   | prom/prometheus:v2.51.2      | 9090  |
| agri-grafana      | grafana/grafana:10.4.2       | 3001  |

## Scrape Targets (prometheus.yml)

| Job           | Target                | Scrape Path |
|---------------|-----------------------|-------------|
| gateway       | api-gateway:3000      | /metrics    |
| php-farmer    | php-farmer:8000       | /metrics    |
| php-crop      | php-crop:8001         | /metrics    |
| php-irrigation| php-irrigation:8002   | /metrics    |
| python-ml     | python-ml:5000        | /metrics    |
| rabbitmq      | rabbitmq:15692        | /metrics    |

Scrape interval: **15 detik**. External labels: `env=production`, `project=smart-agri-city`.

## Metrics yang Di-expose

### API Gateway (prom-client)
- `http_requests_total{method, path, status}` — counter semua request
- `http_request_duration_seconds{method, path, status}` — histogram latency
- `rate_limit_hits_total{limiter}` — counter request yang diblokir rate limiter
- Default Node.js metrics (CPU, memory, event loop, GC)

### Python ML Service (prometheus-fastapi-instrumentator)
- `http_request_duration_seconds` — auto-instrumented semua route
- `predicted_yield` — gauge: nilai prediksi yield terakhir (ton/ha)
- `irrigation_volume` — gauge: volume irigasi terakhir yang direkomendasikan (liter)

### PHP Farmer (custom Prometheus text format)
- `farmer_service_up` — service health
- `irr_sensor_readings` — total sensor readings
- `irr_sensor_moisture_percent{zone_id}` — rata-rata moisture per zona (1h terakhir)
- `crp_alerts` — total pest alerts
- `farmer_total` — jumlah petani terdaftar
- `harvest_records_total` — total catatan panen

### PHP Crop (custom Prometheus text format)
- `crop_service_up` — service health
- `crp_pest_alerts_total{pest_type}` — counter alert per tipe hama
- `crp_alerts` — total alerts
- `crp_alerts_active` — alerts belum resolved
- `crp_schedules_total` — jumlah jadwal tanam
- `crp_soil_conditions_total` — jumlah catatan kondisi tanah

### PHP Irrigation (custom Prometheus text format)
- `irrigation_service_up` — service health
- `irr_sensor_moisture_percent{zone_id}` — rata-rata moisture per zona (1h terakhir)
- `irr_sensor_readings_total` — total sensor readings
- `irr_irrigation_volume_liters_total{zone_id}` — volume irigasi hari ini per zona
- `irr_zones_active` — jumlah zona aktif

### RabbitMQ (built-in prometheus plugin)
- `rabbitmq_queue_messages`, `rabbitmq_connections`, dll — auto-exposed di :15692/metrics

## Grafana Dashboard — 5 Panel Wajib

Dashboard di-provision otomatis dari `monitoring/grafana/provisioning/`.

| # | Panel | Tipe | Metric Utama |
|---|-------|------|--------------|
| 1 | Soil Moisture per Zona | Time series | `irr_sensor_moisture_percent{zone_id}` |
| 2 | Pest Alert Count | Bar chart | `crp_pest_alerts_total{pest_type}` |
| 3 | Predicted Yield Trend | Time series | `predicted_yield` |
| 4 | Irrigation Volume | Gauge + Time series | `irrigation_volume`, `irr_irrigation_volume_liters_total` |
| 5 | ML Response Latency | Time series | `histogram_quantile(0.95/0.99/0.50, http_request_duration_seconds_bucket)` |

Time range default: **Last 1 hour**. Auto-refresh: **30 detik**.

## Import Dashboard Manual

Jika perlu import ulang:
1. Buka Grafana → Dashboards → Import
2. Upload file `monitoring/grafana-dashboard.json`
3. Pilih datasource **Prometheus** → Import

## File Structure

```
monitoring/
├── prometheus.yml                          # Konfigurasi scrape targets
├── grafana-dashboard.json                  # Dashboard portable (untuk import manual)
├── grafana/
│   └── provisioning/
│       ├── datasources/
│       │   └── prometheus.yml              # Auto-configure Prometheus datasource
│       └── dashboards/
│           ├── dashboard.yml               # Provider config
│           └── smart-agri-city.json        # Dashboard (auto-load saat Grafana start)
└── rabbitmq/
    └── enabled_plugins                     # Aktifkan rabbitmq_prometheus plugin
```
