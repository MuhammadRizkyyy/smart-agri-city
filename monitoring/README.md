# Monitoring Stack

## Components
- Prometheus
- Grafana
- RabbitMQ Metrics

## Scrape Targets
- gateway:3000
- php-farmer:8000
- php-crop:8001
- php-irrigation:8002
- python-ml:5000
- rabbitmq:15692

## Dashboard Panels
1. Soil Moisture per Zona
2. Pest Alert Count
3. Predicted Yield Trend
4. Irrigation Volume
5. ML Response Latency

## Scrape Interval
15 seconds

## Labels
- env=production
- project=smart-agri-city