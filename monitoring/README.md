# Monitoring Stack

## Components
* Prometheus
* Grafana
* RabbitMQ Metrics Exporter

## Scrape Targets
Prometheus melakukan scraping metrics dari service berikut:
* gateway:3000
* php-farmer:8000
* php-crop:8001
* php-irrigation:8002
* python-ml:5000
* rabbitmq:15692

## Dashboard Panels
Dashboard Grafana terdiri dari 5 panel utama:
1. **Soil Moisture per Zona**
   Menampilkan tren kelembapan tanah dari sensor irigasi.

2. **Pest Alert Count**
   Menampilkan jumlah alert hama yang terdeteksi.

3. **Predicted Yield Trend**
   Menampilkan tren prediksi hasil panen dari ML Service.

4. **Irrigation Volume**
   Menampilkan total volume irigasi per zona.

5. **ML Response Latency**
   Menampilkan distribusi waktu respons endpoint prediksi ML.

## Prometheus Configuration

### Scrape Interval
```yaml
scrape_interval: 15s
```

### External Labels

```yaml
env: production
project: smart-agri-city
```

## Files
* `monitoring/prometheus.yml`
* `monitoring/grafana-dashboard.json`

## Notes
Konfigurasi ini merupakan baseline monitoring untuk Smart Agri City. Integrasi metrik aktual dari setiap service akan dilakukan setelah seluruh service menyediakan endpoint `/metrics`.
