# Panduan End-to-End Testing Manual — Smart AgriCity Platform

> **Platform:** Smart AgriCity Microservices  
> **Skenario:** S1 – S6 (IoT Ingestion, Auth, ML Prediction, Auto-Irrigation, Monitoring, Kubernetes)  
> **Tools yang dibutuhkan:** `curl`, `docker`, browser, opsional: Postman, MQTT Explorer  

---

## Daftar Isi

1. [Prasyarat & Persiapan](#1-prasyarat--persiapan)
2. [Referensi Cepat: Port & URL Layanan](#2-referensi-cepat-port--url-layanan)
3. [Kredensial & Data Uji](#3-kredensial--data-uji)
4. [S1 — IoT Data Ingestion Pipeline](#skenario-s1--iot-data-ingestion-pipeline)
5. [S2 — Login Petani & Catat Panen (OAuth2 + JWT)](#skenario-s2--login-petani--catat-panen-oauth2--jwt)
6. [S3 — ML Real-time Prediction](#skenario-s3--ml-real-time-prediction)
7. [S4 — Irigasi Otomatis (ML → RabbitMQ → MQTT)](#skenario-s4--irigasi-otomatis-ml--rabbitmq--mqtt)
8. [S5 — Monitoring & Observability](#skenario-s5--monitoring--observability)
9. [S6 — Kubernetes Deployment](#skenario-s6--kubernetes-deployment)
10. [Ringkasan Checklist](#ringkasan-checklist)
11. [Troubleshooting Umum](#troubleshooting-umum)

---

## 1. Prasyarat & Persiapan

### 1.1 Perangkat Lunak yang Diperlukan

| Tool | Versi Minimum | Kegunaan |
|------|---------------|----------|
| Docker Desktop | 24.x | Menjalankan semua container |
| Docker Compose | 2.x (V2) | Orchestrasi multi-container |
| `curl` | — | API testing via terminal |
| Browser | Chrome/Firefox | Grafana, Node-RED, phpMyAdmin |
| `kubectl` | 1.28+ | S6 Kubernetes (opsional) |
| Minikube | 1.32+ | S6 local cluster (opsional) |

### 1.2 Menjalankan Stack Lengkap

Buka terminal di root direktori proyek, lalu jalankan:

```bash
# Build dan start semua container (pertama kali / setelah perubahan)
docker compose up --build -d

# Atau tanpa rebuild (jika image sudah ada)
docker compose up -d
```

Tunggu sekitar **60–90 detik** sampai semua container selesai inisialisasi, terutama MySQL.

### 1.3 Verifikasi Semua Container Berjalan

```bash
docker ps --filter "name=agri-" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

**Container yang diharapkan aktif (minimal 12):**

| Container | Status |
|-----------|--------|
| `agri-gateway` | Up (healthy) |
| `agri-oauth-server` | Up (healthy) |
| `agri-php-farmer` | Up (healthy) |
| `agri-php-crop` | Up (healthy) |
| `agri-php-irrigation` | Up (healthy) |
| `agri-python-ml` | Up (healthy) |
| `agri-mysql` | Up (healthy) |
| `agri-rabbitmq` | Up (healthy) |
| `agri-mosquitto` | Up (healthy) |
| `agri-nodered` | Up |
| `agri-iot-simulator` | Up |
| `agri-prometheus` | Up |
| `agri-grafana` | Up |
| `agri-phpmyadmin` | Up |

### 1.4 Cek Health Semua Layanan Sekaligus

```bash
for svc in \
  "API Gateway:http://localhost:3000/health" \
  "OAuth Server:http://localhost:3002/health" \
  "PHP Farmer:http://localhost:8000/health" \
  "PHP Crop:http://localhost:8001/health" \
  "PHP Irrigation:http://localhost:8002/health" \
  "Python ML:http://localhost:5000/health" \
  "Grafana:http://localhost:3001/api/health" \
  "Prometheus:http://localhost:9090/-/healthy" \
  "Node-RED:http://localhost:1880/"; do
  name="${svc%%:*}"; url="${svc#*:}"
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$url")
  echo "$code  $name  ($url)"
done
```

**Hasil yang diharapkan:** semua mengembalikan `200` atau `204`.

---

## 2. Referensi Cepat: Port & URL Layanan

| Layanan | Port | URL | Keterangan |
|---------|------|-----|------------|
| **API Gateway** | 3000 | `http://localhost:3000` | Entry point utama |
| **OAuth Server** | 3002 | `http://localhost:3002` | Token & auth |
| **PHP Farmer** | 8000 | `http://localhost:8000` | Data petani/panen |
| **PHP Crop** | 8001 | `http://localhost:8001` | Jadwal tanam & alert |
| **PHP Irrigation** | 8002 | `http://localhost:8002` | Sensor & irigasi |
| **Python ML** | 5000 | `http://localhost:5000` | Prediksi ML |
| **Grafana** | 3001 | `http://localhost:3001` | Dashboard monitoring |
| **Prometheus** | 9090 | `http://localhost:9090` | Metrics collector |
| **Node-RED** | 1880 | `http://localhost:1880` | IoT flow automation |
| **RabbitMQ UI** | 15672 | `http://localhost:15672` | Message queue UI |
| **phpMyAdmin** | 8080 | `http://localhost:8080` | Database UI |
| **MQTT Broker** | 1883 | `mqtt://localhost:1883` | MQTT broker |

---

## 3. Kredensial & Data Uji

### 3.1 Akun Pengguna (dari seed data)

| Email | Password | Role | ID |
|-------|----------|------|----|
| `farmer@agri.com` | `password123` | petani | 1 (Ahmad Subagyo) |
| `officer@agri.com` | `password123` | petugas | 2 (Budi Wibowo) |

### 3.2 OAuth Clients

| Client ID | Client Secret | Grant Type | Digunakan oleh |
|-----------|---------------|------------|----------------|
| `web-app` | `web_secret_123` | password, refresh_token | Frontend / pengujian manual |
| `iot-device` | `iot_secret_456` | client_credentials | Node-RED & IoT devices |

### 3.3 Akses Layanan Pendukung

| Layanan | Username | Password |
|---------|----------|----------|
| RabbitMQ Management UI | `guest` | `guest` |
| Grafana | `admin` | `admin` |
| phpMyAdmin / MySQL | `root` | `secret_db_password_change_me` |

### 3.4 Zona Irigasi (dari seed data)

| ID | Nama | Area (ha) |
|----|------|-----------|
| 1 | Kecamatan Jalancagak | 150.50 |
| 2 | Kecamatan Ciater | 220.10 |
| 3 | Kecamatan Kasomalang | 128.80 |
| 4 | Kecamatan Tanjungsiang | 182.20 |
| 5 | Zona Utara - Padi | 15.00 |
| 6 | Zona Selatan - Hortikultura | 10.00 |

---

## Skenario S1 — IoT Data Ingestion Pipeline

**Tujuan:** Memverifikasi alur data dari sensor IoT (MQTT) melewati Node-RED → Gateway → Database → RabbitMQ.

**Alur lengkap:**
```
IoT Simulator → MQTT (agri/+/sensor) → Node-RED Flow 1 → OAuth token 
→ POST /iot/sensor (Gateway) → PHP Irrigation → MySQL (irr_sensor_readings) 
→ RabbitMQ (sensor.data queue)
```

---

### S1.1 — Verifikasi IoT Simulator Berjalan

```bash
docker inspect --format='{{.State.Status}}' agri-iot-simulator
```

**Hasil yang diharapkan:** `running`

Lihat log simulator untuk melihat data yang dikirim:

```bash
docker logs agri-iot-simulator --tail 20
```

**Hasil yang diharapkan:** Terlihat log pengiriman data MQTT ke topik `agri/zona1/sensor`, `agri/zona2/sensor`, dst. setiap beberapa detik.

---

### S1.2 — Verifikasi Mosquitto MQTT Broker Sehat

```bash
docker inspect --format='{{.State.Health.Status}}' agri-mosquitto
```

**Hasil yang diharapkan:** `healthy`

---

### S1.3 — Verifikasi Node-RED Aktif dan Flow Berjalan

1. Buka browser ke **http://localhost:1880**
2. Pastikan halaman Node-RED terbuka
3. Klik tab **"Flow 1 - Sensor Ingestion"**
4. Pastikan node-node terhubung dan tidak ada node berwarna merah (error)
5. Node `Subscribe agri/+/sensor` harus menampilkan status **"connected"**

Verifikasi via curl:

```bash
curl -s http://localhost:1880/flows | head -c 200
```

**Hasil yang diharapkan:** JSON response berisi daftar flows Node-RED.

---

### S1.4 — Simulasi Manual Pengiriman Data Sensor via IoT Client

Langkah ini mensimulasikan apa yang dilakukan IoT device: mendapatkan token lalu POST data sensor.

**Langkah 1: Dapatkan token IoT (client_credentials)**

```bash
IOT_TOKEN=$(curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials&client_id=iot-device&client_secret=iot_secret_456" \
  | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

echo "IOT Token: $IOT_TOKEN"
```

**Hasil yang diharapkan:** Token panjang berformat JWT tercetak.

**Langkah 2: Kirim data sensor melalui Gateway**

```bash
curl -s -X POST "http://localhost:3000/iot/sensor" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $IOT_TOKEN" \
  -d '{
    "zone_id": 1,
    "moisture": 42.5,
    "temperature": 28.1,
    "ph": 6.8,
    "nitrogen": 75,
    "phosphorus": 35,
    "potassium": 55,
    "air_temp": 28.1,
    "air_humidity": 68.0,
    "light_lux": 5000.0
  }'
```

**Hasil yang diharapkan:** HTTP 200 atau 201 dengan body JSON berisi `"status": "success"` atau data yang tersimpan.

---

### S1.5 — Verifikasi Data Tersimpan di Database

```bash
docker exec agri-mysql mysql -u root -psecret_db_password_change_me \
  -e "SELECT id, zone_id, moisture, temperature, ph, recorded_at FROM agriCity.irr_sensor_readings ORDER BY id DESC LIMIT 5;"
```

**Hasil yang diharapkan:** Tabel menampilkan beberapa baris data sensor dengan `recorded_at` terbaru.

Alternatif via browser: buka **http://localhost:8080** (phpMyAdmin), login dengan `root` / `secret_db_password_change_me`, pilih database `agriCity`, tabel `irr_sensor_readings`.

---

### S1.6 — Verifikasi RabbitMQ Queue Aktif

1. Buka browser ke **http://localhost:15672**
2. Login dengan `guest` / `guest`
3. Klik tab **"Queues"**
4. Pastikan queue `sensor.data` (atau serupa) muncul dalam daftar

Atau via curl:

```bash
curl -s -u guest:guest http://localhost:15672/api/queues \
  | grep -o '"name":"[^"]*"' | cut -d'"' -f4
```

**Hasil yang diharapkan:** Muncul nama queue seperti `sensor.data`, `iot.valve`, `alert.pest`.

---

### ✅ Kriteria Keberhasilan S1

- [ ] IoT simulator berstatus `running`
- [ ] Mosquitto MQTT broker berstatus `healthy`
- [ ] Node-RED dapat diakses di port 1880
- [ ] POST `/iot/sensor` via Gateway mengembalikan HTTP 200/201
- [ ] Data sensor terlihat di tabel `irr_sensor_readings`
- [ ] Queue RabbitMQ terbentuk

---

## Skenario S2 — Login Petani & Catat Panen (OAuth2 + JWT)

**Tujuan:** Memverifikasi alur autentikasi OAuth2 password grant, validasi token via introspect, akses API terproteksi, dan pencabutan token.

**Alur lengkap:**
```
POST /oauth/token (password grant) → access_token + refresh_token
→ GET /api/farmers (Bearer token) → Gateway introspect → PHP Farmer
→ POST /api/harvests → data tersimpan di frm_harvests
→ POST /oauth/revoke → token dicabut
→ GET /api/farmers (token lama) → 401/403 ditolak
```

---

### S2.1 — Mendapatkan Access Token (Login)

```bash
RESPONSE=$(curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&username=farmer@agri.com&password=password123&client_id=web-app&client_secret=web_secret_123")

# Format output (pilih salah satu):
# Opsi 1: Gunakan jq (jika tersedia)
echo $RESPONSE | jq .

# Opsi 2: Raw output (tanpa formatting)
echo $RESPONSE

# Opsi 3: Extract access_token saja (via grep)
ACCESS_TOKEN=$(echo $RESPONSE | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
echo "Access Token: $ACCESS_TOKEN"
```

**Hasil yang diharapkan:** JSON berisi:
```json
{
  "access_token": "eyJ...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "...",
  "scope": "..."
}
```

Simpan token untuk langkah selanjutnya:

```bash
ACCESS_TOKEN=$(echo $RESPONSE | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
echo "Token: $ACCESS_TOKEN"
```

---

### S2.2 — Validasi Token via Introspect

```bash
curl -s -X POST "http://localhost:3002/oauth/introspect" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "token=$ACCESS_TOKEN"
```

**Hasil yang diharapkan:** Raw JSON response
```json
{"active":true,"sub":"1","email":"farmer@agri.com","role":"petani","exp":1234567890}
```

Verifikasi bahwa respon berisi `"active":true`.

---

### S2.3 — Akses API Terproteksi: List Petani

```bash
curl -s -X GET "http://localhost:3000/api/farmers" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

**Hasil yang diharapkan:** HTTP 200 dengan daftar petani dalam format JSON (`"status": "success"`, array data farmers).

---

### S2.4 — Coba Akses Tanpa Token (Harus Ditolak)

```bash
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" \
  "http://localhost:3000/api/farmers"
```

**Hasil yang diharapkan:** `HTTP Status: 401` (Unauthorized).

---

### S2.5 — Catat Data Panen Baru

```bash
curl -s -X POST "http://localhost:3000/api/harvests" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "land_id": 1,
    "crop_type": "Padi",
    "yield_ton": 5.2,
    "harvest_date": "2026-06-14",
    "notes": "Manual E2E Test - panen sukses"
  }'
```

**Hasil yang diharapkan:** HTTP 200 atau 201 dengan response body berisi data panen yang tersimpan.

---

### S2.6 — Verifikasi Data Panen Tersimpan di Database

```bash
docker exec agri-mysql mysql -u root -psecret_db_password_change_me \
  -e "SELECT id, land_id, crop_type, yield_ton, harvest_date, notes FROM agriCity.frm_harvests ORDER BY id DESC LIMIT 3;"
```

**Hasil yang diharapkan:** Baris panen baru dengan catatan "Manual E2E Test" terlihat.

---

### S2.7 — Refresh Token

```bash
REFRESH_TOKEN=$(echo $RESPONSE | grep -o '"refresh_token":"[^"]*"' | cut -d'"' -f4)

curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token&refresh_token=$REFRESH_TOKEN&client_id=web-app&client_secret=web_secret_123"
```

**Hasil yang diharapkan:** Token baru diterbitkan dengan `access_token` yang berbeda.

---

### S2.8 — Revoke Token & Verifikasi Penolakan

**Langkah 1: Buat token baru khusus untuk di-revoke**

```bash
REVOKE_RESP=$(curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&username=farmer@agri.com&password=password123&client_id=web-app&client_secret=web_secret_123")

REVOKE_TOKEN=$(echo $REVOKE_RESP | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
echo "Token to revoke: $REVOKE_TOKEN"
```

**Langkah 2: Revoke token tersebut**

```bash
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" \
  -X POST "http://localhost:3002/oauth/revoke" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "token=$REVOKE_TOKEN"
```

**Hasil yang diharapkan:** `HTTP Status: 200` atau `204`.

**Langkah 3: Coba akses API dengan token yang sudah direvoke**

```bash
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" \
  -H "Authorization: Bearer $REVOKE_TOKEN" \
  "http://localhost:3000/api/farmers"
```

**Hasil yang diharapkan:** `HTTP Status: 401` atau `403` — akses ditolak.

---

### ✅ Kriteria Keberhasilan S2

- [ ] POST `/oauth/token` mengembalikan `access_token` dan `refresh_token`
- [ ] POST `/oauth/introspect` mengembalikan `"active": true`
- [ ] GET `/api/farmers` tanpa token → HTTP 401
- [ ] GET `/api/farmers` dengan token valid → HTTP 200
- [ ] POST `/api/harvests` berhasil menyimpan data → HTTP 201
- [ ] Data panen terlihat di database
- [ ] POST `/oauth/token` dengan `grant_type=refresh_token` berhasil
- [ ] POST `/oauth/revoke` berhasil → HTTP 200/204
- [ ] Akses dengan token revoked → HTTP 401/403

---

## Skenario S3 — ML Real-time Prediction

**Tujuan:** Memverifikasi ketiga endpoint prediksi Machine Learning: prediksi hasil panen, deteksi hama, dan kalkulasi kebutuhan irigasi.

---

### Persiapan: Dapatkan Token

```bash
ACCESS_TOKEN=$(curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&username=farmer@agri.com&password=password123&client_id=web-app&client_secret=web_secret_123" \
  | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
```

---

### S3.1 — Cek Health ML Service

```bash
curl -s "http://localhost:5000/health"
```

**Hasil yang diharapkan:** Response JSON berisi:
- `"status": "ok"`
- `"models_loaded": true`
- `"models": ["yield", "pest", "irrigation"]`

> ⚠️ **Troubleshooting:** Jika `"models_loaded": false`, kemungkinan ada mismatch scikit-learn version.  
> **Solusi:** 
> ```bash
> # Rebuild ML service dengan scikit-learn pinned version
> docker compose up python-ml --build -d
> # Tunggu 30-60 detik untuk model loading, lalu cek lagi
> sleep 30 && curl -s "http://localhost:5000/health"
> ```

---

### S3.2 — Prediksi Hasil Panen (`/predict/yield`)

**Kondisi normal (hasil panen diharapkan "Normal"):**

```bash
curl -s -X POST "http://localhost:3000/predict/yield" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "avg_temp": 28.5,
    "rainfall": 1200,
    "soil_moisture": 65,
    "ph": 6.5,
    "nitrogen": 80,
    "phosphorus": 40,
    "potassium": 60,
    "area_ha": 2.0,
    "week_of_planting": 14
  }'
```

**Hasil yang diharapkan:** Response berisi `"predicted_yield_ton": 5.2`, `"yield_category": "Normal"`, `"estimated_harvest_days": 72`

Kategori yield: `"Rendah"` (< 4 ton/ha) | `"Normal"` (4–7.5 ton/ha) | `"Tinggi"` (≥ 7.5 ton/ha)

**Kondisi optimal (hasil panen diharapkan "Tinggi"):**

```bash
curl -s -X POST "http://localhost:3000/predict/yield" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "avg_temp": 26.0,
    "rainfall": 2000,
    "soil_moisture": 80,
    "ph": 6.8,
    "nitrogen": 150,
    "phosphorus": 80,
    "potassium": 120,
    "area_ha": 5.0,
    "week_of_planting": 10
  }'
```

---

### S3.3 — Deteksi Hama (`/predict/pest`)

**Kondisi normal (diharapkan "Sehat"):**

```bash
curl -s -X POST "http://localhost:3000/predict/pest" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "air_humidity": 75,
    "leaf_temp": 28.5,
    "soil_ph": 6.5,
    "chlorophyll": 42.0,
    "light_lux": 35000,
    "zone": "zona1"
  }'
```

**Hasil yang diharapkan:** Response berisi `"pest_category": "Sehat"` dan `"action_required": false`

**Kondisi rawan hama:**

```bash
curl -s -X POST "http://localhost:3000/predict/pest" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "air_humidity": 92,
    "leaf_temp": 33.0,
    "soil_ph": 5.2,
    "chlorophyll": 18.0,
    "light_lux": 8000,
    "zone": "zona2"
  }'
```

**Hasil yang diharapkan:** `"action_required": true` dengan `pest_category` berisi nama hama (misal `"Wereng"` atau `"Blast"`).

> **Catatan:** Zone yang valid: `zona1`, `zona2`, `zona3`, `zona4`. Menggunakan zone lain akan mengembalikan HTTP 422.

**Uji validasi zone invalid:**

```bash
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" \
  -X POST "http://localhost:3000/predict/pest" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{"air_humidity":75,"leaf_temp":28,"soil_ph":6.5,"chlorophyll":42,"light_lux":35000,"zone":"zone-x"}'
```

**Hasil yang diharapkan:** `HTTP Status: 422` (Unprocessable Entity).

---

### S3.4 — Kalkulasi Kebutuhan Irigasi (`/predict/irrigation`)

**Tanah cukup basah (diharapkan "Tidak Perlu"):**

```bash
curl -s -X POST "http://localhost:3000/predict/irrigation" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "soil_moisture": 65,
    "air_temp": 26,
    "rain_forecast": 20,
    "growth_phase": "vegetatif",
    "evapotranspiration": 3.0
  }'
```

**Hasil yang diharapkan:** Response berisi `"irrigation_urgency": "Tidak Perlu"` dan `water_needed_liters`

**Tanah kering kritis (diharapkan "Kritis" — akan memicu S4):**

```bash
curl -s -X POST "http://localhost:3000/predict/irrigation" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "soil_moisture": 20,
    "air_temp": 32,
    "rain_forecast": 0,
    "growth_phase": "vegetatif",
    "evapotranspiration": 6.0
  }'
```

**Hasil yang diharapkan:** Response berisi `"irrigation_urgency": "Kritis"` dan `water_needed_liters` > 2000

Tingkat urgensi: `"Tidak Perlu"` (moisture > 50%) | `"Segera"` (25–50%) | `"Kritis"` (< 25%)

Growth phase yang valid: `semai` | `vegetatif` | `generatif` | `panen`

---

### S3.5 — Uji via Direct ML Service (Bypass Gateway)

Untuk memastikan layanan ML sendiri bekerja tanpa gateway:

```bash
# Yield langsung ke Python ML
curl -s -X POST "http://localhost:5000/predict/yield" \
  -H "Content-Type: application/json" \
  -d '{"avg_temp":28.5,"rainfall":1200,"soil_moisture":65,"ph":6.5,"nitrogen":80,"phosphorus":40,"potassium":60,"area_ha":2.0,"week_of_planting":14}'
```

---

### ✅ Kriteria Keberhasilan S3

- [ ] ML service health check: `"models_loaded": true`
- [ ] POST `/predict/yield` mengembalikan `predicted_yield_ton` dan `yield_category`
- [ ] POST `/predict/pest` mengembalikan `pest_category` dan `action_required`
- [ ] POST `/predict/pest` dengan zone invalid → HTTP 422
- [ ] POST `/predict/irrigation` mengembalikan `water_needed_liters` dan `irrigation_urgency`
- [ ] Input moisture < 25 menghasilkan urgency `"Kritis"`
- [ ] Semua endpoint dapat diakses via Gateway dengan token valid

---

## Skenario S4 — Irigasi Otomatis (ML → RabbitMQ → MQTT)

**Tujuan:** Memverifikasi alur irigasi otomatis yang dipicu deteksi tanah kering oleh ML, command dikirim via RabbitMQ, dan Node-RED meneruskan ke MQTT valve.

**Alur lengkap:**
```
ML deteksi moisture < 25 → urgency "Kritis"
→ POST /api/irrigation/command (trigger_type: otomatis_ml)
→ PHP Irrigation simpan log + publish ke RabbitMQ iot.valve
→ Node-RED Flow 2 poll queue → publish MQTT agri/commands/valve/{zone}
→ Valve actuator menerima command
```

---

### S4.1 — Verifikasi Skenario Tanah Kering via ML

```bash
# Simpan token
ACCESS_TOKEN=$(curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&username=farmer@agri.com&password=password123&client_id=web-app&client_secret=web_secret_123" \
  | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

# Prediksi dengan tanah sangat kering
curl -s -X POST "http://localhost:5000/predict/irrigation" \
  -H "Content-Type: application/json" \
  -d '{
    "soil_moisture": 18,
    "air_temp": 35,
    "rain_forecast": 0,
    "growth_phase": "generatif",
    "evapotranspiration": 7.0
  }'
```

**Hasil yang diharapkan:** Response berisi `"irrigation_urgency": "Kritis"` dan `water_needed_liters` > 2000.

---

### S4.2 — Kirim Perintah Irigasi Start

```bash
curl -s -X POST "http://localhost:3000/api/irrigation/command" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "zone_id": 2,
    "action": "start",
    "trigger_type": "otomatis_ml"
  }'
```

**Hasil yang diharapkan:** HTTP 200 atau 201 dengan konfirmasi command diterima dan log irigasi dibuat.

---

### S4.3 — Verifikasi Log Irigasi Tersimpan di Database

```bash
docker exec agri-mysql mysql -u root -psecret_db_password_change_me \
  -e "SELECT id, zone_id, started_at, ended_at, trigger_type FROM agriCity.irr_irrigation_logs ORDER BY id DESC LIMIT 5;"
```

**Hasil yang diharapkan:** Baris baru dengan `trigger_type = 'otomatis_ml'` dan `started_at` terbaru.

---

### S4.4 — Verifikasi RabbitMQ Queue `iot.valve`

1. Buka **http://localhost:15672** → login `guest/guest`
2. Klik tab **"Queues"**
3. Cari queue bernama `iot.valve`
4. Klik nama queue → lihat **"Get messages"** untuk melihat pesan pending

Atau via curl:

```bash
curl -s -u guest:guest "http://localhost:15672/api/queues/%2F/iot.valve"
```

**Hasil yang diharapkan:** Response JSON berisi info queue `iot.valve` dengan field `messages_ready` >= 0.

---

### S4.5 — Verifikasi Node-RED Flow 2 Memproses Command

1. Buka **http://localhost:1880**
2. Klik tab **"Flow 2 - Irrigation Valve Command"**
3. Perhatikan debug output di panel kanan jika ada pesan masuk dari RabbitMQ
4. Node `Subscribe RabbitMQ iot.valve` harus terhubung (tidak ada error merah)

---

### S4.6 — Hentikan Irigasi (Stop Command)

```bash
curl -s -X POST "http://localhost:3000/api/irrigation/command" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "zone_id": 2,
    "action": "stop",
    "trigger_type": "otomatis_ml"
  }'
```

**Hasil yang diharapkan:** HTTP 200 dengan log irigasi diperbarui (`ended_at` terisi).

---

### S4.7 — Lihat Riwayat Log Irigasi

```bash
curl -s "http://localhost:3000/api/irrigation/logs" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

---

### S4.8 — Uji Irigasi Manual (trigger_type: manual)

```bash
curl -s -X POST "http://localhost:3000/api/irrigation/command" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{
    "zone_id": 1,
    "action": "start",
    "trigger_type": "manual"
  }'
```

**Hentikan setelahnya:**

```bash
curl -s -X POST "http://localhost:3000/api/irrigation/command" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -d '{"zone_id": 1, "action": "stop", "trigger_type": "manual"}'
```

---

### ✅ Kriteria Keberhasilan S4

- [ ] ML prediksi dengan moisture < 25 menghasilkan urgency `"Kritis"`
- [ ] POST `/api/irrigation/command` dengan `action: start` → HTTP 200/201
- [ ] Log irigasi tersimpan di `irr_irrigation_logs` dengan `trigger_type = 'otomatis_ml'`
- [ ] Queue `iot.valve` di RabbitMQ aktif
- [ ] Node-RED Flow 2 terhubung ke RabbitMQ dan MQTT
- [ ] POST `/api/irrigation/command` dengan `action: stop` memperbarui `ended_at`
- [ ] GET `/api/irrigation/logs` menampilkan riwayat lengkap

---

## Skenario S5 — Monitoring & Observability

**Tujuan:** Memverifikasi sistem monitoring Prometheus (scraping metrics) dan Grafana (dashboard visualisasi).

---

### S5.1 — Cek Health Semua Layanan via Gateway

```bash
curl -s "http://localhost:3000/health"
```

**Hasil yang diharapkan:** Response JSON berisi status semua upstream services (`"status": "ok"`).

---

### S5.2 — Verifikasi Prometheus Scraping Targets

1. Buka browser ke **http://localhost:9090**
2. Klik menu **Status → Targets**
3. Semua target harus berstatus **UP** (hijau)
4. Target yang diharapkan: Gateway, OAuth Server, PHP Farmer, PHP Crop, PHP Irrigation, Python ML

Atau via API:

```bash
curl -s "http://localhost:9090/api/v1/targets"
```

**Hasil yang diharapkan:** JSON response dengan semua target health `"up"`.

---

### S5.3 — Verifikasi Metrics Endpoint Setiap Layanan

```bash
# Gateway metrics
curl -s -o /dev/null -w "Gateway /metrics: HTTP %{http_code}\n" \
  "http://localhost:3000/metrics"

# PHP Farmer
curl -s -o /dev/null -w "PHP Farmer /metrics: HTTP %{http_code}\n" \
  "http://localhost:8000/metrics"

# PHP Crop
curl -s -o /dev/null -w "PHP Crop /metrics: HTTP %{http_code}\n" \
  "http://localhost:8001/metrics"

# PHP Irrigation
curl -s -o /dev/null -w "PHP Irrigation /metrics: HTTP %{http_code}\n" \
  "http://localhost:8002/metrics"

# Python ML
curl -s -o /dev/null -w "Python ML /metrics: HTTP %{http_code}\n" \
  "http://localhost:5000/metrics"
```

**Hasil yang diharapkan:** Semua mengembalikan `HTTP 200`.

Lihat contoh output metrics (format Prometheus):

```bash
curl -s "http://localhost:3000/metrics" | grep -E "^(http_requests|process_cpu|nodejs)" | head -10
```

---

### S5.4 — Jalankan Beberapa Request untuk Menghasilkan Data Metrics

```bash
ACCESS_TOKEN=$(curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&username=farmer@agri.com&password=password123&client_id=web-app&client_secret=web_secret_123" \
  | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

# Generate beberapa request
for i in {1..5}; do
  curl -s -o /dev/null "http://localhost:3000/api/farmers" \
    -H "Authorization: Bearer $ACCESS_TOKEN"
  curl -s -o /dev/null "http://localhost:8000/health"
  curl -s -o /dev/null "http://localhost:8001/health"
  curl -s -o /dev/null "http://localhost:8002/health"
done
echo "5 request cycles completed"
```

---

### S5.5 — Verifikasi Data di Prometheus Query

1. Buka **http://localhost:9090**
2. Di kolom query, ketik: `http_requests_total` lalu klik **Execute**
3. Pastikan ada hasil query dengan label service yang berbeda
4. Coba juga query: `process_cpu_seconds_total`

---

### S5.6 — Verifikasi Grafana Dashboard

1. Buka browser ke **http://localhost:3001**
2. Login dengan `admin` / `admin`
3. Klik **Dashboards** di sidebar kiri
4. Cari dashboard **"Smart AgriCity"** atau **"smart-agri-city"**
5. Buka dashboard tersebut
6. Pastikan panel-panel berikut tampil data:
   - **Soil Moisture per Zone** — moisture sensor tiap zona
   - **Pest Alert Count** — jumlah alert hama
   - **Irrigation Volume** — volume air irigasi

**Verifikasi Grafana API:**

```bash
curl -s "http://localhost:3001/api/health"
```

**Hasil yang diharapkan:** Response JSON berisi `"database": "ok"`

**Lihat daftar dashboard via API:**

```bash
curl -s -u admin:admin "http://localhost:3001/api/search?type=dash-db"
```

---

### S5.7 — Verifikasi Prometheus Metrics untuk ML Prediction & Crop Alerts

Setelah menjalankan beberapa prediksi di S3 dan S4, cek apakah Prometheus mencatat metrics:

1. Buka **http://localhost:9090**
2. Query metrics yang tersedia:

**ML Predictions:**
```
predicted_yield_ton
```
Hasil yang diharapkan: Gauge metric dengan label `instance="python-ml:5000"`, `job="python-ml"`, `service="python-ml"`

**Crop Alerts (Pest Detection):**
```
crp_pest_alerts_total
```
Hasil yang diharapkan: Counter metric dengan berbagai label `pest_type`:
- `pest_type="high_temp"` — Alert temperatur tinggi
- `pest_type="extreme_ph"` — Alert pH ekstrem
- `pest_type="pest_outbreak"` — Alert ledakan hama
- `pest_type="low_moisture"` — Alert kelembaban rendah
- `pest_type="Blast"` — Alert penyakit Blast
- `pest_type="Wereng"` — Alert hama Wereng

**Contoh query dan hasil:**

```
Query: crp_pest_alerts_total{pest_type="Wereng"}
Result: 1
```

Nilai counter akan terus bertambah setiap ada alert baru untuk hama Wereng.

**Rate of alerts (Wereng per menit):**
```
rate(crp_pest_alerts_total{pest_type="Wereng"}[1m])
```

Ini menunjukkan berapa banyak alert Wereng per menit dalam window terakhir.

---

### S5.8 — Uji Rate Limiter via Metrics

Kirim banyak request dan verifikasi 429 di Prometheus:

```bash
# Kirim request berulang ke health (tanpa auth) untuk trigger rate limit
for i in {1..110}; do
  curl -s -o /dev/null -w "%{http_code} " "http://localhost:3000/health"
done
echo ""
```

**Hasil yang diharapkan:** Setelah sekitar 100 request, muncul `429` (rate limit aktif per 15 menit per IP).

Di Prometheus, query: `rate_limit_exceeded_total` atau lihat di Grafana.

---

### ✅ Kriteria Keberhasilan S5

- [ ] GET `/health` semua layanan → HTTP 200
- [ ] Tidak ada container `unhealthy` atau `exited`
- [ ] Total container aktif ≥ 12
- [ ] Prometheus: semua target berstatus **UP**
- [ ] Semua layanan ekspor `/metrics` → HTTP 200
- [ ] Grafana dapat diakses → `"database": "ok"`
- [ ] Dashboard SmartAgriCity tampil di Grafana
- [ ] Rate limiter aktif (request ke-101 → HTTP 429)

---

## Skenario S6 — Kubernetes Deployment

**Tujuan:** Memverifikasi deployment ke cluster Kubernetes (Minikube), termasuk namespace, pods, HPA auto-scaling, dan Ingress routing.

> ⚠️ **Prasyarat:** Skenario ini memerlukan Minikube dan kubectl yang terinstall.  
> Jika tidak tersedia, tandai sebagai **N/A** dan skip ke ringkasan.

---

### S6.1 — Persiapan: Start Minikube

```bash
# Mulai Minikube dengan driver Docker
minikube start --driver=docker --memory=4096 --cpus=2

# Verifikasi
kubectl version --client
kubectl cluster-info
```

**Hasil yang diharapkan:** Cluster info menampilkan control plane URL.

---

### S6.2 — Buat Namespace dan Apply Secrets

```bash
# Buat namespace
kubectl apply -f k8s/namespace.yaml

# Verifikasi namespace
kubectl get namespace agricity
```

**Hasil yang diharapkan:** `agricity` berstatus `Active`.

**Setup secrets (copy dari example):**

```bash
# Edit k8s/secrets.yaml terlebih dahulu dengan nilai yang sesuai
# Kemudian apply
kubectl apply -f k8s/secrets.yaml -n agricity
kubectl apply -f k8s/configmap.yaml -n agricity
```

---

### S6.3 — Deploy Semua Komponen

```bash
# Apply semua manifest sekaligus dengan kustomization
kubectl apply -k k8s/ -n agricity

# Atau apply satu per satu
kubectl apply -f k8s/mysql-statefulset.yaml -n agricity
kubectl apply -f k8s/rabbitmq-deployment.yaml -n agricity
kubectl apply -f k8s/oauth-server-deployment.yaml -n agricity
kubectl apply -f k8s/php-deployments.yaml -n agricity
kubectl apply -f k8s/python-ml-deployment.yaml -n agricity
kubectl apply -f k8s/gateway-deployment.yaml -n agricity
```

---

### S6.4 — Monitor Status Pods

```bash
# Watch pods sampai semua Running
kubectl get pods -n agricity -w
```

Tunggu sampai semua pod berstatus `Running` dan kolom `READY` menunjukkan `1/1` (atau `X/X`).

```bash
# Cek detail (tanpa watch)
kubectl get pods -n agricity
```

**Hasil yang diharapkan:**

```
NAME                              READY   STATUS    RESTARTS   AGE
agri-gateway-xxx-xxx              1/1     Running   0          2m
agri-oauth-server-xxx-xxx         1/1     Running   0          2m
agri-php-farmer-xxx-xxx           1/1     Running   0          2m
agri-php-crop-xxx-xxx             1/1     Running   0          2m
agri-php-irrigation-xxx-xxx       1/1     Running   0          2m
agri-python-ml-xxx-xxx            1/1     Running   0          2m
agri-mysql-0                      1/1     Running   0          3m
agri-rabbitmq-xxx-xxx             1/1     Running   0          3m
```

---

### S6.5 — Verifikasi HPA (Horizontal Pod Autoscaler)

```bash
kubectl get hpa -n agricity
```

**Hasil yang diharapkan:**

```
NAME              REFERENCE                  TARGETS   MINPODS   MAXPODS   REPLICAS
agri-gateway-hpa  Deployment/agri-gateway    5%/80%    1         5         1
php-farmer-hpa    Deployment/agri-php-farmer 3%/70%    1         3         1
```

Lihat detail HPA:

```bash
kubectl describe hpa -n agricity
```

---

### S6.6 — Verifikasi Ingress

```bash
kubectl get ingress -n agricity
```

**Hasil yang diharapkan:** Ingress terdaftar dengan host dan address.

```bash
kubectl describe ingress -n agricity
```

---

### S6.7 — Akses via Kubernetes Service

**⚠️ Note untuk Windows:** Minikube di Windows (Docker driver) tidak accessible via direct IP dari host. **Port-forward adalah solusi yang harus digunakan.**

**Setup Port-Forward untuk semua services:**

Jalankan command di bawah ini di terminal (akan berjalan di background):

```bash
# Jalankan semua port-forward sekaligus (recommended)
kubectl port-forward svc/gateway-service 3000:3000 -n agricity &
kubectl port-forward svc/oauth-server-service 3002:3002 -n agricity &
kubectl port-forward svc/farmer-service 8000:8000 -n agricity &
kubectl port-forward svc/crop-service 8001:8001 -n agricity &
kubectl port-forward svc/irrigation-service 8002:8002 -n agricity &
kubectl port-forward svc/python-ml-service 5000:5000 -n agricity &
```

**Test Gateway:**

```bash
curl http://localhost:3000/health
```

**Hasil yang diharapkan:**
```json
{
  "status": "ok",
  "service": "api-gateway",
  "upstreams": [...]
}
```

---

**Accessing individual services via localhost:**

| Service | Port | URL |
|---------|------|-----|
| API Gateway | 3000 | `http://localhost:3000` |
| OAuth Server | 3002 | `http://localhost:3002` |
| PHP Farmer | 8000 | `http://localhost:8000` |
| PHP Crop | 8001 | `http://localhost:8001` |
| PHP Irrigation | 8002 | `http://localhost:8002` |
| Python ML | 5000 | `http://localhost:5000` |

---

**Note:** Jika Ingress domain access diperlukan (bukan untuk Windows testing):
```bash
# Linux/Mac only:
minikube tunnel
# Lalu akses via: http://agri.kelompok1.local/health (Linux/Mac)
```

---

### S6.8 — Verifikasi Istio (Advanced Traffic Management)

**Prasyarat:** Istio harus terinstall di cluster.

**Langkah 1: Install Istio menggunakan istioctl (versi stabil)**

Download dari GitHub (lebih reliable daripada download script):

```bash
# Windows: Download dari GitHub releases
curl -L https://github.com/istio/istio/releases/download/1.20.3/istio-1.20.3-win.zip -o istio.zip

# Extract
Expand-Archive istio.zip -DestinationPath .

# Navigate & install
cd istio-1.20.3
.\bin\istioctl.exe install --set profile=demo -y

# Atau gunakan versi Linux/Mac
curl -L https://github.com/istio/istio/releases/download/1.20.3/istio-1.20.3-linux-amd64.tar.gz | tar xz
cd istio-1.20.3
./bin/istioctl install --set profile=demo -y
```

**Jika download masih gagal, gunakan Helm (alternative):**

```bash
# Pastikan helm sudah terinstall
helm version

# Add Istio repo
helm repo add istio https://istio-release.storage.googleapis.com/charts
helm repo update

# Install Istio base CRDs
helm install istio-base istio/base -n istio-system --create-namespace

# Install Istio daemon
helm install istiod istio/istiod -n istio-system
```

---

**Langkah 2: Verifikasi instalasi Istio**

```bash
kubectl get pods -n istio-system
```

**Hasil yang diharapkan:**
```
NAME                      READY   STATUS    RESTARTS   AGE
istiod-xxx-xxx            1/1     Running   0          2m
istio-ingressgateway-xxx  1/1     Running   0          2m
```

Jika pod belum running, tunggu 1-2 menit dan cek lagi.

---

**Langkah 3: Enable Istio sidecar injection di namespace agricity**

```bash
kubectl label namespace agricity istio-injection=enabled --overwrite

# Restart pods agar sidecar ter-inject
kubectl rollout restart deployment -n agricity
kubectl rollout restart statefulset -n agricity

# Tunggu pods restart
kubectl get pods -n agricity -w
```

---

**Langkah 4: Apply Istio configs untuk AgriCity**

```bash
kubectl apply -f k8s/istio/ -n agricity
```

---

**Langkah 5: Verifikasi VirtualService**

```bash
kubectl get virtualservice -n agricity
```

**Hasil yang diharapkan:**
```
NAME                  HOSTS                      AGE
agricity-ingress-vs   [agri.kelompok1.local]     1m
farmer-service-vs     [farmer-service]           1m
crop-service-vs       [crop-service]             1m
irrigation-service-vs [irrigation-service]       1m
python-ml-vs          [python-ml]                1m
```

---

**Langkah 6: Verifikasi DestinationRule**

```bash
kubectl get destinationrule -n agricity
```

**Hasil yang diharapkan:**
```
NAME              HOST                       AGE
oauth-server-dr   oauth-server-service       1m
farmer-dr         farmer-service             1m
crop-dr           crop-service               1m
irrigation-dr     irrigation-service         1m
python-ml-dr      python-ml-service          1m
```

---

**Langkah 7: Verifikasi Peer Authentication (mTLS)**

```bash
kubectl get peerauthentication -n agricity
```

**Hasil yang diharapkan:**
```
NAME                          MODE      AGE
allow-gateway-to-services     STRICT    1m
```

---

**Troubleshooting Istio:**

```bash
# Cek Istio logs
kubectl logs -n istio-system -l app=istiod -f

# Cek sidecar di pods
kubectl get pods -n agricity -o jsonpath='{range .items[*]}{.metadata.name}{"\t"}{.spec.containers[*].name}{"\n"}{end}'

# Verifikasi mTLS aktif
kubectl exec -it <pod-name> -n agricity -c <container> -- curl -v http://farmer-service:8000/health
```

---

**Skip Istio jika gagal terinstall:**

Jika Istio tidak bisa diinstall, testing K8s dasar sudah cukup (deployment, service, HPA, ingress). Istio adalah advanced feature dan opsional untuk skenario testing dasar.

---

### S6.9 — Uji Fungsional API via K8s (opsional)

Gunakan port-forward untuk akses penuh:

```bash
# Forward semua service yang dibutuhkan
kubectl port-forward service/agri-gateway 3000:3000 -n agricity &
kubectl port-forward service/agri-oauth-server 3002:3002 -n agricity &

# Test login
curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&username=farmer@agri.com&password=password123&client_id=web-app&client_secret=web_secret_123"
```

---

### ✅ Kriteria Keberhasilan S6

- [ ] `kubectl version` berhasil
- [ ] `minikube start` sukses, cluster aktif
- [ ] Namespace `agricity` terbuat
- [ ] Semua pods berstatus `Running` dengan `READY = X/X`
- [ ] HPA terdaftar minimal untuk Gateway dan PHP services
- [ ] Ingress terdaftar dan dapat diakses
- [ ] API Gateway dapat merespons request via K8s

---

## Ringkasan Checklist

### Quick Reference: Semua Skenario

| # | Skenario | Command Kunci | Hasil yang Diharapkan |
|---|----------|---------------|----------------------|
| **S1** | IoT Ingestion | `POST /iot/sensor` dengan client_credentials | HTTP 200, data di `irr_sensor_readings` |
| **S2** | Login & Harvest | `POST /oauth/token` → `POST /api/harvests` | Token valid, data di `frm_harvests` |
| **S3** | ML Prediction | `POST /predict/yield` / `pest` / `irrigation` | Hasil prediksi dengan kategori |
| **S4** | Auto Irrigation | `POST /api/irrigation/command` | Log di DB, queue RabbitMQ aktif |
| **S5** | Monitoring | Prometheus targets UP, Grafana dashboard | Semua metrics terbaca |
| **S6** | Kubernetes | `kubectl get pods -n agricity` | Semua pods Running |

### Master Checklist

**Pra-Testing:**
- [ ] `docker compose up -d` berhasil, semua 12+ container aktif
- [ ] Semua health endpoint mengembalikan HTTP 200

**S1 — IoT:**
- [ ] Simulator berjalan (`docker ps`)
- [ ] Node-RED aktif di port 1880
- [ ] Mosquitto healthy
- [ ] Data sensor masuk ke database
- [ ] RabbitMQ queue aktif

**S2 — Auth:**
- [ ] Token OAuth2 diterbitkan
- [ ] Introspect token aktif
- [ ] API farmers accessible dengan token
- [ ] Tanpa token → 401
- [ ] Panen tersimpan di DB
- [ ] Revoke token berhasil
- [ ] Token revoked → 401/403

**S3 — ML:**
- [ ] ML service loaded (`models_loaded: true`)
- [ ] Yield prediction berhasil
- [ ] Pest detection berhasil
- [ ] Pest zone invalid → 422
- [ ] Irrigation prediction berhasil
- [ ] Moisture < 25 → urgency "Kritis"

**S4 — Irigasi:**
- [ ] ML deteksi moisture kritis
- [ ] Command start berhasil
- [ ] Log tersimpan di `irr_irrigation_logs`
- [ ] RabbitMQ `iot.valve` queue aktif
- [ ] Node-RED Flow 2 berjalan
- [ ] Command stop berhasil

**S5 — Monitoring:**
- [ ] Semua service health OK
- [ ] Tidak ada container unhealthy
- [ ] Prometheus targets semua UP
- [ ] Semua /metrics endpoint → 200
- [ ] Grafana accessible, database OK
- [ ] Dashboard menampilkan data
- [ ] Rate limiter aktif (429 setelah 100 req)

**S6 — Kubernetes:**
- [ ] kubectl tersedia
- [ ] Cluster aktif
- [ ] Namespace `agricity` ada
- [ ] Semua pods Running
- [ ] HPA terkonfigurasi
- [ ] Ingress tersedia

---

## Troubleshooting Umum

### Container tidak mau start

```bash
# Lihat log container spesifik
docker logs agri-mysql --tail 50
docker logs agri-gateway --tail 50

# Restart container bermasalah
docker restart agri-mysql

# Hard reset (hapus data volume)
docker compose down -v
docker compose up --build -d
```

### OAuth token gagal didapat

```bash
# Pastikan OAuth server jalan
curl -s "http://localhost:3002/health"

# Cek koneksi MySQL dari OAuth server
docker exec agri-oauth-server node -e "require('./src/config/db').getConnection().then(c=>{console.log('DB OK');c.release()}).catch(console.error)"
```

### Database tidak memiliki data seed

```bash
# Cek apakah seed berjalan
docker exec agri-mysql mysql -u root -psecret_db_password_change_me \
  -e "SELECT COUNT(*) as farmer_count FROM agriCity.frm_farmers;"

# Jalankan ulang seed manual jika perlu
docker exec -i agri-mysql mysql -u root -psecret_db_password_change_me < database/seed.sql
```

### Node-RED tidak connect ke MQTT/RabbitMQ

1. Buka **http://localhost:1880**
2. Klik ikon hamburger (≡) → **Manage palette** → pastikan semua node terinstall
3. Double-klik node broker → cek host dan port sudah benar (`agri-mosquitto:1883`, `agri-rabbitmq:5672`)
4. Klik **Deploy** untuk re-deploy flows

### ML Service mengembalikan 503

```bash
# Cek log ML service
docker logs agri-python-ml --tail 30

# ML membutuhkan waktu load model saat startup
# Tunggu 30-60 detik lalu coba lagi
```

### Rate limit sudah tercapai (semua request 429)

Rate limit reset setelah 15 menit. Tunggu atau restart gateway:

```bash
docker restart agri-gateway
```

### RabbitMQ queue tidak muncul

Queue hanya terbentuk setelah ada publisher pertama yang mengirim pesan. Jalankan:

```bash
# Kirim sensor data untuk trigger publisher
IOT_TOKEN=$(curl -s -X POST "http://localhost:3002/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials&client_id=iot-device&client_secret=iot_secret_456" \
  | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

curl -s -X POST "http://localhost:3000/iot/sensor" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $IOT_TOKEN" \
  -d '{"zone_id":1,"moisture":42.5,"temperature":28.1,"ph":6.8,"nitrogen":75,"phosphorus":35,"potassium":55}'
```

Kemudian cek kembali di RabbitMQ Management UI.

---

*Dokumentasi ini dibuat berdasarkan konfigurasi `docker-compose.yml`, `e2e_test.sh`, skema database, dan source code layanan Smart AgriCity Platform.*
