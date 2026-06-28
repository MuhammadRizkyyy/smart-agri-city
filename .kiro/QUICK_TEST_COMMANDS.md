# Quick Test Commands - Copy-Paste Ready

Gunakan commands ini untuk verify semua service berjalan dengan baik.

---

## Setup (Jalankan Sekali)

```bash
# Install MQTT client (macOS)
brew install mosquitto-clients

# Atau Linux
sudo apt-get install mosquitto-clients

# Atau Windows (skip, gunakan docker/PowerShell)
```

---

## 1. Check All Services Status

```bash
# List running containers
docker compose ps

# Expected output: All services with status "Up"
```

---

## 2. Test RabbitMQ

**GUI (Web Browser):**
```
http://localhost:15674
Username: guest
Password: guest
```

**Command line:**
```bash
# Check connection
docker exec agri-rabbitmq rabbitmq-diagnostics -q ping

# View queues
docker exec agri-rabbitmq rabbitmqctl list_queues name messages consumers

# Expected queues: alerts, predictions, notifications, irrigation_alerts
```

---

## 3. Test Prometheus

**Web Browser:**
```
http://localhost:9091
```

**Test PromQL query:**
```bash
# In Prometheus UI, paste di search bar:
up

# Or:
rate(http_requests_total[5m])
```

---

## 4. Test Grafana

**Web Browser:**
```
http://localhost:3010

# Login
Username: admin
Password: admin

# Check dashboard: "Smart Agri City"
```

---

## 5. Test Node-RED

**Web Browser:**
```
http://localhost:1881

# Check flows deployed
# Check debug messages (right panel)
```

**Import test flow:**
```bash
# View current flows
curl -s http://localhost:1881/api/flows | jq .

# Should show flows array with nodes
```

---

## 6. Test MQTT Broker (Mosquitto)

### Subscribe to Sensor Data

**Terminal 1 - Subscribe (listener):**
```bash
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/+/sensor" -v
```

**Expected output (every 10 seconds):**
```
agri/zone-a/sensor {"moisture": 31.43, "temperature": 32.0, ...}
agri/zone-b/sensor {"moisture": 52.24, "temperature": 32.0, ...}
...
```

### Subscribe to Alerts Only

```bash
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/+/alert" -v
```

**Expected when alert triggered:**
```
agri/zone-a/alert {"type": "pH_critical", "value": 7.35, ...}
```

### Test Publish (Send Custom Message)

```bash
mosquitto_pub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/zone-test/custom" \
  -m '{"test": "message", "timestamp": "2026-06-28T12:00:00"}'
```

---

## 7. Test MySQL Database

### Via Docker

```bash
# Check database exists
docker exec agri-mysql mysql -u root -ppassword -e \
  "SHOW DATABASES;"

# Check tables
docker exec agri-mysql mysql -u root -ppassword -D agriCity -e \
  "SHOW TABLES;"

# Count sensor readings
docker exec agri-mysql mysql -u root -ppassword -D agriCity -e \
  "SELECT COUNT(*) as total_readings FROM irr_sensor_readings;"

# View latest 5 readings
docker exec agri-mysql mysql -u root -ppassword -D agriCity -e \
  "SELECT zone_id, moisture, temperature, pH, recorded_at FROM irr_sensor_readings ORDER BY recorded_at DESC LIMIT 5;"

# Count alerts
docker exec agri-mysql mysql -u root -ppassword -D agriCity -e \
  "SELECT alert_type, COUNT(*) FROM crp_alerts GROUP BY alert_type;"
```

### Via PhpMyAdmin (Web Browser)

```
http://localhost:8888

Username: root
Password: password (atau nilai DB_PASSWORD di .env)

Navigate to: agriCity → irr_sensor_readings
```

### Via MySQL CLI (Local)

```bash
# Connect
mysql -h 127.0.0.1 -P 3307 -u root -p

# Enter password, then:
USE agriCity;
SELECT COUNT(*) FROM irr_sensor_readings;
SELECT * FROM irr_sensor_readings ORDER BY recorded_at DESC LIMIT 1;
EXIT;
```

---

## 8. Test IoT Simulator

```bash
# View simulator logs
docker logs -f agri-iot-simulator

# Should show every 10 seconds:
# --- TELEMETRY ROUND: 2026-06-28T18:18:13.467281 ---
# [NORMAL] Zone A (padi): Moist=31.43% | ...
# [ALERT: pH CRITICAL] Soil pH 7.35 is outside range...
```

---

## 9. Test API Gateway

```bash
# Health check
curl http://localhost:3106/health

# Expected response:
# {"status":"ok"}

# List routes (if exposed)
curl http://localhost:3106/api/routes
```

---

## 10. Test OAuth Server

```bash
# Health check
curl http://localhost:3102/health

# Expected response:
# {"status":"ok"}
```

---

## 11. Test PHP Services

```bash
# Farmer Service
curl http://localhost:8010/health

# Crop Service
curl http://localhost:8011/health

# Irrigation Service
curl http://localhost:8012/health

# Expected response:
# {"status":"ok"}
```

---

## 12. Test Python ML Service

```bash
# Health check
curl http://localhost:5001/health

# Expected response (JSON or plaintext):
# {"status":"ok"} atau "ok"

# Check model prediction endpoint
curl -X POST http://localhost:5001/predict \
  -H "Content-Type: application/json" \
  -d '{
    "moisture": 30,
    "temperature": 32,
    "ph": 6.5,
    "nitrogen": 80,
    "phosphorus": 50,
    "potassium": 100,
    "humidity": 60
  }'
```

---

## 13. Full End-to-End Flow Test

```bash
# Terminal 1 - Watch simulator
docker logs -f agri-iot-simulator | grep "TELEMETRY\|ALERT"

# Terminal 2 - Subscribe MQTT
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/+/sensor" -v | head -20

# Terminal 3 - Monitor database
watch "docker exec agri-mysql mysql -u root -ppassword -D agriCity -e \
  'SELECT zone_id, COUNT(*) as count FROM irr_sensor_readings GROUP BY zone_id;'"

# Terminal 4 - Check Prometheus scraping
curl -s http://localhost:9091/api/v1/query?query=up | jq '.data.result[] | {job: .metric.job, instance: .metric.instance, value: .value[1]}'

# Terminal 5 - Open in browser
open http://localhost:3010          # Grafana
open http://localhost:15674         # RabbitMQ
open http://localhost:1881          # Node-RED
open http://localhost:8888          # PhpMyAdmin
```

---

## 14. Debug: Verify Data Flow Path

```bash
# 1. Simulator sending?
docker logs agri-iot-simulator 2>&1 | tail -5

# 2. MQTT broker received?
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/zone-a/sensor" -C 1

# 3. Node-RED processed?
curl -s http://localhost:1881/api/flows | jq '.[] | .nodes[] | .type'

# 4. Database stored?
docker exec agri-mysql mysql -u root -ppassword -D agriCity -e \
  "SELECT COUNT(*) FROM irr_sensor_readings WHERE recorded_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);"

# 5. Prometheus scraped?
curl -s http://localhost:9091/api/v1/query?query='irr_sensor_readings' | jq '.data.result'

# 6. Grafana dashboard updated?
curl -s http://localhost:3010/api/dashboards/uid/smart-agri-city | jq '.dashboard.panels[0].title'
```

---

## 15. Container Resource Check

```bash
# View memory/CPU usage
docker stats --no-stream

# Expected healthy state:
# api-gateway:     ~50-100MB
# node-red:        ~60-120MB
# grafana:         ~80-150MB
# prometheus:      ~100-200MB
# mysql:           ~200-400MB (depends on data)
# rabbitmq:        ~100-200MB
```

---

## 16. Network Connectivity Test

```bash
# Test if containers can reach each other
docker exec agri-node-red ping -c 1 mosquitto
docker exec agri-node-red ping -c 1 rabbitmq
docker exec agri-node-red ping -c 1 mysql
docker exec agri-python-ml ping -c 1 rabbitmq

# All should return "1 packets transmitted, 1 received"
```

---

## 17. Logs Aggregation

```bash
# View all service logs
docker compose logs

# Follow all logs
docker compose logs -f

# Follow specific service
docker compose logs -f node-red prometheus grafana

# Filter logs (example: only errors)
docker compose logs | grep -i error

# Last 100 lines
docker compose logs --tail=100
```

---

## Bonus: Performance Testing

```bash
# Monitor MQTT message throughput
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/+/sensor" | pv -L 1000 > /dev/null

# Monitor database insert rate
while true; do
  docker exec agri-mysql mysql -u root -ppassword -D agriCity -e \
    "SELECT DATE_FORMAT(MAX(recorded_at), '%H:%i:%S') as latest, COUNT(*) as today FROM irr_sensor_readings WHERE DATE(recorded_at) = CURDATE();"
  sleep 5
done
```

---

## Troubleshooting Checklist

- [ ] All containers running: `docker compose ps`
- [ ] MQTT broker healthy: `docker logs agri-mosquitto | grep "ready"`
- [ ] Simulator sending: `docker logs agri-iot-simulator | tail -5`
- [ ] MQTT messages flowing: `mosquitto_sub -h localhost -p 1884 -u admin -P adminpass -t "agri/+/sensor" -C 1`
- [ ] Database populated: `docker exec agri-mysql mysql -u root -ppassword -D agriCity -e "SELECT COUNT(*) FROM irr_sensor_readings;"`
- [ ] Prometheus scraping: `http://localhost:9091` → Targets all green
- [ ] Grafana connected: `http://localhost:3010` → Dashboard shows data
- [ ] Node-RED working: `http://localhost:1881` → No red error nodes

---

## Ready for Presentation!

All commands di atas bisa langsung di-copy-paste untuk demo kepada dosen. Combine dengan screenshots dari dashboard untuk full impact! 🎉
