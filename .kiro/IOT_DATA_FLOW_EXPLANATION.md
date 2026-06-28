# IoT Data Flow: Simulator → MQTT → Database

## Jawaban Singkat
**Log yang Anda lihat adalah output dari `simulator.py` yang PRINT ke console (stdout).** 

Data tersebut:
- ✅ **Dibuat random** oleh simulator.py (menggunakan Gaussian distribution & state machine)
- ✅ **Dipublikasikan ke MQTT broker** via MQTT protocol
- ❓ **Tersimpan ke database** - tergantung apakah ada service yang subscribe & insert ke DB

---

## Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ simulator.py (Docker IoT Container)                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 1. ZoneSimulator.update() generates random metrics:            │
│    - moisture = clamp(old_value + gauss(0, 1.5), 30, 75)      │
│    - temperature = clamp(...) + seasonal factor               │
│    - pH, NPK, humidity, etc. all procedurally generated       │
│                                                                 │
│ 2. Prints to stdout (CONSOLE) - ini yang Anda lihat!          │
│    [NORMAL] Zone A (padi): Moist=31.43% | Temp=32.0C | ...   │
│    [ALERT: pH CRITICAL] Soil pH 7.35 is outside range...     │
│                                                                 │
│ 3. Publishes JSON to MQTT Broker (3 types per zone):         │
│    - Bundled topic: agri/zone-a/sensor                        │
│    - Individual topics: agri/zone-a/moisture, etc.           │
│    - Alert topic: agri/zone-a/alert                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                         ↓ (MQTT Network)
                    mosquitto (MQTT Broker)
                    Port 1883
                         ↓
        ┌────────────────┬────────────────┐
        ↓                ↓                ↓
   Express         PHP Service      Other Services
   Gateway         (crop/alerts)    (monitoring,ML)
   
   Kalau ada service subscribe ke agri/# topics,
   maka ia bisa terima data & insert ke database

   Kalau tidak ada subscriber,
   data hanya di-log di console & di-hold di MQTT broker
   (sampai TTL expire)
```

---

## Detail Teknis

### 1. Random Data Generation

File: `iot/simulator.py` lines 97-160

```python
class ZoneSimulator:
    def __init__(self, zone_id, crop):
        # Initial state: random nilai
        self.moisture = random.uniform(40.0, 60.0)
        self.temperature = random.uniform(24.0, 28.0)
        self.ph = random.uniform(6.0, 6.8)
        # ... dst
```

Setiap 10 detik (default `MQTT_PUBLISH_INTERVAL`), method `update()` dipanggil:

```python
def update(self, hour):
    # Probabilistic state changes
    if self.state == "NORMAL":
        roll = random.random()
        if roll < 0.04:           # 4% chance DROUGHT
            self.state = "DROUGHT"
        elif roll < 0.08:         # 4% chance PEST
            self.state = "PEST"
        elif roll < 0.09:         # 1% chance pH anomaly
            self.ph = 4.3 or 8.2
    
    # Update metrics dengan Gaussian noise
    self.moisture = clamp(
        self.moisture + random.gauss(0, 1.5) - 0.3 * time_factor,
        30.0, 75.0
    )
    self.temperature = clamp(
        self.temperature + random.gauss(0, 0.4) + 0.8 * time_factor,
        22.0, 32.0
    )
    # ... dst semua field diupdate dengan noise + seasonal factor
```

**Key insights:**
- Data berubah setiap round (realistis, bukan static)
- Ada `time_factor` yang simulate siang/malam cycle
- Ada probabilistic state transitions (NORMAL → DROUGHT/PEST)
- Semua nilai di-clamp agar realistic range

---

### 2. Console Output (Logger)

Lines 251-266:

```python
state_color = GREEN if sim.state == "NORMAL" else RED
print(f"[{state_color}{sim.state}{RESET}] {ZONE_NAMES[zone]} ({sim.crop}): "
      f"Moist={metrics['moisture']}% | Temp={metrics['temperature']}C | pH={metrics['ph']} | "
      f"N={metrics['nitrogen']} | P={metrics['phosphorus']} | K={metrics['potassium']} | "
      f"Humid={metrics['humidity']}%")
```

**Ini yang Anda lihat di Docker log:**
```
[NORMAL] Zone A (padi): Moist=31.43% | Temp=32.0C | pH=7.35 | N=111.19 | P=35.46 | K=80.53 | Humid=55.98%
```

Warna output:
- GREEN = [NORMAL] - kondisi OK
- YELLOW = [DROUGHT] - moisture terlalu rendah
- RED = [PEST] - humidity tinggi + temperature tinggi

---

### 3. MQTT Publishing

Lines 240-250:

```python
bundle_payload = {
    **metrics,
    "zone": zone,
    "crop": sim.crop,
    "timestamp": timestamp_str
}

bundled_topic = f"agri/{zone}/sensor"
bundle_json = json.dumps(bundle_payload)

if connected:
    client.publish(bundled_topic, bundle_json, qos=1)  # Publish ke broker
```

**Format data yang dikirim:**
```json
{
  "moisture": 31.43,
  "temperature": 32.0,
  "ph": 7.35,
  "nitrogen": 111.19,
  "phosphorus": 35.46,
  "potassium": 80.53,
  "air_temp": 32.0,
  "humidity": 55.98,
  "light_lux": 10000.0,
  "zone": "zone-a",
  "crop": "padi",
  "timestamp": "2026-06-28T18:18:13.467281"
}
```

**Topics yang dipublikasikan:**
```
agri/zone-a/sensor           ← bundled sensor data (JSON)
agri/zone-a/moisture         ← individual sensor (31.43)
agri/zone-a/temperature      ← individual sensor (32.0)
agri/zone-a/ph               ← individual sensor (7.35)
...
agri/zone-a/alert            ← alert messages (DROUGHT, pH CRITICAL, PEST RISK)
```

---

### 4. Alert Generation

Lines 268-296:

```python
# Drought Alert (< 25%)
if metrics["moisture"] < 25:
    drought_payload = {"type": "drought_warning", "moisture": 24.0, ...}
    print(f"[ALERT: DROUGHT] Moisture 24.0% below critical threshold!")
    client.publish(f"agri/{zone}/alert", alert_json, qos=1)

# pH Critical Alert (< 5.5 or > 7.0)
if metrics["ph"] < 5.5 or metrics["ph"] > 7.0:
    ph_payload = {"type": "ph_critical", "value": 7.35, ...}
    print(f"[ALERT: pH CRITICAL] Soil pH 7.35 is outside range (5.5 - 7.0)!")
    client.publish(f"agri/{zone}/alert", alert_json, qos=1)

# Pest Risk Alert (humidity > 90% AND temp > 32°C)
if metrics["humidity"] > 90.0 and metrics["temperature"] > 32.0:
    pest_payload = {"type": "pest_indicator", ...}
    print(f"[ALERT: PEST RISK] High Humid (95.08%) & Temp (33.29C) - Risk of pests!")
    client.publish(f"agri/{zone}/alert", alert_json, qos=1)
```

Alert ini juga **print ke console** DAN **publish ke MQTT**.

---

## Data Flow: Simulator → Database (Question)

### Scenario 1: Log HANYA di Console (current state?)

```
simulator.py prints → Docker stdout logs
                   ↓
         docker logs iot-simulator (Anda lihat ini)
         
MQTT published → mosquitto broker (in-memory or persistent?)
               ↓
         (no subscriber) → data expires or stays in broker
```

**Result:** Data tidak tersimpan ke database.

---

### Scenario 2: Data Tersimpan ke Database

Ada 2 cara:

#### Cara A: Express Gateway Subscribe & Insert

```
simulator.py (publish MQTT)
    ↓
mosquitto broker
    ↓
Express Gateway (subscribe agri/+/sensor)
    ↓
PHP Crop Service atau Python ML Service
    ↓
MySQL: irr_sensor_readings
```

Ini requires:
1. Express gateway / PHP / Python service **subscribe** ke MQTT topics
2. Service parse JSON & insert ke database

---

#### Cara B: Node-RED atau MQTT Integration Service

```
simulator.py (publish MQTT)
    ↓
mosquitto broker
    ↓
Node-RED flow (agri/+/sensor → MySQL)
    ↓
MySQL: irr_sensor_readings
```

Atau:

```
simulator.py (publish MQTT)
    ↓
mosquitto broker
    ↓
Telegraf (MQTT input plugin)
    ↓
InfluxDB / MySQL
```

---

## Cek: Apakah Data Masuk Database?

Query MySQL untuk check:

```sql
USE agriCity;

-- Check recent sensor readings
SELECT * FROM irr_sensor_readings 
ORDER BY recorded_at DESC 
LIMIT 10;

-- Check volume data
SELECT 
  zone_id,
  COUNT(*) as reading_count,
  MAX(recorded_at) as latest,
  MIN(recorded_at) as oldest
FROM irr_sensor_readings
GROUP BY zone_id;
```

Jika query return kosong → data **tidak** masuk database, hanya di console + MQTT broker.

---

## Untuk Presentasi Dosen

### Jawab: "Log itu dari mana?"

**Jawaban lengkap:**
> "Log yang ditampilkan di Docker adalah **output dari simulator.py**. Simulator ini generate random sensor data secara procedural menggunakan Gaussian distribution, state machine transitions, dan seasonal factors untuk realism.
>
> Setiap update (default 10 detik):
> 1. Simulator generate nilai acak untuk 9 sensor (moisture, temp, pH, NPK, humidity, light)
> 2. Print ke console (STDOUT) - ini yang kita lihat di docker logs
> 3. Publish ke MQTT broker dalam 3 format: bundled JSON, individual values, alerts
>
> Data kemudian tersimpan ke database jika ada service yang:
> - Subscribe ke MQTT topics (agri/zone-*/sensor)
> - Parse JSON payload
> - Insert ke tabel irr_sensor_readings
>
> Jika tidak ada subscriber, data hanya di-log console & bertahan di MQTT broker sesuai TTL policy."

---

### Jawab: "Kenapa random, bukan real sensor?"

**Jawaban:**
> "Untuk development & testing, kita tidak bisa mount real sensors di semua zone. Simulator lebih praktis karena:
>
> 1. **Reproducible scenarios**: Bisa generate specific conditions (drought, pest, pH anomaly) untuk test alert logic
> 2. **Scale testing**: Test 5 zones simultaneously tanpa hardware
> 3. **Seasonal simulation**: Simulate year-round data dalam hitungan menit
> 4. **Cost efficient**: Tidak perlu invest hardware sebelum MVP
>
> Untuk production, ganti simulator dengan real IoT devices (Modbus sensors → Arduino/PLC → MQTT gateway)."

---

### Jawab: "Apakah data sudah masuk database?"

**Cek dengan:**
```bash
docker exec agricitydb mysql -u root -ppassword -e \
  "SELECT COUNT(*) FROM agriCity.irr_sensor_readings;"
```

Jika 0 → tidak masuk database (need to check why subscriber tidak subscribe)
Jika > 0 → data masuk database ✅

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│ SMART AGRI CITY - DATA PIPELINE                                         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  [IoT Simulator] (Python)                                              │
│  - ZoneSimulator class                                                 │
│  - Random data generation (Gaussian + state machine)                   │
│  - Print to stdout + MQTT publish                                      │
│         ↓                                                              │
│  [MQTT Broker] (mosquitto:1883)                                        │
│  - agri/zone-a/sensor (JSON)                                           │
│  - agri/zone-a/alert (JSON)                                            │
│         ↓                                                              │
│  [Message Subscribers]                                                │
│  ├─ Express Gateway (subscribe & log? forward?)                        │
│  ├─ PHP Crop Service (alert processor)                                │
│  ├─ Python ML Service (prediction engine)                             │
│  └─ Monitoring Service (dashboard data)                               │
│         ↓                                                              │
│  [MySQL Database]                                                      │
│  - irr_sensor_readings (time-series)                                   │
│  - irr_irrigation_logs (automation logs)                               │
│  - crp_alerts (alert history)                                          │
│         ↓                                                              │
│  [API & Dashboard]                                                      │
│  - Express Gateway API                                                │
│  - PHP Web UI                                                          │
│  - Grafana Monitoring                                                  │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

