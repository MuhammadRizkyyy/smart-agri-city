# Cara Membuka Service di Smart Agri City

## Quick Start: Docker Compose

Jalankan semua service sekaligus dengan satu command:

```bash
# Navigate ke project root
cd /path/to/smart-agri-city

# Start semua containers (build jika belum ada)
docker compose up --build

# Atau untuk development mode (hot-reload, debug)
docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build
```

Tunggu sampai semua service healthy (±2-3 menit). Log akan menunjukkan port masing-masing.

---

## Service & Port Reference

| Service | Port | URL | Username | Password | Fungsi |
|---------|------|-----|----------|----------|--------|
| **MySQL** | 3307 | localhost:3307 | root | [DB_PASSWORD] | Database |
| **RabbitMQ** | 5674 / 15674 | http://localhost:15674 | guest | guest | Message Queue |
| **Mosquitto** | 1884 | localhost:1884 | admin | adminpass | MQTT Broker |
| **OAuth Server** | 3102 | http://localhost:3102 | - | - | Auth Service |
| **API Gateway** | 3106 | http://localhost:3106 | - | - | REST API |
| **PHP Farmer** | 8010 | http://localhost:8010 | - | - | Farmer Service |
| **PHP Crop** | 8011 | http://localhost:8011 | - | - | Crop Service |
| **PHP Irrigation** | 8012 | http://localhost:8012 | - | - | Irrigation Service |
| **Python ML** | 5001 | http://localhost:5001 | - | - | ML Predictions |
| **Node-RED** | 1881 | http://localhost:1881 | - | - | Flow Automation |
| **PhpMyAdmin** | 8888 | http://localhost:8888 | root | [DB_PASSWORD] | MySQL UI |
| **Prometheus** | 9091 | http://localhost:9091 | - | - | Metrics |
| **Grafana** | 3010 | http://localhost:3010 | admin | admin | Dashboard |

---

## Cara Membuka Setiap Service

### 1. **RabbitMQ Management UI**

```bash
# URL
http://localhost:15674
```

**Username/Password:** `guest / guest`

**Apa yang bisa Anda lihat:**
- Active queues (alerting, notifications, predictions)
- Messages count per queue
- Consumer connections
- Exchange bindings
- Connection metrics

**Untuk apa:**
- Monitor message flow dari PHP services → Python ML → Notification worker
- Debug message stuck di queue
- Manual message publish untuk testing

**Di-configure di:** `docker-compose.yml` line 48-66

---

### 2. **Prometheus (Metrics Scraper)**

```bash
# URL
http://localhost:9091
```

**Apa yang bisa Anda lihat:**
- All metrics being scraped
- Query metrics dengan PromQL (e.g., `rate(http_requests_total[5m])`)
- Alert rules status
- Target health

**Untuk apa:**
- Query historical metrics dari API Gateway, MySQL, RabbitMQ
- Write PromQL queries (e.g., average response time)
- Check data retention (default 7 hari)

**Contoh PromQL queries:**
```promql
# CPU usage
container_cpu_usage_seconds_total

# Memory usage
container_memory_usage_bytes

# API request rate
rate(http_requests_total[5m])

# MySQL query latency
rate(mysql_global_status_questions[1m])
```

**Di-configure di:** `monitoring/prometheus.yml`

---

### 3. **Grafana (Dashboards & Alerts)**

```bash
# URL
http://localhost:3010

# Login
Username: admin
Password: admin
```

**Apa yang bisa Anda lihat:**
- Pre-built dashboards (Smart Agri City monitoring)
- Real-time metrics dari Prometheus
- Alerts status
- Historical trends

**Untuk apa:**
- Monitor system health (CPU, memory, disk)
- Monitor application metrics (API latency, error rate)
- Check irrigation automation decisions
- View ML prediction accuracy

**Default Dashboard:** "Smart Agri City" (sudah auto-provisioned)

**Di-configure di:** 
- `monitoring/grafana/provisioning/dashboards/smart-agri-city.json`
- `monitoring/grafana/provisioning/datasources/prometheus.yml`

---

### 4. **Node-RED (Flow Automation)**

```bash
# URL
http://localhost:1881
```

**Apa yang bisa Anda lihat:**
- Visual workflow automation
- MQTT input nodes (subscribe ke agri/zone-*/sensor)
- Alert processing nodes
- Output nodes (database, notification, etc.)

**Untuk apa:**
- Setup automation logic tanpa code (drag-drop flows)
- Example: MQTT sensor → alert logic → database insert
- Create custom rules (e.g., IF moisture < 25 AND pH > 7.0 THEN trigger alert)
- Test MQTT messages secara real-time

**Pre-configured Flow:**
- Sudah ada di `iot/flows.json`
- Auto-import saat container start

**Cara menambah flow baru:**
1. Buka http://localhost:1881
2. Drag nodes dari sidebar (MQTT in, function, switch, database, etc.)
3. Connect nodes
4. Deploy (tombol merah "Deploy")
5. Akan auto-save ke `/data/flows.json`

**Contoh node yang sering dipakai:**
- **mqtt in** - Subscribe ke MQTT topic (agri/+/sensor)
- **function** - Custom JavaScript untuk logic
- **switch** - Conditional routing (IF-THEN)
- **delay** - Tambah delay antara nodes
- **mysql** - Query/insert ke database
- **http request** - POST ke API
- **debug** - Output ke debug panel

**Di-configure di:** 
- Image: `nodered/node-red:3.1`
- Flows: `iot/flows.json`
- Data volume: `nodered-data:/data`

---

### 5. **Mosquitto (MQTT Broker)**

```bash
# MQTT port (for IoT devices)
localhost:1884

# Username/Password
Username: admin
Password: adminpass
```

**Tidak ada UI**, tapi bisa testing dengan MQTT client:

```bash
# Install mosquitto-clients (macOS)
brew install mosquitto-clients

# Subscribe ke topic (see all sensor messages)
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass -t "agri/+/sensor" -v

# Publish test message
mosquitto_pub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/zone-a/test" \
  -m '{"test": "hello", "timestamp": "2026-06-28T12:00:00"}'
```

**Topics yang tersedia:**
```
agri/zone-a/sensor          ← Sensor readings (JSON)
agri/zone-b/sensor
agri/zone-c/sensor
agri/zone-d/sensor
agri/zone-e/sensor

agri/zone-*/moisture        ← Individual sensor values
agri/zone-*/temperature
agri/zone-*/ph
agri/zone-*/humidity
agri/zone-*/nitrogen
agri/zone-*/phosphorus
agri/zone-*/potassium

agri/zone-*/alert           ← Alert messages
```

**Di-configure di:** 
- `iot/mosquitto.conf`
- `iot/mosquitto-entrypoint.sh`
- Dockerfile: `iot/mosquitto.Dockerfile`

---

### 6. **MySQL (Database)**

#### Via PhpMyAdmin (GUI)
```bash
# URL
http://localhost:8888

# Login
Username: root
Password: [DB_PASSWORD dari .env]
```

#### Via Command Line
```bash
# Connect ke MySQL container
docker exec -it agri-mysql mysql -u root -p

# Enter password, then:
USE agriCity;
SHOW TABLES;
SELECT * FROM irr_sensor_readings LIMIT 10;
```

#### Via MySQL Client
```bash
# Install client
brew install mysql-client

# Connect
mysql -h 127.0.0.1 -P 3307 -u root -p

# Password: [DB_PASSWORD]
```

**Key Tables untuk check:**
- `irr_zones` - Irrigation zones
- `irr_sensor_readings` - Sensor data (time-series)
- `irr_irrigation_logs` - Irrigation events
- `frm_farmers` - User accounts
- `frm_lands` - Farmer lands
- `crp_alerts` - System alerts
- `oauth_tokens` - Active sessions

**Di-configure di:** `docker-compose.yml` line 32-50

---

## Workflow Sederhana: Trace Data End-to-End

### 1. Check IoT Simulator Output

```bash
# View simulator logs
docker logs -f agri-iot-simulator

# Output:
# [NORMAL] Zone A (padi): Moist=31.43% | Temp=32.0C | ...
# [ALERT: pH CRITICAL] ...
```

### 2. Subscribe ke MQTT Broker

```bash
# Di terminal baru
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass \
  -t "agri/zone-a/sensor" -v
```

### 3. Check Node-RED Processing

```bash
# Buka http://localhost:1881
# Klik Debug tab (kanan atas)
# Lihat messages yang diprocess
```

### 4. Check Database

```bash
# Terminal
docker exec -it agri-mysql mysql -u root -ppassword -e \
  "SELECT * FROM agriCity.irr_sensor_readings ORDER BY recorded_at DESC LIMIT 5;"
```

### 5. Monitor di Grafana

```bash
# Buka http://localhost:3010
# Lihat dashboard "Smart Agri City"
# Check metrics: sensor readings, alerts, system health
```

### 6. Check RabbitMQ Queues

```bash
# Buka http://localhost:15674
# Login: guest / guest
# Lihat queues: alerts, predictions, notifications
# Monitor message rate & consumer connections
```

---

## Troubleshooting

### Service tidak mau start

```bash
# Check if ports already in use
lsof -i :3106        # API Gateway
lsof -i :15674       # RabbitMQ Management
lsof -i :3010        # Grafana
lsof -i :1881        # Node-RED

# Kill process yang occupy port
kill -9 <PID>

# Atau change port di docker-compose.yml atau .env
```

### Container crashed

```bash
# View container logs
docker logs agri-node-red
docker logs agri-prometheus
docker logs agri-grafana

# Restart service
docker compose restart node-red
```

### Connections refused (containers can't reach each other)

```bash
# Check network
docker network ls
docker network inspect smart-agri-city_agri-network

# Verify DNS resolution inside container
docker exec agri-node-red getent hosts mosquitto
docker exec agri-node-red getent hosts rabbitmq
```

### MQTT messages not flowing to database

```bash
# 1. Check simulator sending
docker logs agri-iot-simulator | grep "TELEMETRY ROUND"

# 2. Check MQTT broker received
mosquitto_sub -h localhost -p 1884 -u admin -P adminpass -t "agri/+/sensor"

# 3. Check Node-RED subscribed
curl http://localhost:1881/api/flows

# 4. Check database
docker exec -it agri-mysql mysql -u root -p -e \
  "SELECT COUNT(*) FROM agriCity.irr_sensor_readings;"
```

---

## Environment Variables (.env)

Create `.env` file untuk customize port & credentials:

```bash
# Database
DB_PASSWORD=your_secure_password
DB_USERNAME=agri_user
DB_DATABASE=agriCity

# MQTT
MQTT_USERNAME=admin
MQTT_PASSWORD=adminpass
MQTT_CLIENT_ID=agri_simulator_docker

# RabbitMQ
RABBITMQ_USERNAME=guest
RABBITMQ_PASSWORD=guest

# Grafana
GRAFANA_USER=admin
GRAFANA_PASSWORD=admin

# Ports (optional, defaults shown)
API_GATEWAY_PORT=3106
OAUTH_SERVER_PORT=3102
FARMER_SERVICE_PORT=8010
CROP_SERVICE_PORT=8011
IRRIGATION_SERVICE_PORT=8012
PYTHON_ML_PORT=5001
NODE_RED_PORT=1881
PHPMYADMIN_PORT=8888
PROMETHEUS_PORT=9091
GRAFANA_PORT=3010

# Timezone
TIMEZONE=Asia/Jakarta
```

---

## Development vs Production

### Development Mode (Hot Reload)

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build
```

**Features:**
- Code changes auto-reload (no rebuild needed)
- Debug ports exposed (9229, 9230, 5678)
- More verbose logging
- Faster iteration

**Services dengan hot-reload:**
- Node.js: API Gateway, OAuth Server
- Python: Python ML Service
- PHP: Farmer, Crop, Irrigation services

### Production Mode

```bash
docker compose up --build
```

**Features:**
- Optimized images
- Restart policies
- Health checks
- Volume persistence
- No debug ports exposed

---

## Useful Commands

```bash
# View all running containers & status
docker compose ps

# View specific service logs (follow)
docker logs -f agri-node-red

# Execute command in container
docker exec agri-mysql mysqladmin ping

# Stop all services
docker compose down

# Stop & remove volumes (CAREFUL - data loss)
docker compose down -v

# Rebuild specific service
docker compose build api-gateway

# Restart specific service
docker compose restart node-red

# View resource usage
docker stats

# Clean up stopped containers & dangling images
docker system prune
```

---

## Next Steps untuk Presentasi

1. **Start semua service:** `docker compose up --build`
2. **Demo data flow:** IoT Simulator → MQTT → Node-RED → Database
3. **Show Grafana dashboard:** Real-time monitoring
4. **Check RabbitMQ queues:** Message processing
5. **Query database:** Show time-series data
6. **Test alert logic:** Change Node-RED flow & trigger alerts

Good luck! 🚀
