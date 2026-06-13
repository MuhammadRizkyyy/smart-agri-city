# Smart Agri City — Integrated Platform

> **Matakuliah:** Pembangunan Perangkat Lunak Berorientasi Service (SE IF)  
> **Tema Proyek:** Smart City Berbasis Agrikultur

Platform microservices untuk memantau, mengotomatisasi, dan mengoptimalkan ekosistem pertanian cerdas. Mengintegrasikan IoT data ingestion, prediksi Machine Learning real-time, OAuth2 authentication, message brokering, observability, dan orkestrasi Kubernetes.

---

## Daftar Isi

- [Prasyarat](#prasyarat)
- [Struktur Repositori](#struktur-repositori)
- [Langkah 1 — Clone & Konfigurasi](#langkah-1--clone--konfigurasi)
- [Langkah 2 — Jalankan Docker Compose](#langkah-2--jalankan-docker-compose)
- [Langkah 3 — Verifikasi Semua Service](#langkah-3--verifikasi-semua-service)
- [Langkah 4 — Jalankan Minikube (Kubernetes)](#langkah-4--jalankan-minikube-kubernetes)
- [Langkah 5 — Jalankan E2E Test](#langkah-5--jalankan-e2e-test)
- [Referensi Port](#referensi-port)
- [Skenario E2E](#skenario-e2e)
- [Makefile Cheatsheet](#makefile-cheatsheet)
- [CI/CD Pipeline](#cicd-pipeline)

---

## Prasyarat

Pastikan semua tools berikut sudah terinstall sebelum memulai:

| Tool | Versi Minimum | Keterangan |
|------|--------------|------------|
| [Docker Desktop](https://www.docker.com/products/docker-desktop/) | 24.x | Aktifkan **WSL2 backend** di Windows |
| [Docker Compose](https://docs.docker.com/compose/) | v2.x | Sudah bundled di Docker Desktop |
| [Git](https://git-scm.com/) | 2.x | — |
| [Minikube](https://minikube.sigs.k8s.io/docs/start/) | 1.30+ | Untuk deployment Kubernetes |
| [kubectl](https://kubernetes.io/docs/tasks/tools/) | 1.28+ | CLI Kubernetes |
| [Bash](https://www.gnu.org/software/bash/) | 4.x+ | Git Bash (Windows) atau WSL2 |
| [Make](https://www.gnu.org/software/make/) | — | Opsional, untuk shortcut |

> **Windows:** Gunakan **Git Bash** untuk menjalankan semua perintah bash dan script `.sh`.

---

## Struktur Repositori

```
smart-agri-city/
├── express-gateway/       # API Gateway + Rate Limiting (Express.js)
├── oauth-server/          # JWT & OAuth 2.0 Authorization Server (Node.js)
├── php-farmer/            # Layanan MVC PHP — Farmer & Harvest
├── php-crop/              # Layanan MVC PHP — Crop & Alert
├── php-irrigation/        # Layanan MVC PHP — Irrigation & Sensor
├── python-ml-service/     # Machine Learning Service (FastAPI)
├── iot/                   # IoT Simulator & Konfigurasi Mosquitto MQTT
├── database/              # Schema SQL & Data Seed
├── k8s/                   # Manifes Kubernetes (Deployment, HPA, Ingress)
├── monitoring/            # Konfigurasi Prometheus & Grafana
├── docs/                  # Diagram arsitektur
├── e2e_test.sh            # Script pengujian End-to-End
├── docker-compose.yml     # Stack produksi (14 container)
├── docker-compose.dev.yml # Override untuk development
├── Makefile               # Shortcut commands
└── .env.example           # Template environment variables
```

---

## Langkah 1 — Clone & Konfigurasi

### 1.1 Clone repositori

```bash
git clone <url-repositori>
cd backend-smartcity
```

### 1.2 Buat file `.env`

```bash
cp .env.example .env
```

Edit `.env` sesuai kebutuhan. Nilai paling penting yang **wajib diubah**:

```env
# Password database (gunakan nilai yang sama di DB_PASSWORD)
DB_PASSWORD=secret_db_password_change_me

# JWT Secret — minimal 32 karakter
JWT_SECRET=super_secret_jwt_key_change_me_in_production_123456789

# RabbitMQ — biarkan default untuk development
RABBITMQ_USERNAME=guest
RABBITMQ_PASSWORD=guest

# MQTT
MQTT_USERNAME=admin
MQTT_PASSWORD=adminpass
```

> **Penting:** Nilai `DB_PASSWORD` di `.env` harus konsisten. Script `e2e_test.sh` menggunakan variabel `DB_PASS` yang sudah dikonfigurasi dengan nilai default `secret_db_password_change_me`.

---

## Langkah 2 — Jalankan Docker Compose

### 2.1 Build dan jalankan semua service

```bash
# Menggunakan Makefile
make up

# Atau langsung dengan docker compose
docker compose up -d --build
```

Proses build pertama kali membutuhkan waktu **5–15 menit** tergantung kecepatan internet (download base images).

### 2.2 Pantau status container

```bash
# Cek apakah semua container healthy
make ps
# atau
docker compose ps
```

Tunggu hingga **semua 14 container** menunjukkan status `healthy`. Biasanya membutuhkan **2–3 menit** setelah build selesai.

```
agri-api-gateway        Up (healthy)
agri-oauth-server       Up (healthy)
agri-php-farmer         Up (healthy)
agri-php-crop           Up (healthy)
agri-php-irrigation     Up (healthy)
agri-python-ml          Up (healthy)
agri-mysql              Up (healthy)
agri-rabbitmq           Up (healthy)
agri-mosquitto          Up (healthy)
agri-node-red           Up (healthy)
agri-iot-simulator      Up (healthy)
agri-prometheus         Up (healthy)
agri-grafana            Up (healthy)
agri-phpmyadmin         Up (healthy)
```

### 2.3 Mode Development (opsional)

Untuk development dengan hot-reload:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

---

## Langkah 3 — Verifikasi Semua Service

Buka browser atau gunakan `curl` untuk memverifikasi service aktif:

| Service | URL | Kredensial |
|---------|-----|------------|
| API Gateway Health | http://localhost:3000/health | — |
| OAuth Server | http://localhost:3002/health | — |
| RabbitMQ Management | http://localhost:15672 | `guest` / `guest` |
| Grafana Dashboard | http://localhost:3001 | `admin` / `admin` |
| Prometheus | http://localhost:9090 | — |
| Node-RED | http://localhost:1880 | — |
| phpMyAdmin | http://localhost:8080 | `root` / nilai `DB_PASSWORD` |

**Quick check semua endpoint:**

```bash
curl http://localhost:3000/health
curl http://localhost:3002/health
curl http://localhost:5000/health
curl http://localhost:8000/health
curl http://localhost:8001/health
curl http://localhost:8002/health
```

---

## Langkah 4 — Jalankan Minikube (Kubernetes)

Langkah ini diperlukan untuk **Skenario S6** (Kubernetes Deployment). Jika hanya ingin menjalankan skenario S1–S5 via Docker Compose, langkah ini bisa **dilewati**.

### 4.1 Start Minikube dengan Docker driver

```bash
minikube start --driver=docker
```

> **Catatan:** Gunakan `--driver=docker` karena Docker Desktop sudah berjalan. Jangan gunakan driver HyperV atau VirtualBox yang rentan error di Windows.

Tunggu hingga output menampilkan:
```
Done! kubectl is now configured to use "minikube" cluster
```

### 4.2 Verifikasi cluster aktif

```bash
kubectl cluster-info
minikube status
```

Output yang diharapkan:
```
minikube
type: Control Plane
host: Running
kubelet: Running
apiserver: Running
kubeconfig: Configured
```

### 4.3 Enable addons yang dibutuhkan

```bash
# Metrics server (untuk HPA)
minikube addons enable metrics-server

# Ingress controller
minikube addons enable ingress

# Cek addons aktif
minikube addons list | grep enabled
```

### 4.4 Build image ke dalam Minikube

Karena Minikube memiliki Docker daemon sendiri, image perlu di-build di dalamnya:

```bash
# Arahkan Docker CLI ke Minikube's Docker daemon
eval $(minikube docker-env)   # Linux/Mac
# Windows Git Bash:
eval $(minikube -p minikube docker-env)

# Build semua image
docker build -t smart-agri/api-gateway:latest ./express-gateway
docker build -t smart-agri/php-farmer:latest ./php-farmer
docker build -t smart-agri/php-crop:latest ./php-crop
docker build -t smart-agri/php-irrigation:latest ./php-irrigation
docker build -t smart-agri/python-ml-service:latest ./python-ml-service

# Kembali ke Docker daemon lokal
eval $(minikube docker-env -u)
```

### 4.5 Deploy ke Kubernetes

```bash
# Buat namespace
kubectl apply -f k8s/namespace.yaml

# Buat secrets (edit secrets.yaml dengan nilai yang benar sebelumnya)
kubectl apply -f k8s/secrets.yaml

# Deploy semua manifest
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/mysql-statefulset.yaml
kubectl apply -f k8s/rabbitmq-deployment.yaml
kubectl apply -f k8s/oauth-server-deployment.yaml
kubectl apply -f k8s/php-deployments.yaml
kubectl apply -f k8s/python-ml-deployment.yaml
kubectl apply -f k8s/gateway-deployment.yaml
kubectl apply -f k8s/hpa.yaml
kubectl apply -f k8s/ingress.yaml

# Atau sekaligus (setelah namespace & secrets dibuat):
kubectl apply -f k8s/ -n agricity
```

### 4.6 Verifikasi pods berjalan

```bash
# Pantau pods sampai semua Running
kubectl get pods -n agricity -w

# Cek HPA
kubectl get hpa -n agricity

# Cek Ingress
kubectl get ingress -n agricity
```

Tunggu hingga semua pod menunjukkan `1/1 Running`.

### 4.7 Jika Minikube sebelumnya error / stale context

```bash
# Update kubectl context
minikube update-context

# Jika perlu restart ulang
minikube stop
minikube start --driver=docker
```

---

## Langkah 5 — Jalankan E2E Test

### 5.1 Prasyarat sebelum menjalankan test

Pastikan kondisi berikut **sebelum** menjalankan `e2e_test.sh`:

- [ ] Semua 14 Docker containers berstatus **healthy** (`docker compose ps`)
- [ ] Minikube sudah **running** (`minikube status`) — untuk S6
- [ ] Gateway baru dijalankan / belum banyak traffic (rate limiter belum penuh)
- [ ] Menjalankan dari **Git Bash** (Windows) atau terminal bash

### 5.2 Jalankan script

```bash
bash e2e_test.sh
```

Waktu eksekusi: **2–4 menit** (termasuk rate-limit test di akhir).

### 5.3 Output yang diharapkan

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  S5 · Docker Full Stack — Service Health Checks
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✅  API Gateway reachable (HTTP 200)
  ✅  OAuth Server reachable (HTTP 200)
  ...semua service reachable...
  ✅  Running containers: 14 (≥12)

  ...S2, S3, S1, S4, S5, S6 semua PASS...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  SUMMARY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Total: 47 tests | ✅ 47 passed | ❌ 0 failed | ⚠️  0 warnings

  🎉  Semua skenario E2E PASSED!
```

### 5.4 Troubleshooting jika test gagal

**Rate limiter kena (429) di tengah test:**

Gateway `globalLimiter` membatasi 100 request / 15 menit per IP. Rate limit di-reset saat gateway restart:

```bash
docker compose restart api-gateway
sleep 10
bash e2e_test.sh
```

**Token OAuth gagal diterima:**

Pastikan MySQL sudah healthy dan seed data sudah ada:

```bash
docker compose ps agri-mysql
docker exec agri-mysql mysql -u root -psecret_db_password_change_me agriCity \
  -e "SELECT email FROM frm_farmers LIMIT 3;"
```

Harus menampilkan `farmer@agri.com`.

**Prometheus target down:**

Tunggu 30 detik setelah semua container healthy, lalu cek:

```bash
curl -s http://localhost:9090/api/v1/targets | grep -o '"health":"[a-z]*"' | sort | uniq -c
```

Semua harus `"health":"up"`.

**Minikube tidak terhubung (S6 warning):**

```bash
minikube update-context
kubectl cluster-info
# Jika masih gagal:
minikube start --driver=docker
```

**php-irrigation /metrics error:**

Jika Prometheus melaporkan `php-irrigation` down dengan error `got "<"`, artinya ada error PHP di endpoint metrics. Cek dengan:

```bash
curl http://localhost:8002/metrics | tail -10
```

---

## Referensi Port

| Service | Port Host | Deskripsi |
|---------|-----------|-----------|
| API Gateway | `3000` | Entry point semua request API |
| OAuth Server | `3002` | Token issue, introspect, revoke |
| Grafana | `3001` | Dashboard monitoring |
| Node-RED | `1880` | IoT flow designer |
| phpMyAdmin | `8080` | Database management UI |
| Prometheus | `9090` | Metrics time-series |
| RabbitMQ UI | `15672` | Message broker management |
| MQTT Broker | `1883` | Mosquitto (IoT messages) |
| Python ML | `5000` | ML prediction endpoints |
| PHP Farmer | `8000` | Farmer & harvest service |
| PHP Crop | `8001` | Crop & alert service |
| PHP Irrigation | `8002` | Irrigation & sensor service |
| MySQL | `3306` | Database (internal) |

---

## Skenario E2E

Script `e2e_test.sh` menguji 6 skenario berikut secara otomatis:

| Skenario | Deskripsi |
|----------|-----------|
| **S1** | IoT Ingestion — MQTT → Node-RED → DB → RabbitMQ → ML |
| **S2** | Login Petani — OAuth2 password grant, JWT, harvest POST, token revoke |
| **S3** | ML Prediction — `/predict/yield`, `/predict/pest`, `/predict/irrigation` |
| **S4** | Irigasi Otomatis — ML dry soil → irrigation command → RabbitMQ |
| **S5** | Monitoring — Prometheus 6/6 targets UP, Grafana, semua `/metrics` |
| **S6** | Kubernetes — cluster aktif, pods Running/Ready, HPA, Ingress |

**Catatan payload penting:**

```bash
# Harvest — gunakan yield_ton, bukan quantity_kg
POST /api/harvests  {"land_id":1,"crop_type":"Padi","yield_ton":5.2,"harvest_date":"2025-06-01"}

# ML Pest — zone harus: zona1 | zona2 | zona3 | zona4
POST /predict/pest  {"zone":"zona1", ...}

# ML Irrigation — growth_phase harus: semai | vegetatif | generatif | panen
POST /predict/irrigation  {"growth_phase":"vegetatif", ...}

# Irrigation command — gunakan action + trigger_type
POST /api/irrigation/command  {"zone_id":1,"action":"start","trigger_type":"otomatis_ml"}
```

---

## Makefile Cheatsheet

```bash
make up          # Build + jalankan semua service (background)
make down        # Stop + hapus semua container, network, volume
make restart     # Restart semua container
make ps          # Status semua container
make logs        # Tail logs semua container
make stats       # Monitor CPU & RAM container real-time
make k8s-apply   # Apply semua manifest k8s/
make k8s-delete  # Hapus semua resource k8s/
make clean       # Hapus cache Python, __pycache__, dll.
```

---

## CI/CD Pipeline

Proyek menggunakan **GitHub Actions** untuk otomatisasi lint, test, dan build Docker image.

### Setup GitHub Secrets

Di **Settings → Secrets and variables → Actions**, tambahkan:

- `DOCKER_USERNAME` — username Docker Hub
- `DOCKER_PASSWORD` — Personal Access Token Docker Hub (bukan password akun)

### Workflow

- **Lint & Test** — otomatis saat `push` atau `pull_request` ke `main` / `dev`
- **Build & Push** — hanya di `main` setelah test pass, push image ke Docker Hub dengan tag `latest` dan short SHA

---

## Git Workflow

Branch `main` dilindungi — **tidak boleh push langsung**. Alur kerja:

```bash
# 1. Sync main lokal
git checkout main && git pull origin main

# 2. Buat branch fitur
git checkout -b feature/nama-fitur

# 3. Kerjakan, commit
git add .
git commit -m "feat: deskripsi perubahan"

# 4. Push & buat Pull Request
git push origin feature/nama-fitur
# → buka GitHub → Compare & pull request → isi deskripsi → Create PR
```

---

## Keamanan

- File `.env`, `tls.key`, `tls.crt`, `k8s/secrets.yaml` sudah ada di `.gitignore` — **jangan pernah di-commit**
- Untuk TLS/HTTPS di Kubernetes, generate self-signed cert:
  ```bash
  openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout tls.key -out tls.crt \
    -subj "/CN=agri.kelompok1.local/O=SmartAgriCity"

  kubectl create secret tls agri-tls-secret \
    --cert=tls.crt --key=tls.key -n agricity
  ```
- Untuk production, gunakan [cert-manager](https://cert-manager.io/) + Let's Encrypt
