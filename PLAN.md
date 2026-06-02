# PLAN.md — Smart Agri City Integrated Platform

> **Mata Kuliah:** Pembangunan Perangkat Lunak Orientasi Berbasis Service  
> **Kelas:** Peminatan SE IF  
> **Tema Proyek:** Smart City berbasis Agrikultur  
> **Format:** Presentasi Kelompok (8 menit + 2–3 menit tanya jawab)

---

## Daftar Isi

1. [Deskripsi Proyek](#1-deskripsi-proyek)
2. [Tujuan & Capaian Pembelajaran](#2-tujuan--capaian-pembelajaran)
3. [Arsitektur Sistem](#3-arsitektur-sistem)
4. [Cakupan Teknologi per Pertemuan](#4-cakupan-teknologi-per-pertemuan)
5. [Spesifikasi Setiap Service](#5-spesifikasi-setiap-service)
6. [Model AI/ML yang Digunakan](#6-model-aiml-yang-digunakan)
7. [Dataset & Sumber Data](#7-dataset--sumber-data)
8. [Implementasi IoT Layer](#8-implementasi-iot-layer)
9. [Message Broker — RabbitMQ](#9-message-broker--rabbitmq)
10. [Docker — Containerisasi](#10-docker--containerisasi)
11. [Kubernetes — Orkestrasi](#11-kubernetes--orkestrasi)
12. [Spesifikasi API Endpoints](#12-spesifikasi-api-endpoints)
13. [Skema Database](#13-skema-database)
14. [Struktur Repository](#14-struktur-repository)
15. [Skenario Pengujian End-to-End](#15-skenario-pengujian-end-to-end)
16. [Kriteria Penilaian & Poin Bonus](#16-kriteria-penilaian--poin-bonus)
17. [Deliverables yang Dikumpulkan](#17-deliverables-yang-dikumpulkan)
18. [Pembagian Kerja Tim](#18-pembagian-kerja-tim)
19. [Aturan Keamanan Server](#19-aturan-keamanan-server)
20. [Timeline Pengerjaan](#20-timeline-pengerjaan)

---

## 1. Deskripsi Proyek

**Smart Agri City Integrated Platform** adalah sistem manajemen pertanian cerdas berbasis microservice yang mengintegrasikan seluruh teknologi yang dipelajari sepanjang semester. Sistem ini merupakan adaptasi tema Smart City ke domain agrikultur, mencakup:

- Jaringan sensor IoT di seluruh zona kebun/sawah (kelembaban tanah, suhu, pH, NPK, hama)
- Platform analitik berbasis AI/ML untuk prediksi hasil panen, deteksi hama, dan optimasi irigasi
- Dasbor monitoring real-time yang diakses oleh petani, petugas pertanian, dan pemangku kebijakan daerah

Seluruh service berjalan dalam kontainer Docker yang diorkestrasikan dengan Kubernetes, berkomunikasi melalui REST API, message broker (RabbitMQ), dan protokol IoT (MQTT). Proyek ini mencakup implementasi seluruh materi dari Pertemuan 1 hingga 13.

**Server & Akses yang Disediakan:**

```
SSH       : ssh -p 8989 mahasiswa@103.147.92.134
Username  : kelompok1 (sesuaikan nomor kelompok)
Port yang dialokasikan:
  Express.js Gateway : 3000   OAuth Server  : 3002
  PHP Farmer Service : 8000   PHP Crop Svc  : 8001
  PHP Irrigation Svc : 8002   Python ML     : 5000
  RabbitMQ UI        : 15672  Grafana       : 3001
  Prometheus         : 9090   Node-RED      : 1880
  MQTT Broker        : 1883
```

---

## 2. Tujuan & Capaian Pembelajaran

Setelah menyelesaikan tugas besar ini, mahasiswa mampu:

- Merancang arsitektur microservice lengkap dari nol hingga produksi dengan domain agrikultur
- Membangun dan mengintegrasikan minimal 4 service independen (Express.js, PHP x3, Python ML, IoT)
- Mengimplementasikan autentikasi JWT dan otorisasi OAuth 2.0 secara konsisten lintas service
- Membangun API Gateway yang menangani routing, rate limiting, load balancing, dan request logging
- Mengintegrasikan message broker RabbitMQ untuk komunikasi asinkron antar service
- Melatih model Machine Learning (yield predictor, pest classifier, irrigation optimizer) dan menyajikannya sebagai REST API dengan Python FastAPI
- Menghubungkan sensor pertanian IoT melalui protokol MQTT ke dalam ekosistem microservice
- Mengemas seluruh service ke dalam Docker container dengan Docker Compose
- Men-deploy sistem ke kluster Kubernetes dengan konfigurasi Deployment, Service, Ingress, HPA, ConfigMap, dan Secrets
- Mendokumentasikan sistem secara profesional agar dapat direproduksi oleh pihak lain

---

## 3. Arsitektur Sistem

### 3.1 Gambaran Arsitektur Keseluruhan

| Layer | Komponen | Teknologi | Port | Fungsi |
|---|---|---|---|---|
| IoT Layer | Sensor Gateway | Node-RED + Mosquitto MQTT | 1883/1880 | Terima data sensor kebun, publish ke broker |
| IoT Layer | IoT Device Simulator | Python script (paho-mqtt) | — | Simulasikan sensor suhu, kelembaban, pH, NPK, hama |
| Gateway Layer | API Gateway | Express.js + http-proxy-middleware | 3000 | Routing, JWT verify, rate limiting, load balancing |
| Gateway Layer | OAuth Server | Express.js + oauth2-server | 3002 | Issue & validate OAuth 2.0 access token |
| Service Layer | Farmer Service | PHP 8.2 MVC | 8000 | CRUD petani, lahan, riwayat panen |
| Service Layer | Crop Service | PHP 8.2 MVC | 8001 | Data tanaman, jadwal tanam, alert hama |
| Service Layer | Irrigation Service | PHP 8.2 MVC | 8002 | Kontrol irigasi otomatis, log sensor |
| ML Layer | Prediction Service | Python 3.11 + FastAPI | 5000 | Prediksi panen, klasifikasi hama, optimasi irigasi |
| Messaging | Message Broker | RabbitMQ 3.12 | 5672/15672 | Event-driven komunikasi antar service |
| Monitoring | Metrics & Dashboard | Prometheus + Grafana | 9090/3001 | Monitoring performa semua service |
| Infra | Container Runtime | Docker + Docker Compose | — | Packaging semua service |
| Infra | Orchestration | Kubernetes (kubectl) | — | Deployment, scaling, self-healing |

### 3.2 Alur Data Utama

Alur data dari sensor IoT hingga ke dasbor petani:

1. **Sensor IoT** (simulator) → publish data ke Mosquitto MQTT Broker dengan topik `agri/{zone}/{sensor_type}`
2. **Node-RED** subscribe MQTT → transformasi payload → POST ke API Gateway (`/iot/sensor`)
3. **API Gateway** verifikasi JWT/OAuth → forward ke Irrigation Service (PHP)
4. **Irrigation PHP Service** simpan data ke MySQL → publish event `sensor.new` ke RabbitMQ
5. **Python ML Service** subscribe RabbitMQ `sensor.new` → jalankan prediksi → publish `harvest.ready` atau `alert.pest`
6. **Crop PHP Service** consume `alert.pest` → buat alert di DB → notifikasi petani
7. **Farmer Dashboard** (Express aggregator) → GET data dari PHP + Python → return ke frontend
8. **Prometheus** scrape metrics dari semua service → **Grafana** tampilkan dashboard 5 panel agrikultur

### 3.3 Keterkaitan dengan Materi Per. 1–13

| Pertemuan | Topik | Implementasi di Proyek |
|---|---|---|
| Per. 1 | Monolith vs Microservice | Arsitektur dipecah menjadi 6+ service independen (Farmer, Crop, Irrigation, ML, Gateway, OAuth) |
| Per. 2 | SOA & Method Response | Setiap service ikuti kontrak REST API dengan response standar JSON |
| Per. 3 | REST API Database Service | PHP Farmer, Crop, Irrigation Service: CRUD + MySQL |
| Per. 4 | REST API PHP MVC | Ketiga PHP service gunakan pola MVC (Controller-Model-View JSON) |
| Per. 5 | JSON Web Token | Semua endpoint dilindungi JWT; issue di OAuth Server, verify di Gateway |
| Per. 6 | OAuth 2.0 | OAuth Server terbitkan access_token (password, client_credentials, refresh_token grant) |
| Per. 7 | API Gateway | Express Gateway: routing, JWT middleware, rate limiting, load balancing, logging |
| Per. 8 | UTS | (sudah berjalan) |
| Per. 9 | Message Broker RabbitMQ | PHP service publish event; Python ML service consume & proses |
| Per. 10 | Rate Limiting | Gateway terapkan rate limit per IP (100 req/15 menit) dan per token (500 req/jam) |
| Per. 11 | Python ML Service | FastAPI expose 3 model: yield predictor, pest classifier, irrigation optimizer |
| Per. 12 | Docker | Semua service dikemas dalam Docker image + Docker Compose orchestration |
| Per. 13 | Kubernetes | Deploy ke K8s cluster: Deployment, Service, Ingress, HPA, ConfigMap, Secrets |
| Per. 14 | IoT | Mosquitto MQTT + Node-RED + sensor simulator pertanian terintegrasi di pipeline |

---

## 4. Cakupan Teknologi per Pertemuan

### Per. 1-2 — Arsitektur Monolith vs Microservice, SOA

Arsitektur monolith (satu aplikasi PHP besar) dibandingkan dengan microservice yang diimplementasikan: setiap service (Farmer, Crop, Irrigation, ML) berdiri independen dengan database prefix masing-masing, port berbeda, dan dapat di-deploy secara terpisah. Setiap service mengikuti kontrak SOA dengan response JSON standar:

```json
{
  "status": "success",
  "code": 201,
  "data": { ... },
  "message": "Data berhasil disimpan",
  "timestamp": "2025-01-01T00:00:00.000Z",
  "service": "farmer-service"
}
```

### Per. 3-4 — REST API Database Service & PHP MVC

Tiga service PHP independen menggunakan arsitektur MVC murni (custom router, tanpa Laravel/Symfony):

- Request masuk ke **Controller** → business logic di **Model** → response di **View** (JSON)
- Validasi input di setiap endpoint menggunakan custom `Validator` class
- Gunakan **PDO** untuk koneksi MySQL (bukan mysqli)
- Publish event ke RabbitMQ setiap ada data signifikan baru
- Expose `GET /health` yang mengembalikan status DB connection

### Per. 5-6 — JWT & OAuth 2.0

Grant type yang diimplementasikan di OAuth Server:

| Grant Type | Digunakan Oleh | Deskripsi |
|---|---|---|
| `password` | Petani & petugas login via app | username + password → access_token + refresh_token |
| `client_credentials` | IoT device, service-to-service | client_id + client_secret → access_token |
| `refresh_token` | Semua client | Perpanjang sesi tanpa login ulang |

Endpoint OAuth:
- `POST /oauth/token` → issue token
- `POST /oauth/introspect` → validasi token (dipakai Gateway)
- `POST /oauth/revoke` → cabut token

### Per. 7 — API Gateway

Gateway adalah single entry point. Wajib mengimplementasikan:

- **JWT Middleware:** verifikasi token di setiap protected endpoint
- **OAuth 2.0 Introspection:** validasi access token dari OAuth server
- **Routing:** forward berdasarkan path prefix (`/api/farmers` → port 8000, `/api/crops` → port 8001, dst.)
- **Load Balancing:** round-robin ke multiple instance PHP jika dijalankan dengan replika
- **Rate Limiting:** 100 req/15 menit per IP; 500 req/jam per token
- **Request Logging:** timestamp, method, path, status, response time
- **Error Handling:** response standar 401, 403, 429, 502, 503
- **Health Aggregator:** `GET /health` mengembalikan status semua upstream service

### Per. 9 — RabbitMQ

Lihat [Section 9 — Message Broker](#9-message-broker--rabbitmq).

### Per. 10 — Rate Limiting & Throttling

```javascript
// src/middleware/rateLimit.js
const rateLimit = require('express-rate-limit');

const globalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 menit
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: { status: "error", code: 429, message: "Too many requests" },
});

const authLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 jam
  max: 500,
  keyGenerator: (req) => req.headers.authorization || req.ip,
});

module.exports = { globalLimiter, authLimiter };
```

### Per. 11 — Python ML Service

Lihat [Section 6 — Model AI/ML](#6-model-aiml-yang-digunakan).

### Per. 12 — Docker

Lihat [Section 10 — Docker](#10-docker--containerisasi).

### Per. 13 — Kubernetes

Lihat [Section 11 — Kubernetes](#11-kubernetes--orkestrasi).

---

## 5. Spesifikasi Setiap Service

### 5.1 API Gateway — Express.js (Port 3000)

```javascript
// src/index.js — struktur utama gateway
const express = require('express');
const { globalLimiter, authLimiter } = require('./middleware/rateLimit');
const jwtMiddleware = require('./middleware/jwt');
const { createProxyMiddleware } = require('http-proxy-middleware');
const logger = require('./middleware/logger');

const app = express();

app.use(logger);
app.use(globalLimiter);

// Health aggregator (tidak butuh auth)
app.get('/health', healthAggregator);

// IoT endpoint (pakai client_credentials OAuth)
app.use('/iot', oauthIntrospect, proxyTo(process.env.IRRIGATION_SERVICE_URL));

// Protected endpoints (JWT + auth rate limit)
app.use('/api/farmers',     authLimiter, jwtMiddleware, proxyTo(process.env.FARMER_SERVICE_URL));
app.use('/api/lands',       authLimiter, jwtMiddleware, proxyTo(process.env.FARMER_SERVICE_URL));
app.use('/api/harvests',    authLimiter, jwtMiddleware, proxyTo(process.env.FARMER_SERVICE_URL));
app.use('/api/crops',       authLimiter, jwtMiddleware, proxyTo(process.env.CROP_SERVICE_URL));
app.use('/api/alerts',      authLimiter, jwtMiddleware, proxyTo(process.env.CROP_SERVICE_URL));
app.use('/api/irrigation',  authLimiter, jwtMiddleware, proxyTo(process.env.IRRIGATION_SERVICE_URL));
app.use('/api/sensors',     authLimiter, jwtMiddleware, proxyTo(process.env.IRRIGATION_SERVICE_URL));
app.use('/predict',         authLimiter, jwtMiddleware, proxyTo(process.env.PYTHON_ML_URL));
app.use('/detect',          authLimiter, jwtMiddleware, proxyTo(process.env.PYTHON_ML_URL));
```

### 5.2 OAuth 2.0 Authorization Server (Port 3002)

Grant type yang diimplementasikan: `password`, `client_credentials`, `refresh_token`.

Endpoint:
- `POST /oauth/token` → issue token
- `POST /oauth/introspect` → validasi token (dipakai Gateway)
- `POST /oauth/revoke` → cabut token

### 5.3 PHP MVC Services

#### Farmer Service (Port 8000)

| Controller | Model | Fungsi Utama |
|---|---|---|
| FarmerController | Farmer | CRUD data petani, registrasi, profil |
| LandController | Land | CRUD lahan (koordinat, luas, jenis tanah) |
| HarvestController | Harvest | Catat dan query riwayat panen aktual |

Setiap controller wajib:
- Menerima request → validasi → proses di model → return response JSON standar
- Publish event ke RabbitMQ setiap ada data baru yang signifikan
- Expose `GET /health` yang mengembalikan status DB connection

#### Crop Service (Port 8001)

| Controller | Model | Fungsi Utama |
|---|---|---|
| CropController | CropSchedule | Jadwal tanam, fase tumbuh, rekomendasi tanaman |
| AlertController | Alert | CRUD alert hama/penyakit per zona |
| RecommendController | SoilCondition | Rekomendasi tanaman berdasarkan kondisi tanah |

#### Irrigation Service (Port 8002)

| Controller | Model | Fungsi Utama |
|---|---|---|
| SensorController | SensorReading | Simpan & query pembacaan sensor IoT |
| IrrigationController | IrrigationLog | Kontrol valve, log volume & durasi irigasi |
| ZoneController | Zone | Status real-time semua zona kebun |

**Contoh implementasi TrafficController yang diadaptasi ke IrrigationController:**

```php
<?php
// app/Controllers/IrrigationController.php
namespace App\Controllers;

use App\Models\SensorReading;
use App\Services\RabbitMQPublisher;
use App\Validators\SensorValidator;

class IrrigationController {
    private SensorReading $model;
    private RabbitMQPublisher $publisher;

    public function __construct() {
        $this->model     = new SensorReading();
        $this->publisher = new RabbitMQPublisher();
    }

    public function storeReading(array $data): array {
        $validated = SensorValidator::validate($data);
        $record    = $this->model->create($validated);

        // Publish ke RabbitMQ untuk dikonsumsi Python ML
        $this->publisher->publish('sensor.new', [
            'id'        => $record['id'],
            'zone'      => $record['zone_id'],
            'moisture'  => $record['moisture'],
            'ph'        => $record['ph'],
            'nitrogen'  => $record['nitrogen'],
            'timestamp' => $record['recorded_at'],
        ]);

        return ['status' => 'success', 'code' => 201, 'data' => $record];
    }
}
```

### 5.4 Python ML Service — FastAPI (Port 5000)

Lihat [Section 6](#6-model-aiml-yang-digunakan) untuk detail model dan kode implementasi.

**Endpoint wajib:**

```
GET  /health                   → Status service + daftar model terload
POST /predict/yield            → Prediksi hasil panen (ton/ha)
POST /predict/pest             → Klasifikasi hama/penyakit tanaman
POST /predict/irrigation       → Rekomendasi kebutuhan air
GET  /model/feature-importance → Bobot fitur ketiga model
POST /predict/batch            → Batch prediction (array input → array output)
```

---

## 6. Model AI/ML yang Digunakan

### 6.1 Ringkasan Ketiga Model

| Model | Task ML | Algoritma | Input Fitur | Output |
|---|---|---|---|---|
| Crop Yield Predictor | Regression | Random Forest Regressor | suhu, curah_hujan, kelembaban_tanah, ph, nitrogen, phosphorus, potassium, area_ha, minggu_tanam | `predicted_yield_ton`, `yield_category` |
| Pest & Disease Classifier | Multi-class Classification | Gradient Boosting Classifier | kelembaban_udara, suhu_daun, ph_tanah, klorofil, intensitas_cahaya, zona | kategori: `Sehat / Wereng / Blast / Tungro / Layu_Fusarium` |
| Irrigation Optimizer | Regression | Gradient Boosting Regressor | kelembaban_tanah, suhu_udara, ramalan_hujan, fase_tumbuh, evapotranspirasi | `water_needed_liters`, `irrigation_urgency` |

### 6.2 Training Script

```python
# train_models.py — latih ketiga model sekaligus
import pandas as pd, numpy as np, joblib
from sklearn.ensemble import (RandomForestRegressor,
                               GradientBoostingClassifier,
                               GradientBoostingRegressor)
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.model_selection import cross_val_score
from sklearn.metrics import r2_score, classification_report

# ── MODEL 1: Crop Yield Predictor ──────────────────────────────────────────
df_yield = pd.read_csv("data/crop_yield.csv")
YIELD_FEATS = ['avg_temp','rainfall','soil_moisture','ph',
               'nitrogen','phosphorus','potassium','area_ha','week_of_planting']

scaler_y = StandardScaler()
X_y = scaler_y.fit_transform(df_yield[YIELD_FEATS])
y_y = df_yield['yield_ton_per_ha']

mdl_y = RandomForestRegressor(n_estimators=200, max_depth=12, random_state=42)
mdl_y.fit(X_y, y_y)
cv_y = cross_val_score(mdl_y, X_y, y_y, cv=5, scoring='r2')
print(f"Yield R²     : {cv_y.mean():.4f} ± {cv_y.std():.4f}")

# ── MODEL 2: Pest & Disease Classifier ─────────────────────────────────────
df_pest = pd.read_csv("data/pest_disease.csv")
PEST_FEATS = ['air_humidity','leaf_temp','soil_ph',
              'chlorophyll','light_lux','zone_enc']

le_zone = LabelEncoder()
df_pest['zone_enc'] = le_zone.fit_transform(df_pest['zone'])
le_pest = LabelEncoder()
y_p = le_pest.fit_transform(df_pest['pest_category'])

scaler_p = StandardScaler()
X_p = scaler_p.fit_transform(df_pest[PEST_FEATS])

mdl_p = GradientBoostingClassifier(n_estimators=150, learning_rate=0.1,
                                    random_state=42)
mdl_p.fit(X_p, y_p)
cv_p = cross_val_score(mdl_p, X_p, y_p, cv=5, scoring='accuracy')
print(f"Pest Acc     : {cv_p.mean():.4f} ± {cv_p.std():.4f}")

# ── MODEL 3: Irrigation Optimizer ──────────────────────────────────────────
df_irr = pd.read_csv("data/irrigation_demand.csv")
IRR_FEATS = ['soil_moisture','air_temp','rain_forecast',
             'growth_phase_enc','evapotranspiration']

le_phase = LabelEncoder()
df_irr['growth_phase_enc'] = le_phase.fit_transform(df_irr['growth_phase'])
y_i = df_irr['water_needed_liters']

scaler_i = StandardScaler()
X_i = scaler_i.fit_transform(df_irr[IRR_FEATS])

mdl_i = GradientBoostingRegressor(n_estimators=150, learning_rate=0.05,
                                   random_state=42)
mdl_i.fit(X_i, y_i)
cv_i = cross_val_score(mdl_i, X_i, y_i, cv=5, scoring='r2')
print(f"Irrigation R²: {cv_i.mean():.4f} ± {cv_i.std():.4f}")

# ── SAVE ALL ────────────────────────────────────────────────────────────────
joblib.dump({
    'yield': {
        'model': mdl_y, 'scaler': scaler_y, 'features': YIELD_FEATS
    },
    'pest': {
        'model': mdl_p, 'scaler': scaler_p,
        'le_pest': le_pest, 'le_zone': le_zone, 'features': PEST_FEATS
    },
    'irrigation': {
        'model': mdl_i, 'scaler': scaler_i,
        'le_phase': le_phase, 'features': IRR_FEATS
    },
}, 'models/agri_models.pkl')
print("All models saved → models/agri_models.pkl")
```

### 6.3 FastAPI Endpoints (main.py)

```python
# main.py — FastAPI Smart Agri City ML Service
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
import joblib, numpy as np

app = FastAPI(title="Smart Agri City ML Service", version="1.0")
B = joblib.load("models/agri_models.pkl")

# ── SCHEMAS ──────────────────────────────────────────────────────────────────
class YieldIn(BaseModel):
    avg_temp: float
    rainfall: float
    soil_moisture: float = Field(..., ge=0, le=100)
    ph: float = Field(..., ge=0, le=14)
    nitrogen: float; phosphorus: float; potassium: float
    area_ha: float = Field(..., gt=0)
    week_of_planting: int = Field(..., ge=1, le=52)

class PestIn(BaseModel):
    air_humidity: float = Field(..., ge=0, le=100)
    leaf_temp: float
    soil_ph: float = Field(..., ge=0, le=14)
    chlorophyll: float
    light_lux: float
    zone: str

class IrrigationIn(BaseModel):
    soil_moisture: float = Field(..., ge=0, le=100)
    air_temp: float
    rain_forecast: int = Field(..., ge=0, le=3)
    growth_phase: str   # semai / vegetatif / generatif / panen
    evapotranspiration: float

# ── ENDPOINTS ─────────────────────────────────────────────────────────────────
@app.get("/health")
def health():
    return {"status": "ok", "service": "python-ml", "models": list(B.keys())}

@app.post("/predict/yield")
def predict_yield(d: YieldIn):
    b = B['yield']
    X = b['scaler'].transform([[
        d.avg_temp, d.rainfall, d.soil_moisture, d.ph,
        d.nitrogen, d.phosphorus, d.potassium, d.area_ha, d.week_of_planting
    ]])
    yield_ton = float(b['model'].predict(X)[0])
    category  = "Tinggi" if yield_ton > 6 else "Normal" if yield_ton > 3 else "Rendah"
    return {
        "predicted_yield_ton": round(yield_ton, 2),
        "yield_category": category,
        "estimated_harvest_days": 90 if d.week_of_planting < 20 else 110
    }

@app.post("/predict/pest")
def predict_pest(d: PestIn):
    b = B['pest']
    zone_enc = b['le_zone'].transform([d.zone])[0]
    X = b['scaler'].transform([[
        d.air_humidity, d.leaf_temp, d.soil_ph,
        d.chlorophyll, d.light_lux, zone_enc
    ]])
    pred  = b['model'].predict(X)[0]
    proba = b['model'].predict_proba(X)[0]
    label = b['le_pest'].inverse_transform([pred])[0]
    return {
        "pest_category": label,
        "confidence": round(float(proba.max()), 3),
        "action_required": label != "Sehat"
    }

@app.post("/predict/irrigation")
def predict_irrigation(d: IrrigationIn):
    b = B['irrigation']
    phase_enc = b['le_phase'].transform([d.growth_phase])[0]
    X = b['scaler'].transform([[
        d.soil_moisture, d.air_temp, d.rain_forecast,
        phase_enc, d.evapotranspiration
    ]])
    liters = float(b['model'].predict(X)[0])
    urgency = "Kritis" if liters > 5000 else "Segera" if liters > 2000 else "Tidak Perlu"
    return {
        "water_needed_liters": round(liters, 1),
        "irrigation_urgency": urgency,
        "open_valve_minutes": round(liters / 83.3, 0)  # asumsi 5000 L/jam
    }
```

### 6.4 Target Metrik Evaluasi Model

| Model | Metrik | Target Minimum |
|---|---|---|
| Crop Yield Predictor | R² (5-fold CV) | ≥ 0.70 |
| Pest & Disease Classifier | Accuracy (5-fold CV) | ≥ 0.72 |
| Irrigation Optimizer | R² (5-fold CV) | ≥ 0.70 |

---

## 7. Dataset & Sumber Data

### 7.1 Dataset Utama

| # | Nama Dataset | Link | Digunakan Untuk |
|---|---|---|---|
| 1 | Crop Yield Prediction Dataset | https://www.kaggle.com/datasets/patelris/crop-yield-prediction-dataset | Model 1: Yield Predictor (fitur: suhu, curah hujan, area, hasil panen) |
| 2 | Crop Recommendation Dataset | https://www.kaggle.com/datasets/atharvaingle/crop-recommendation-dataset | Model 2 & 3: fitur NPK, pH, kelembaban, suhu per jenis tanaman |
| 3 | Global Weather Repository | https://www.kaggle.com/datasets/nelgiriyewithana/global-weather-repository | Model 3: Irrigation Optimizer (filter: Indonesia) |
| 4 | Plant Disease Dataset | https://www.kaggle.com/datasets/vipoooool/new-plant-diseases-dataset | Augmentasi statistik fitur untuk Pest Classifier |

### 7.2 Dataset Sintetis

Jika dataset real tidak mencukupi 5.000 baris, generate menggunakan:

```python
# generate_agri_dataset.py
import pandas as pd, numpy as np, random

ZONES   = ['zona1', 'zona2', 'zona3', 'zona4']
CROPS   = ['padi', 'jagung', 'cabai', 'tomat']
PHASES  = ['semai', 'vegetatif', 'generatif', 'panen']
PESTS   = ['Sehat', 'Wereng', 'Blast', 'Tungro', 'Layu_Fusarium']

rows = []
for _ in range(5000):
    zone  = random.choice(ZONES)
    crop  = random.choice(CROPS)
    phase = random.choice(PHASES)
    moist = random.uniform(20, 90)
    rows.append({
        'zone': zone, 'crop': crop,
        'soil_moisture': moist,
        'air_temp': random.uniform(22, 36),
        'soil_ph': round(random.uniform(5.0, 7.5), 2),
        'nitrogen': random.uniform(10, 90),
        'phosphorus': random.uniform(5, 60),
        'potassium': random.uniform(10, 100),
        'light_lux': random.uniform(5000, 80000),
        'air_humidity': random.uniform(50, 95),
        'rainfall_mm': random.uniform(0, 300),
        'growth_phase': phase,
        'evapotranspiration': random.uniform(2, 8),
        'rain_forecast': random.randint(0, 3),
        'yield_ton_per_ha': max(0, np.random.normal(5, 1.5)),
        'pest_category': random.choices(PESTS, weights=[60,15,10,10,5])[0],
        'water_needed_liters': max(0, (90 - moist) * 55 + random.gauss(0, 200)),
    })

df = pd.DataFrame(rows)
df.to_csv('data/agri_synthetic.csv', index=False)
print(f"Generated {len(df)} rows → data/agri_synthetic.csv")
```

### 7.3 EDA Notebook

Sertakan `notebooks/EDA_agri.ipynb` yang berisi:
- Distribusi setiap fitur (histogram + boxplot)
- Correlation heatmap antar fitur
- Analisis seasonal (pola musim tanam Indonesia)
- Class distribution untuk pest classifier
- Feature importance setelah training

---

## 8. Implementasi IoT Layer

### 8.1 Mosquitto MQTT Broker

Topic naming convention:
```
agri/{zone}/{sensor_type}
Contoh:
  agri/zona1/sensor     → bundle semua sensor dalam satu payload
  agri/zona2/alert      → alert pH kritis atau hama terdeteksi
  agri/zona3/command    → perintah buka/tutup valve irigasi
```

Autentikasi MQTT menggunakan file `passwd` (username/password). Konfigurasi `mosquitto.conf`:
```
listener 1883
allow_anonymous false
password_file /mosquitto/config/passwd
```

### 8.2 Sensor Simulator (Python)

```python
# iot/agri_simulator.py
import paho.mqtt.client as mqtt
import json, time, random, math
from datetime import datetime

BROKER = "localhost"
ZONES  = ["zona1", "zona2", "zona3", "zona4"]
CROPS  = {"zona1": "padi", "zona2": "jagung",
           "zona3": "cabai", "zona4": "tomat"}

client = mqtt.Client()
client.username_pw_set("iot_agri", "agri_secret")
client.connect(BROKER, 1883)

def simulate_soil(zone, hour):
    # Kelembaban tanah lebih rendah siang hari (evapotranspirasi)
    base_moisture = 65 if 6 <= hour <= 10 else 45
    moist = max(10, base_moisture + random.gauss(0, 8))
    return {
        "moisture": round(moist, 2),
        "temperature": round(random.uniform(24, 34), 2),
        "ph": round(random.uniform(5.5, 7.2), 2),
        "nitrogen": round(random.uniform(20, 80), 2),
        "phosphorus": round(random.uniform(15, 60), 2),
        "potassium": round(random.uniform(30, 100), 2),
    }

def simulate_air(zone):
    return {
        "air_temp": round(random.uniform(22, 36), 2),
        "humidity": round(random.uniform(55, 90), 2),
        "light_lux": round(random.uniform(5000, 80000), 0),
    }

while True:
    now = datetime.now()
    for zone in ZONES:
        soil = simulate_soil(zone, now.hour)
        air  = simulate_air(zone)
        payload = {**soil, **air,
                   "zone": zone, "crop": CROPS[zone],
                   "timestamp": now.isoformat()}
        client.publish(f"agri/{zone}/sensor", json.dumps(payload), qos=1)

        # Alert jika pH kritis (di luar batas 5.5–7.0)
        if payload["ph"] < 5.5 or payload["ph"] > 7.0:
            client.publish(f"agri/{zone}/alert",
                json.dumps({"type": "ph_critical", "value": payload["ph"],
                            "zone": zone, "timestamp": now.isoformat()}), qos=1)

        # Alert jika kelembaban sangat rendah (kekeringan)
        if payload["moisture"] < 25:
            client.publish(f"agri/{zone}/alert",
                json.dumps({"type": "drought_warning", "moisture": payload["moisture"],
                            "zone": zone, "timestamp": now.isoformat()}), qos=1)
    time.sleep(30)
```

### 8.3 Node-RED Flows

Minimal 3 flow yang wajib diimplementasikan:

**Flow 1 — Sensor Ingestion:**
`Subscribe agri/+/sensor` → parse JSON → transform ke format PHP service → `POST localhost:3000/iot/sensor`

**Flow 2 — Alert Handling:**
`Subscribe agri/+/alert` → parse JSON → `POST localhost:3000/api/alerts` dengan bearer token IoT → Notifikasi petani

**Flow 3 — Hourly ML Prediction Trigger:**
Inject node (setiap 60 menit) → aggregate rata-rata sensor 1 jam → `POST localhost:3000/predict/yield` → simpan hasil prediksi ke DB via Crop Service

Error handling di semua flow: jika gateway down → simpan ke antrian lokal Node-RED (queue node).

---

## 9. Message Broker — RabbitMQ

### 9.1 Exchange & Queue Configuration

| Exchange | Queue | Publisher | Consumer | Peristiwa |
|---|---|---|---|---|
| `agri.events` | `sensor.new` | Irrigation PHP Service | Python ML Service | Data sensor baru masuk dari Node-RED |
| `agri.events` | `alert.pest` | Python ML Service | Crop PHP Service | Hama/penyakit terdeteksi → buat alert |
| `agri.events` | `harvest.ready` | Python ML Service | Farmer PHP Service | Prediksi panen dalam 7 hari ke depan |
| `agri.events` | `irrigation.trigger` | Python ML Service | Irrigation PHP Service | Rekomendasi irigasi darurat (moisture kritis) |
| `agri.events` | `report.submitted` | Farmer PHP Service | Notification Worker | Petani submit laporan manual |
| `agri.commands` | `iot.valve` | API Gateway | Node-RED / IoT Gateway | Perintah buka/tutup valve irigasi zona |

### 9.2 Consumer Python ML (Sensor Consumer)

```python
# consumers/sensor_consumer.py
import pika, json, joblib, numpy as np

def start_consumer():
    conn   = pika.BlockingConnection(pika.ConnectionParameters('rabbitmq'))
    ch     = conn.channel()
    bundle = joblib.load("models/agri_models.pkl")

    ch.exchange_declare(exchange='agri.events', exchange_type='topic', durable=True)
    ch.queue_declare(queue='sensor.new', durable=True)
    ch.queue_bind(queue='sensor.new', exchange='agri.events', routing_key='sensor.new')

    def callback(ch, method, props, body):
        event = json.loads(body)
        b = bundle['irrigation']
        phase_enc = b['le_phase'].transform(['vegetatif'])[0]  # default jika tidak ada
        X = b['scaler'].transform([[
            event['moisture'], event['air_temp'],
            0,                 # rain_forecast default
            phase_enc,
            4.5                # evapotranspiration default
        ]])
        liters = float(b['model'].predict(X)[0])
        if liters > 5000:
            print(f"[ML] {event['zone']} → IRIGASI DARURAT {liters:.0f}L")
            # Publish irrigation.trigger ke RabbitMQ
        ch.basic_ack(delivery_tag=method.delivery_tag)

    ch.basic_consume(queue='sensor.new', on_message_callback=callback)
    print("ML Consumer listening on sensor.new ...")
    ch.start_consuming()
```

---

## 10. Docker — Containerisasi

### 10.1 Dockerfile per Service

**Python ML Service:**
```dockerfile
# python-ml-service/Dockerfile
FROM python:3.11-slim
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY . .
RUN python train_models.py
EXPOSE 5000
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:5000/health || exit 1
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]
```

**PHP Service (contoh Farmer):**
```dockerfile
# php-farmer/Dockerfile
FROM php:8.2-fpm-alpine
RUN apk add --no-cache nginx && docker-php-ext-install pdo pdo_mysql
WORKDIR /var/www/html
COPY . .
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD curl -f http://localhost:8000/health || exit 1
CMD ["php", "-S", "0.0.0.0:8000", "public/index.php"]
```

### 10.2 Docker Compose

```yaml
# docker-compose.yml
version: "3.9"

networks:
  agri-net:
    driver: bridge

volumes:
  mysql-data:
  rabbitmq-data:
  grafana-data:

services:
  # ── INFRA ──────────────────────────────────────────────────────────────────
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: rootpass
      MYSQL_DATABASE: agriCity
    volumes: [mysql-data:/var/lib/mysql]
    networks: [agri-net]
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  rabbitmq:
    image: rabbitmq:3.12-management
    ports: ["15672:15672"]
    volumes: [rabbitmq-data:/var/lib/rabbitmq]
    networks: [agri-net]

  mosquitto:
    image: eclipse-mosquitto:2.0
    ports: ["1883:1883"]
    volumes: [./iot/mosquitto.conf:/mosquitto/config/mosquitto.conf]
    networks: [agri-net]

  # ── SERVICES ───────────────────────────────────────────────────────────────
  api-gateway:
    build: ./express-gateway
    ports: ["3000:3000"]
    environment:
      JWT_SECRET: ${JWT_SECRET}
      FARMER_SERVICE_URL: http://farmer-service:8000
      CROP_SERVICE_URL: http://crop-service:8001
      IRRIGATION_SERVICE_URL: http://irrigation-service:8002
      PYTHON_ML_URL: http://python-ml:5000
    depends_on: [rabbitmq, mysql]
    networks: [agri-net]

  oauth-server:
    build: ./oauth-server
    ports: ["3002:3002"]
    environment:
      JWT_SECRET: ${JWT_SECRET}
      DB_HOST: mysql
    depends_on: {mysql: {condition: service_healthy}}
    networks: [agri-net]

  farmer-service:
    build: ./php-farmer
    environment:
      DB_HOST: mysql
      DB_NAME: agriCity
      RABBITMQ_HOST: rabbitmq
    depends_on: {mysql: {condition: service_healthy}}
    networks: [agri-net]

  crop-service:
    build: ./php-crop
    environment:
      DB_HOST: mysql
      DB_NAME: agriCity
      RABBITMQ_HOST: rabbitmq
    depends_on: {mysql: {condition: service_healthy}}
    networks: [agri-net]

  irrigation-service:
    build: ./php-irrigation
    environment:
      DB_HOST: mysql
      DB_NAME: agriCity
      RABBITMQ_HOST: rabbitmq
    depends_on: {mysql: {condition: service_healthy}}
    networks: [agri-net]

  python-ml:
    build: ./python-ml-service
    environment:
      RABBITMQ_HOST: rabbitmq
    depends_on: [rabbitmq]
    networks: [agri-net]

  node-red:
    image: nodered/node-red:3.1
    ports: ["1880:1880"]
    volumes: [./iot/node-red-data:/data]
    networks: [agri-net]

  # ── MONITORING ─────────────────────────────────────────────────────────────
  prometheus:
    image: prom/prometheus:v2.48.0
    ports: ["9090:9090"]
    volumes: [./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml]
    networks: [agri-net]

  grafana:
    image: grafana/grafana:10.2.0
    ports: ["3001:3000"]
    volumes: [grafana-data:/var/lib/grafana]
    networks: [agri-net]
```

### 10.3 Perintah Docker Wajib Dikuasai

```bash
docker compose up -d --build     # build semua image dan jalankan di background
docker compose ps                # lihat status semua container
docker compose logs -f <svc>     # streaming log service tertentu
docker compose down -v           # hentikan dan hapus container + volume
docker exec -it <name> sh        # masuk ke dalam container
docker stats                     # monitor CPU, memory real-time
```

---

## 11. Kubernetes — Orkestrasi

### 11.1 Manifest Kubernetes yang Wajib Dibuat

| File Manifest | Kind | Fungsi |
|---|---|---|
| `k8s/namespace.yaml` | Namespace | Isolasi resource dalam namespace `agriCity` |
| `k8s/configmap.yaml` | ConfigMap | URL service, port, feature flags non-sensitif |
| `k8s/secrets.yaml` | Secret | DB password, JWT secret (base64 encoded) |
| `k8s/mysql-statefulset.yaml` | StatefulSet + PVC | MySQL dengan persistent storage |
| `k8s/rabbitmq-deployment.yaml` | Deployment + Service | RabbitMQ message broker |
| `k8s/gateway-deployment.yaml` | Deployment + Service | API Gateway, 2 replika |
| `k8s/python-ml-deployment.yaml` | Deployment + Service + HPA | ML Service, auto-scale 1–5 pod |
| `k8s/php-deployments.yaml` | 3x Deployment + Service | Farmer, Crop, Irrigation service |
| `k8s/ingress.yaml` | Ingress | Route traffic eksternal ke service yang tepat |
| `k8s/hpa.yaml` | HorizontalPodAutoscaler | Scale Python ML saat CPU > 70% |

### 11.2 Contoh Gateway Deployment

```yaml
# k8s/gateway-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: api-gateway
  namespace: agriCity
  labels: {app: api-gateway, tier: gateway}
spec:
  replicas: 2
  selector:
    matchLabels: {app: api-gateway}
  template:
    metadata:
      labels: {app: api-gateway}
    spec:
      containers:
      - name: api-gateway
        image: agri-city/api-gateway:latest
        ports: [{containerPort: 3000}]
        envFrom:
        - configMapRef: {name: agri-config}
        - secretRef:    {name: agri-secrets}
        readinessProbe:
          httpGet: {path: /health, port: 3000}
          initialDelaySeconds: 10
          periodSeconds: 5
        livenessProbe:
          httpGet: {path: /health, port: 3000}
          initialDelaySeconds: 30
          periodSeconds: 10
        resources:
          requests: {cpu: "100m", memory: "128Mi"}
          limits:   {cpu: "500m", memory: "512Mi"}
---
apiVersion: v1
kind: Service
metadata:
  name: api-gateway-svc
  namespace: agriCity
spec:
  selector: {app: api-gateway}
  ports: [{port: 80, targetPort: 3000}]
  type: ClusterIP
```

### 11.3 HPA untuk Python ML

```yaml
# k8s/hpa.yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: python-ml-hpa
  namespace: agriCity
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: python-ml
  minReplicas: 1
  maxReplicas: 5
  metrics:
  - type: Resource
    resource:
      name: cpu
      target: {type: Utilization, averageUtilization: 70}
```

### 11.4 Perintah kubectl Wajib Dikuasai

```bash
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/ -n agriCity           # apply semua manifest
kubectl get pods -n agriCity -w             # watch status pod real-time
kubectl get svc -n agriCity                 # lihat semua service & ClusterIP
kubectl describe pod <pod-name> -n agriCity # detail pod + events
kubectl logs -f <pod-name> -n agriCity      # streaming log
kubectl scale deployment python-ml --replicas=3 -n agriCity
kubectl set image deployment/api-gateway api-gateway=agri-city/api-gateway:v2 -n agriCity
kubectl rollout status deployment/api-gateway -n agriCity
kubectl rollout undo deployment/api-gateway -n agriCity   # rollback jika gagal
kubectl exec -it <pod-name> -n agriCity -- /bin/sh
```

---

## 12. Spesifikasi API Endpoints

### 12.1 Auth & Gateway Endpoints

| Method | Endpoint | Auth | Service | Deskripsi |
|---|---|---|---|---|
| POST | `/oauth/token` | Tidak | OAuth Server | Issue access_token (password / client_credentials grant) |
| POST | `/oauth/introspect` | Client Secret | OAuth Server | Validasi token (digunakan Gateway internal) |
| POST | `/oauth/revoke` | Bearer | OAuth Server | Cabut token |
| GET | `/health` | Tidak | Gateway | Status semua upstream service (aggregated) |
| GET | `/metrics` | Internal | Gateway | Prometheus metrics scrape endpoint |

### 12.2 Farmer Service Endpoints (via Gateway → port 8000)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/farmers` | JWT | Daftarkan petani baru |
| GET | `/api/farmers/:id` | JWT | Profil + ringkasan lahan petani |
| POST | `/api/lands` | JWT | Tambah data lahan |
| GET | `/api/lands/:zone_id` | JWT | Detail lahan per zona |
| POST | `/api/harvests` | JWT | Catat hasil panen aktual |
| GET | `/api/harvests` | JWT | Riwayat panen (filter crop, zone, date) |
| GET | `/health` | Tidak | Status DB connection |

### 12.3 Crop Service Endpoints (via Gateway → port 8001)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/crops/recommend` | JWT | Rekomendasi tanaman by kondisi tanah |
| POST | `/api/crops/schedule` | JWT | Buat jadwal tanam |
| GET | `/api/crops/growth/:id` | JWT | Status pertumbuhan aktif |
| POST | `/api/alerts` | JWT (IoT) | Buat alert hama/penyakit dari ML |
| GET | `/api/alerts/active` | JWT | Daftar alert aktif per zona |
| PATCH | `/api/alerts/:id/resolve` | JWT + Admin | Tandai alert selesai ditangani |
| GET | `/health` | Tidak | Status DB connection |

### 12.4 Irrigation Service Endpoints (via Gateway → port 8002)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/sensors/readings` | JWT (IoT) | Simpan pembacaan sensor dari Node-RED |
| GET | `/api/sensors/current` | JWT | Kondisi sensor terkini per zona |
| GET | `/api/sensors/history` | JWT | Riwayat sensor (filter date, zone) |
| GET | `/api/irrigation/status` | JWT | Status valve irigasi real-time per zona |
| POST | `/api/irrigation/command` | JWT + Admin | Nyalakan/matikan irigasi zona |
| GET | `/api/irrigation/logs` | JWT | Riwayat irigasi (volume, durasi, zona) |
| GET | `/health` | Tidak | Status DB connection |

### 12.5 Python ML Endpoints (via Gateway → port 5000)

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/health` | Tidak | Status service + daftar model terload |
| POST | `/predict/yield` | JWT | Prediksi hasil panen (ton/ha) |
| POST | `/predict/pest` | JWT | Klasifikasi hama/penyakit tanaman |
| POST | `/predict/irrigation` | JWT | Rekomendasi kebutuhan air (liter) |
| GET | `/model/feature-importance` | JWT | Bobot fitur penting ketiga model |
| POST | `/predict/batch` | JWT | Batch prediction — array input, array output |

### 12.6 Standar Response JSON

Seluruh service wajib menggunakan format response berikut:

```json
{
  "status": "success",
  "code": 200,
  "data": { "..." : "..." },
  "message": "Keterangan singkat",
  "timestamp": "2025-01-01T00:00:00.000Z",
  "service": "farmer-service"
}
```

HTTP status code yang digunakan: `200`, `201`, `400`, `401`, `403`, `404`, `422`, `429`, `500`, `502`, `503`.

---

## 13. Skema Database

### 13.1 Tabel Wajib per Service

```sql
-- database/schema.sql
CREATE DATABASE IF NOT EXISTS agriCity;
USE agriCity;

-- ── SHARED ──────────────────────────────────────────────────────────────────
CREATE TABLE zones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) NOT NULL,
    location_lat    DECIMAL(10,7),
    location_lng    DECIMAL(10,7),
    area_hectare    DECIMAL(6,2),
    soil_type       ENUM('lempung','pasir','debu','humus'),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

CREATE TABLE oauth_clients (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       VARCHAR(80) UNIQUE NOT NULL,
    client_secret   VARCHAR(80),
    grant_types     VARCHAR(200),
    redirect_uris   TEXT
);

CREATE TABLE oauth_tokens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       VARCHAR(80),
    user_id         INT,
    access_token    VARCHAR(500) UNIQUE NOT NULL,
    refresh_token   VARCHAR(500),
    expires_at      TIMESTAMP,
    INDEX idx_access_token (access_token(100))
);

-- ── FARMER SERVICE ──────────────────────────────────────────────────────────
CREATE TABLE farmers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nik             VARCHAR(16) UNIQUE NOT NULL,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(100) UNIQUE NOT NULL,
    phone           VARCHAR(20),
    zone_id         INT,
    role            ENUM('petani','petugas','admin') DEFAULT 'petani',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    FOREIGN KEY (zone_id) REFERENCES zones(id)
);

CREATE TABLE lands (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id       INT NOT NULL,
    zone_id         INT NOT NULL,
    area_m2         DECIMAL(10,2),
    soil_ph_baseline DECIMAL(4,2),
    coordinates     TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_farmer (farmer_id)
);

CREATE TABLE harvests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id       INT NOT NULL,
    zone_id         INT NOT NULL,
    crop_type       VARCHAR(50),
    yield_ton       DECIMAL(8,2),
    harvest_date    DATE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_crop (crop_type)
);

-- ── CROP SERVICE ─────────────────────────────────────────────────────────────
CREATE TABLE crop_schedules (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    zone_id               INT NOT NULL,
    crop_type             VARCHAR(50),
    plant_date            DATE,
    expected_harvest_date DATE,
    growth_phase          ENUM('semai','vegetatif','generatif','panen'),
    status                ENUM('aktif','selesai','gagal') DEFAULT 'aktif',
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_status (status)
);

CREATE TABLE alerts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT NOT NULL,
    alert_type      VARCHAR(50),
    severity        ENUM('rendah','sedang','tinggi','kritis'),
    value           DECIMAL(10,2),
    description     TEXT,
    resolved_at     TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_severity (severity),
    INDEX idx_created (created_at)
);

-- ── IRRIGATION SERVICE ───────────────────────────────────────────────────────
CREATE TABLE sensor_readings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT NOT NULL,
    moisture        DECIMAL(5,2),
    temperature     DECIMAL(5,2),
    ph              DECIMAL(4,2),
    nitrogen        DECIMAL(6,2),
    phosphorus      DECIMAL(6,2),
    potassium       DECIMAL(6,2),
    air_temp        DECIMAL(5,2),
    air_humidity    DECIMAL(5,2),
    light_lux       DECIMAL(10,2),
    recorded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_recorded (recorded_at)
);

CREATE TABLE irrigation_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT NOT NULL,
    started_at      TIMESTAMP NOT NULL,
    ended_at        TIMESTAMP NULL,
    volume_liters   DECIMAL(10,2),
    trigger_type    ENUM('manual','otomatis_ml','otomatis_jadwal'),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_started (started_at)
);
```

### 13.2 Best Practice Database

- Gunakan satu database MySQL `agriCity` dengan prefix tabel per service (`farmer_`, `crop_`, `irr_`) jika diperlukan
- Buat user MySQL per service dengan akses terbatas hanya ke tabel yang relevan
- Tambahkan `INDEX` pada kolom yang sering di-filter: `zone_id`, `recorded_at`, `status`, `created_at`
- Sertakan `database/seed.sql` dengan data dummy: 4 zona, 50 petani, 200 sensor readings, 200 harvest records, 20 alerts

---

## 14. Struktur Repository

```
smart-agri-city/
│
├── express-gateway/                  # Per. 7, 10 — API Gateway + Rate Limiting
│   ├── src/
│   │   ├── index.js
│   │   ├── routes/
│   │   ├── middleware/               # jwt.js, rateLimit.js, logger.js
│   │   └── utils/
│   ├── Dockerfile
│   ├── package.json
│   └── .env.example
│
├── oauth-server/                     # Per. 5, 6 — JWT & OAuth 2.0
│   ├── src/
│   ├── Dockerfile
│   └── package.json
│
├── php-farmer/                       # Per. 3, 4 — Farmer PHP MVC Service
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── FarmerController.php
│   │   │   ├── LandController.php
│   │   │   └── HarvestController.php
│   │   ├── Models/
│   │   │   ├── Farmer.php
│   │   │   ├── Land.php
│   │   │   └── Harvest.php
│   │   ├── Services/
│   │   │   └── RabbitMQPublisher.php
│   │   └── Validators/
│   ├── public/index.php
│   ├── Dockerfile
│   └── .env.example
│
├── php-crop/                         # Per. 3, 4 — Crop PHP MVC Service
│   └── (struktur sama dengan php-farmer)
│
├── php-irrigation/                   # Per. 3, 4 — Irrigation PHP MVC Service
│   └── (struktur sama dengan php-farmer)
│
├── python-ml-service/                # Per. 11 — Python ML FastAPI
│   ├── main.py
│   ├── train_models.py
│   ├── generate_agri_dataset.py      # Generate dataset sintetis jika diperlukan
│   ├── consumers/
│   │   └── sensor_consumer.py        # Per. 9 — RabbitMQ consumer
│   ├── models/                       # File .pkl (di .gitignore)
│   ├── data/
│   │   ├── crop_yield.csv
│   │   ├── pest_disease.csv
│   │   └── irrigation_demand.csv
│   ├── notebooks/
│   │   └── EDA_agri.ipynb
│   ├── requirements.txt
│   ├── Dockerfile
│   └── .env.example
│
├── iot/                              # Tambahan — IoT Layer
│   ├── agri_simulator.py
│   ├── mosquitto.conf
│   └── node-red-data/               # Node-RED flows (3 flow)
│
├── database/
│   ├── schema.sql                   # DDL semua tabel
│   └── seed.sql                     # Data dummy realistis
│
├── k8s/                             # Per. 13 — Kubernetes manifests
│   ├── namespace.yaml
│   ├── configmap.yaml
│   ├── secrets.yaml
│   ├── mysql-statefulset.yaml
│   ├── rabbitmq-deployment.yaml
│   ├── gateway-deployment.yaml
│   ├── python-ml-deployment.yaml
│   ├── php-deployments.yaml
│   ├── ingress.yaml
│   └── hpa.yaml
│
├── monitoring/
│   ├── prometheus.yml               # Scrape config semua service
│   └── grafana-dashboard.json       # Dashboard: soil moisture, yield trend,
│                                    # pest alert count, irrigation volume, ML latency
│
├── postman/
│   └── SmartAgriCity.postman_collection.json
│
├── docker-compose.yml               # Per. 12
├── docker-compose.dev.yml           # Override untuk development lokal
├── .env.example                     # Template semua environment variable
├── .gitignore                       # Wajib include: .env, models/*.pkl, vendor/
├── Makefile                         # Shortcut perintah umum
└── README.md
```

---

## 15. Skenario Pengujian End-to-End

Kelompok wajib mendemonstrasikan minimal 5 dari 6 skenario berikut:

| No | Skenario | Alur Wajib Berjalan | Materi Terkait |
|---|---|---|---|
| S1 | IoT Data Ingestion | Simulator publish MQTT → Node-RED subscribe → POST ke Gateway → Irrigation Service simpan ke DB → publish `sensor.new` ke RabbitMQ → Python ML consume & prediksi | Per. 9, 11, IoT |
| S2 | Petani Login & Catat Panen | Petani `POST /oauth/token` → dapat access_token → `POST /api/harvests` dengan Bearer token → Gateway verifikasi JWT → PHP simpan → RabbitMQ publish event | Per. 5, 6, 3, 9 |
| S3 | ML Real-time Prediction | Client `POST /predict/yield` dengan data sensor terkini → Gateway rate-limit check → forward ke Python ML → return prediksi hasil panen + kategori | Per. 7, 10, 11 |
| S4 | Irigasi Otomatis | ML detect `moisture < 25` → publish `irrigation.trigger` → Irrigation Service receive → publish `iot.valve` ke RabbitMQ → Node-RED consume → buka valve MQTT command | Per. 9, 11, IoT |
| S5 | Docker Full Stack | `docker compose up --build` → semua 12 container running → `GET /health` dari luar → semua service healthy → simulator berjalan → data masuk ke DB → Grafana tampilkan data | Per. 12 |
| S6 | Kubernetes Deployment | `kubectl apply -f k8s/` → semua pod Running → hit Gateway via Ingress → HPA scale Python ML saat load tinggi → `kubectl rollout update` Gateway tanpa downtime | Per. 13 |

---

## 16. Kriteria Penilaian & Poin Bonus

### 16.1 Kriteria Utama

| No | Komponen | Bobot | Indikator Keberhasilan Minimum |
|---|---|---|---|
| 1 | Arsitektur & Desain (Per.1-2) | 5% | Diagram arsitektur lengkap, semua service terdefinisi, keputusan desain terdokumentasi |
| 2 | PHP MVC Services (Per.3-4) | 12% | 3 service PHP berjalan, CRUD lengkap, pola MVC diterapkan, validasi input ada |
| 3 | JWT & OAuth 2.0 (Per.5-6) | 10% | OAuth server menerbitkan token, Gateway verifikasi JWT, refresh token bekerja |
| 4 | API Gateway & Rate Limit (Per.7,10) | 10% | Routing benar, rate limit aktif (test burst request), logging ada |
| 5 | RabbitMQ Integration (Per.9) | 10% | Minimal 2 event mengalir lewat RabbitMQ, consumer Python ML aktif |
| 6 | Python ML Service (Per.11) | 15% | 3 model terlatih (R²/Acc ≥70%), semua endpoint FastAPI berjalan, response <500ms |
| 7 | IoT Integration | 8% | Simulator berjalan, data masuk via MQTT → Node-RED → API dalam 60 detik |
| 8 | Docker (Per.12) | 12% | `docker compose up --build` berhasil, semua container healthy |
| 9 | Kubernetes (Per.13) | 10% | Semua pod Running di namespace `agriCity`, HPA terkonfigurasi, Ingress bekerja |
| 10 | Integrasi End-to-End | 8% | Minimal 3 dari 6 skenario S1–S6 berjalan sempurna saat demo |
| 11 | Dokumentasi & README | 5% | Setup dapat diikuti orang baru <15 menit, Postman collection ada, schema.sql ada |
| 12 | Demo & Presentasi | 5% | Penjelasan teknis jelas, semua anggota memahami kode yang dibuat |

### 16.2 Poin Bonus

- **CI/CD Pipeline:** GitHub Actions build + push Docker image ke registry otomatis setiap push ke `main`
- **Monitoring lengkap:** Grafana dashboard dengan minimal 5 panel bermakna (soil moisture per zona, pest alert count, predicted yield trend, irrigation volume, ML response latency)
- **HTTPS/TLS:** Ingress terkonfigurasi dengan TLS certificate (self-signed diterima)
- **Unit & Integration Test:** Coverage ≥ 60% untuk minimal 2 service
- **Service Mesh:** Implementasi Istio atau Linkerd untuk observability & traffic management

---

## 17. Deliverables yang Dikumpulkan

| No | Item | Format | Keterangan |
|---|---|---|---|
| 1 | Source Code | Git Repository (GitHub/GitLab) | Semua service, satu repo, branch `main` = production-ready |
| 2 | README.md | Markdown di root repo | Prerequisites, setup .env, cara jalankan lokal & di server, cara test |
| 3 | Diagram Arsitektur | PNG/PDF | System diagram + sequence diagram minimal 2 skenario utama (S1 IoT ingestion, S2 petani login) |
| 4 | `database/schema.sql` | SQL File | DDL + `CREATE DATABASE agriCity` + `USE`, bisa dirun langsung |
| 5 | `database/seed.sql` | SQL File | Data dummy realistis, min 200 baris data total |
| 6 | Postman Collection | JSON Export | Semua endpoint terorganisir per service, env variable pakai `{{baseUrl}}` dan `{{token}}` |
| 7 | ML Report | PDF atau Jupyter Notebook | Dataset, EDA, preprocessing, training, cross-validation, evaluasi ketiga model |
| 8 | `docker-compose.yml` | YAML | Satu perintah `docker compose up` menjalankan seluruh sistem |
| 9 | `k8s/` folder | YAML manifests | Semua manifest K8s, bisa di-apply ke kluster bersih |
| 10 | Demo Video | MP4, max 15 menit | Demonstrasi live semua 6 skenario end-to-end di server |

**Pengumpulan:** submit link repo + link video ke Google Form yang dibagikan dosen, sebelum demo Pertemuan 16.

---

## 18. Pembagian Kerja Tim

Rekomendasi pembagian untuk kelompok 4 orang:

| Anggota | Tanggung Jawab Utama | Service/Komponen |
|---|---|---|
| Anggota 1 | Backend PHP + Database | php-farmer, php-crop, php-irrigation (MVC + RabbitMQ publisher), schema.sql, seed.sql |
| Anggota 2 | Gateway + Auth + IoT | express-gateway (JWT, rate limit, routing, load balancing), oauth-server, MQTT simulator, Node-RED 3 flows |
| Anggota 3 | Python ML + RabbitMQ Consumer | train_models.py, main.py (FastAPI 3 endpoint), sensor_consumer.py, EDA_agri.ipynb, generate dataset |
| Anggota 4 | DevOps + Monitoring | docker-compose.yml, k8s/ manifests (10 file), prometheus.yml, Grafana dashboard 5 panel, README.md |

---

## 19. Aturan Keamanan Server

> **Wajib dipatuhi oleh seluruh anggota kelompok.**

- **JANGAN** simpan password, JWT secret, atau kredensial apapun di dalam kode yang di-push ke Git
- Selalu gunakan file `.env` dan tambahkan `.env` di `.gitignore` — periksa ulang sebelum `git push`
- File `.gitignore` wajib include: `.env`, `models/*.pkl`, `vendor/`, `node_modules/`, `*.log`
- **DILARANG** mengakses, membaca log, atau mengganggu service kelompok lain
- **DILARANG** mematikan container atau pod yang bukan milik kelompok sendiri
- Hubungi dosen atau asisten jika ada masalah server, port konflik, atau akses kubectl

---

## 20. Timeline Pengerjaan

| Minggu | Target | Deliverable |
|---|---|---|
| Minggu 1 | Setup repo, schema DB, `docker-compose.yml` dasar, OAuth server selesai | schema.sql, .env.example, oauth-server running |
| Minggu 2 | Ketiga PHP service selesai (CRUD + RabbitMQ publisher), IoT simulator running | php-farmer, php-crop, php-irrigation, simulator.py |
| Minggu 3 | API Gateway selesai (JWT, rate limit, routing), Node-RED 3 flows, EDA notebook | express-gateway, node-red flows, EDA_agri.ipynb |
| Minggu 4 | Python ML: 3 model terlatih, FastAPI endpoints, RabbitMQ consumer | train_models.py, main.py, sensor_consumer.py |
| Minggu 5 | Docker: semua container sehat, Kubernetes: semua pod running, Grafana dashboard | docker-compose.yml final, k8s/ folder, grafana-dashboard.json |
| Minggu 6 | Testing end-to-end (6 skenario), Postman collection, README, demo video | postman collection, README.md, demo video MP4 |

---

*Selamat Berkarya dan Eksplorasi!*
