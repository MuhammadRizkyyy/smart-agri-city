# Smart Agri City - Complete Architecture Overview

Ini lengkap breakdown semua component & bagaimana mereka bekerja sama.

---

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          SMART AGRI CITY ARCHITECTURE                           │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ╔════════════════════════════════════════════════════════════════════════╗    │
│  ║                        PRESENTATION LAYER                              ║    │
│  ╠════════════════════════════════════════════════════════════════════════╣    │
│  ║                                                                        ║    │
│  ║  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌────────────┐ ║    │
│  ║  │   Grafana    │  │  PhpMyAdmin  │  │  Node-RED    │  │ RabbitMQ   │ ║    │
│  ║  │   Dashboard  │  │   Database   │  │  Automation  │  │  Dashboard │ ║    │
│  ║  │   :3010      │  │    :8888     │  │   :1881      │  │   :15674   │ ║    │
│  ║  └──────────────┘  └──────────────┘  └──────────────┘  └────────────┘ ║    │
│  ║                                                                        ║    │
│  ╚════════════════════════════════════════════════════════════════════════╝    │
│                                      ↓                                          │
│  ╔════════════════════════════════════════════════════════════════════════╗    │
│  ║                          API GATEWAY LAYER                             ║    │
│  ╠════════════════════════════════════════════════════════════════════════╣    │
│  ║                                                                        ║    │
│  ║  ┌────────────────────────────────────────────────────────────────┐   ║    │
│  ║  │  Express API Gateway (:3106)                                  │   ║    │
│  ║  │  - JWT Authentication middleware                             │   ║    │
│  ║  │  - Rate limiting & metrics collection                        │   ║    │
│  ║  │  - Request routing to microservices                          │   ║    │
│  ║  │  - Response aggregation                                      │   ║    │
│  ║  └────────────────────────────────────────────────────────────────┘   ║    │
│  ║                                                                        ║    │
│  ║  ┌────────────────────────────────────────────────────────────────┐   ║    │
│  ║  │  OAuth Server (:3102)                                         │   ║    │
│  ║  │  - User authentication (email/password)                       │   ║    │
│  ║  │  - Google OAuth integration                                   │   ║    │
│  ║  │  - JWT token generation & validation                         │   ║    │
│  ║  │  - Session management (oauth_tokens table)                   │   ║    │
│  ║  └────────────────────────────────────────────────────────────────┘   ║    │
│  ║                                                                        ║    │
│  ╚════════════════════════════════════════════════════════════════════════╝    │
│                    ↙                    ↓                    ↘                   │
│  ╔═══════════════════╗  ╔════════════════════╗  ╔═══════════════════════╗     │
│  ║  SERVICE LAYER    ║  ║  MESSAGE QUEUE     ║  ║  IoT / MONITORING     ║     │
│  ╠═══════════════════╣  ╠════════════════════╣  ╠═══════════════════════╣     │
│  ║                   ║  ║                    ║  ║                       ║     │
│  ║ ┌───────────────┐ ║  ║  RabbitMQ          ║  ║  IoT Simulator        ║     │
│  ║ │ Farmer Svc    │ ║  ║  (:5674, :15674)   ║  ║  └─→ MQTT Messages    ║     │
│  ║ │ (:8010)       │ ║  ║                    ║  ║                       ║     │
│  ║ ├───────────────┤ ║  ║ Exchanges:         ║  ║  Mosquitto MQTT       ║     │
│  ║ │ Crop Svc      │ ║  ║ • alerts           ║  ║  Broker (:1884)       ║     │
│  ║ │ (:8011)       │ ║  ║ • predictions      ║  ║                       ║     │
│  ║ ├───────────────┤ ║  ║ • notifications    ║  ║  Prometheus           ║     │
│  ║ │ Irrigation    │ ║  ║ • irrigation       ║  ║  Scraper (:9091)      ║     │
│  ║ │ Svc (:8012)   │ ║  ║                    ║  ║                       ║     │
│  ║ │               │ ║  ║ Consumers:         ║  ║  Node-RED             ║     │
│  ║ │ (PHP + MySQL) │ ║  ║ • notification     ║  ║  Flow Engine (:1881)  ║     │
│  ║ │               │ ║  ║   worker           ║  ║                       ║     │
│  ║ │ • Pest alerts │ ║  ║ • irrigation       ║  ║  Grafana              ║     │
│  ║ │ • Soil NPK    │ ║  ║   automation       ║  ║  Dashboard (:3010)    ║     │
│  ║ │ • Harvests    │ ║  ║                    ║  ║                       ║     │
│  ║ └───────────────┘ ║  ║                    ║  ║                       ║     │
│  ║                   ║  ╠════════════════════╣  ║                       ║     │
│  ║ ┌───────────────┐ ║  ║ Python ML Service  ║  ║                       ║     │
│  ║ │ Python ML     │ ║  ║ • Consume alerts   ║  ║                       ║     │
│  ║ │ (:5001)       │ ║  ║ • Predict needs    ║  ║                       ║     │
│  ║ │               │ ║  ║ • Publish to queue ║  ║                       ║     │
│  ║ │ • Sensor      │ ║  │                    │  ║                       ║     │
│  ║ │   prediction  │ ║  └────────────────────┘  ║                       ║     │
│  ║ │ • Anomaly     │ ║                           ║                       ║     │
│  ║ │   detection   │ ║                           ║                       ║     │
│  ║ │ • Yield       │ ║                           ║                       ║     │
│  ║ │   estimation  │ ║                           ║                       ║     │
│  ║ └───────────────┘ ║  ║                       ║                       ║     │
│  ║                   ║  ║                       ║                       ║     │
│  ╚═══════════════════╝  ╚════════════════════╗  ╚═══════════════════════╝     │
│                                    │           │                               │
│                         ┌──────────┴───────────┘                               │
│                         ↓                                                      │
│  ╔════════════════════════════════════════════════════════════════════════╗    │
│  ║                      DATA PERSISTENCE LAYER                            ║    │
│  ╠════════════════════════════════════════════════════════════════════════╣    │
│  ║                                                                        ║    │
│  ║  ┌─────────────────────────────────────────────────────────────────┐  ║    │
│  ║  │  MySQL Database (:3307)                                         │  ║    │
│  ║  │                                                                 │  ║    │
│  ║  │  ├─ IRRIGATION SERVICE (irr_)                                  │  ║    │
│  ║  │  │  ├─ irr_zones (zone info)                                  │  ║    │
│  ║  │  │  ├─ irr_sensor_readings (time-series data)                 │  ║    │
│  ║  │  │  └─ irr_irrigation_logs (automation history)               │  ║    │
│  ║  │  │                                                             │  ║    │
│  ║  │  ├─ FARMER SERVICE (frm_)                                     │  ║    │
│  ║  │  │  ├─ frm_farmers (user accounts)                           │  ║    │
│  ║  │  │  ├─ frm_lands (farmer lands)                              │  ║    │
│  ║  │  │  └─ frm_harvests (yield data)                             │  ║    │
│  ║  │  │                                                             │  ║    │
│  ║  │  ├─ CROP SERVICE (crp_)                                       │  ║    │
│  ║  │  │  ├─ crp_crop_schedules (planting plan)                    │  ║    │
│  ║  │  │  ├─ crp_alerts (system alerts)                            │  ║    │
│  ║  │  │  └─ crp_soil_conditions (lab results)                     │  ║    │
│  ║  │  │                                                             │  ║    │
│  ║  │  └─ AUTH SERVICE (oauth_)                                      │  ║    │
│  ║  │     ├─ oauth_clients (registered apps)                        │  ║    │
│  ║  │     └─ oauth_tokens (active sessions)                         │  ║    │
│  ║  │                                                                │  ║    │
│  ║  └─────────────────────────────────────────────────────────────────┘  ║    │
│  ║                                                                        ║    │
│  ║  ┌─────────────────────────────────────────────────────────────────┐  ║    │
│  ║  │  Prometheus Time-Series Database (:9091)                        │  ║    │
│  ║  │  • Metrics from API Gateway                                     │  ║    │
│  ║  │  • System resource usage (CPU, memory, disk)                    │  ║    │
│  ║  │  • Application performance (response time, error rate)          │  ║    │
│  ║  │  • Data retention: 7 days (configurable)                        │  ║    │
│  ║  └─────────────────────────────────────────────────────────────────┘  ║    │
│  ║                                                                        ║    │
│  ╚════════════════════════════════════════════════════════════════════════╝    │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Component Breakdown & Port Reference

### 1. **IoT Layer**
- **IoT Simulator** (Python) - Generate synthetic sensor data
- **Mosquitto MQTT Broker** (:1884) - Pub/sub messaging for IoT devices
- **Topics:** `agri/zone-{a-e}/sensor`, `agri/zone-{a-e}/alert`

### 2. **API Gateway Layer**
- **Express Gateway** (:3106) - Main entry point, routing, auth
- **OAuth Server** (:3102) - User authentication, JWT tokens

### 3. **Service Layer (Microservices)**
- **Farmer Service** (:8010) - User & land management
- **Crop Service** (:8011) - Crop schedules, alerts, soil conditions
- **Irrigation Service** (:8012) - Irrigation control & logging
- **Python ML Service** (:5001) - Predictions, anomaly detection

### 4. **Message Queue**
- **RabbitMQ** (:5674 AMQP, :15674 Management UI)
- **Queues:** alerts, predictions, notifications, irrigation_alerts
- **Consumers:** notification_worker, python_ml_service

### 5. **Automation & Flow**
- **Node-RED** (:1881) - Visual flow automation
- **Handles:** MQTT → processing → database/API writes

### 6. **Monitoring & Observability**
- **Prometheus** (:9091) - Metrics scraper & time-series DB
- **Grafana** (:3010) - Visualization & alerting

### 7. **Data Layer**
- **MySQL** (:3307) - Main transactional database
- **Volumes:** mysql-data, rabbitmq-data, prometheus-data, nodered-data, grafana-data

### 8. **UI/Management**
- **PhpMyAdmin** (:8888) - MySQL web interface
- **RabbitMQ Management** (:15674) - Queue management

---

## Data Flow Examples

### Example 1: Sensor Reading → Database

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Simulator generates sensor data (random values)              │
│    moisture=31.43%, temp=32C, pH=7.35, NPK values               │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 2. Publish to MQTT Broker                                       │
│    Topic: agri/zone-a/sensor                                    │
│    Payload: {moisture: 31.43, temperature: 32.0, ...}          │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 3. Node-RED subscribes to agri/+/sensor                         │
│    Validates & transforms data                                  │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 4. Insert to MySQL                                              │
│    INSERT INTO irr_sensor_readings                              │
│    (zone_id, moisture, temperature, pH, ...) VALUES (...)       │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 5. Prometheus scrapes metrics from API Gateway                  │
│    Stores: request count, latency, error rate                   │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 6. Grafana queries Prometheus & MySQL                           │
│    Displays real-time dashboard                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### Example 2: Alert Trigger → Notification

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Sensor reading: pH=7.35 (outside range 5.5-7.0)              │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 2. Simulator publishes alert message                            │
│    Topic: agri/zone-a/alert                                     │
│    Type: "ph_critical", value: 7.35                             │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 3. Node-RED receives alert                                      │
│    Determines severity & action needed                          │
│    Publishes to RabbitMQ queue: "alerts"                        │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 4. PHP Crop Service consumes from "alerts" queue                │
│    Inserts to MySQL: crp_alerts table                           │
│    Calls ML Service for recommendation                          │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 5. Python ML Service processes alert                            │
│    Generates recommendation (add lime, adjust pH)               │
│    Publishes to RabbitMQ: "notifications" queue                 │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 6. Notification Worker consumes "notifications"                 │
│    Sends SMS/WhatsApp/Email to farmer                           │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 7. Grafana shows alert in dashboard                             │
│    Alert persists until resolved_at timestamp set               │
└─────────────────────────────────────────────────────────────────┘
```

---

### Example 3: Automatic Irrigation Decision

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Sensor: Moisture = 24% (below 25% threshold)                 │
│    Trigger: DROUGHT state                                       │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 2. Simulator publishes drought alert                            │
│    Topic: agri/zone-c/alert                                     │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 3. Node-RED automation flow                                     │
│    Checks: recent_events, soil_type, crop_phase                │
│    Decision: "Need irrigation NOW"                              │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 4. POST to Irrigation Service                                   │
│    POST /api/irrigation/start                                   │
│    zone_id: 3, trigger_type: "otomatis_ml"                      │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 5. Irrigation Service activates pump                            │
│    Logs to irr_irrigation_logs:                                 │
│    (zone_id: 3, started_at: now, trigger_type: "otomatis_ml")  │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 6. Wait for duration (calculated by ML)                         │
│    e.g., 15 minutes for 5 hours to recover moisture             │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 7. Stop pump                                                    │
│    Update irr_irrigation_logs: ended_at: now                    │
│    Calculate volume_liters = flow_rate * duration               │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 8. Notify farmer                                                │
│    "Zone C: Auto-irrigation completed. 750L used. Moisture OK." │
│    Send to RabbitMQ: notifications queue                        │
└──────────────────────┬──────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────┐
│ 9. Grafana shows event in timeline                              │
│    "IRRIGATION: Zone C, 750L, 15 mins, trigger:ML"             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Environment & Network

### Docker Network
```
Network: agri-network (172.25.0.0/16)
Driver: bridge

Containers dapat reach masing-masing via hostname:
- mysql:3306
- rabbitmq:5672
- mosquitto:1883
- node-red:1880 (internal)
- prometheus:9090 (internal)
```

### Volumes
```
mysql-data           → /var/lib/mysql (persistent database)
rabbitmq-data        → /var/lib/rabbitmq (persistent queues)
nodered-data         → /data (flows, settings)
prometheus-data      → /prometheus (metrics)
grafana-data         → /var/lib/grafana (dashboards, datasources)
```

---

## Service Dependencies

```
OAuth Server ← MySQL
         ↓
API Gateway
    ↙   ↓   ↘
  /     |      \
Farmer Crop  Irrigation Service (PHP)
 Svc    Svc          Svc
  ↓     ↓            ↓
  └─ MySQL ─────┘
      ↓
  RabbitMQ
      ↓
  ┌───┴───┬──────────┐
  ↓       ↓          ↓
Python ML Notification Node-RED
Service   Worker     

                ↓
            Metrics (Prometheus)
                ↓
            Grafana Dashboard

Mosquitto (IoT)
    ↓
Node-RED ─→ Database/API
    ↓
Simulation
```

---

## For Presentation

**Key points to mention:**

1. **Microservices Architecture**
   - Setiap service independen (dapat di-scale, update terpisah)
   - PHP services untuk business logic (farmer, crop, irrigation)
   - Python ML untuk predictions & anomaly detection
   - Node.js untuk API gateway & authentication

2. **Real-Time Data Pipeline**
   - IoT Simulator → MQTT Broker → Node-RED → Database
   - Latency: <1 detik dari sensor ke database

3. **Message Queue (RabbitMQ)**
   - Decouple services (tidak harus saling menunggu)
   - Reliability (messages persisted jika consumer down)
   - Scalability (banyak consumers dapat process messages parallel)

4. **Monitoring & Observability**
   - Prometheus scrapes metrics dari semua services
   - Grafana visualisasi metrics & alerts
   - Can trace issues dengan log aggregation

5. **Security**
   - JWT authentication via OAuth Server
   - Role-based access control (RBAC)
   - Secure MQTT with username/password

---

## Next: Start Services & Demo!

```bash
docker compose up --build

# Then open in browser:
Grafana:   http://localhost:3010
Node-RED:  http://localhost:1881
RabbitMQ:  http://localhost:15674
PhpMyAdmin: http://localhost:8888
```

Enjoy! 🚀
