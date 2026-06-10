# Smart Agri City — Integrated Platform

> **Matakuliah:** Pembangunan Perangkat Lunak Berorientasi Service (SE IF)  
> **Tema Proyek:** Smart City Berbasis Agrikultur

Selamat datang di **Smart Agri City Integrated Platform**, sebuah sistem microservices tingkat enterprise premium yang dirancang untuk memantau, mengotomatisasi, dan mengoptimalkan ekosistem pertanian cerdas. Platform ini mengintegrasikan ingest data IoT, prediksi Machine Learning secara real-time, microservices terdistribusi, message brokering, dan orkestrasi container dengan ketersediaan tinggi (high-availability).

---

## Struktur Repositori

Berikut adalah pemetaan direktori yang didefinisikan dalam perencanaan teknis kami:

```bash
smart-agri-city/
├── express-gateway/      # API Gateway + Rate Limiting (Express.js)
├── oauth-server/         # JWT & OAuth 2.0 Authorization Server
├── php-farmer/           # Layanan MVC PHP Farmer
├── php-crop/             # Layanan MVC PHP Crop
├── php-irrigation/       # Layanan MVC PHP Irrigation
├── python-ml-service/    # Layanan Machine Learning (FastAPI + Consumer)
├── iot/                  # Simulator IoT & Konfigurasi Mosquitto
├── database/             # Skema DDL & File Seed Dummy Sintetis
├── k8s/                  # Manifes Kubernetes (StatefulSets, HPAs, dll.)
├── monitoring/           # Dasbor Konfigurasi Prometheus & Grafana
└── postman/              # Ekspor Koleksi API Postman
```

---

## Memulai Cepat & Pengaturan (Kurang dari 15 Menit)

### Prasyarat

Pastikan Anda telah menginstal perangkat lunak berikut di mesin Anda:

- [Docker & Docker Desktop](https://www.docker.com/) (sangat direkomendasikan)
- [Git](https://git-scm.com/)
- [Make](https://www.gnu.org/software/make/) (opsional, untuk perintah shortcut)

### Penerapan Lokal dengan Docker Compose

1. **Klon Repositori**

   ```bash
   git clone <your-repository-url>
   cd backend-smartcity
   ```

2. **Setup Environment Variable**
   Salin templat `.env.example` menjadi `.env`:

   ```bash
   cp .env.example .env
   ```

3. **Jalankan Container**
   Menggunakan Makefile:

   ```bash
   make up
   ```

   _Atau langsung menggunakan Docker Compose:_

   ```bash
   docker compose up -d --build
   ```

4. **Verifikasi Kesehatan Container**
   ```bash
   make ps
   ```

---

## Referensi Port Microservices

| Nama Layanan       | Port    | Deskripsi                            | Akses Eksternal |
| ------------------ | ------- | ------------------------------------ | --------------- |
| **api-gateway**    | `3000`  | Gateway & Titik Masuk Reverse Proxy  | Ya              |
| **oauth-server**   | `3002`  | Penerbitan & Pencabutan Token OAuth2 | Ya              |
| **node-red**       | `1880`  | Kanvas Ingest Data Flow              | Ya              |
| **mosquitto**      | `1883`  | Broker Pesan MQTT                    | Ya              |
| **rabbitmq**       | `15672` | Konsol Broker Pesan (guest/guest)    | Ya              |
| **prometheus**     | `9090`  | Pengumpulan Metrik Time Series       | Ya              |
| **grafana**        | `3001`  | Dasbor Observabilitas Sistem         | Ya              |
| **python-ml**      | `5000`  | REST API Machine Learning            | Hanya Internal  |
| **php-farmer**     | `8000`  | Layanan API MVC Farmer               | Hanya Internal  |
| **php-crop**       | `8001`  | Layanan API MVC Crop                 | Hanya Internal  |
| **php-irrigation** | `8002`  | Layanan API MVC Irrigation           | Hanya Internal  |

---

## Skenario Pengujian End-to-End

Platform ini dibuat untuk memenuhi 6 skenario operasional utama:

1. **S1: Ingest Data IoT**
   - simulator edge mempublikasikan telemetri → Mosquitto MQTT → Langganan Node-RED → api-gateway HTTP POST → php-irrigation Penyimpanan DB → RabbitMQ publikasi event `sensor.new` → python-ml konsumsi event & inferensi model.
2. **S2: Login Farmer & Pelacakan Panen**
   - Farmer login melalui `/oauth/token` → menerima JWT → memanggil `POST /api/harvests` dengan Bearer Token → php-farmer menyimpan ke database dan mengirimkan event audit RabbitMQ.
3. **S3: Prediksi ML Real-time**
   - Aplikasi meminta prediksi hasil panen real-time `POST /predict/yield` → validasi batas tingkat (rate limit) gateway → proxy ke layanan Python ML → parsing model → output JSON instan.
4. **S4: Loop Irigasi Cerdas (Umpan Balik ML)**
   - python-ml mendeteksi tanah kering (`moisture < 25`) → mengirimkan event `irrigation.trigger` ke RabbitMQ → php-irrigation menindaklanjutinya → mengirimkan event `iot.valve` → Node-RED menerima perintah → mengirimkan perintah MQTT untuk mengaktifkan katup.
5. **S5: Pemantauan Full Stack**
   - Prometheus melacak endpoint → menampilkan latensi, penggunaan CPU, dan RAM pada Dasbor Grafana.
6. **S6: Deployment Kubernetes**
   - Deployment menggunakan `kubectl apply -f k8s/` → memverifikasi status kluster, penskalaan HPA, dan peningkatan bergulir (rolling upgrade) tanpa downtime.

---

## Aliran Git & Aturan Proteksi Branch

Untuk menjaga standar basis kode yang tinggi, branch **`main`** sepenuhnya dilindungi. Commit langsung dibatasi.

### Menyiapkan Proteksi Branch (GitHub / GitLab)

Jika menggunakan **GitHub**:

1. Buka halaman **Settings** -> **Branches** di repositori Anda.
2. Klik **Add branch protection rule**.
3. Di bawah **Branch name pattern**, masukkan `main`.
4. Centang **Require a pull request before merging**.
5. Centang **Require approvals** (direkomendasikan: 1 persetujuan).
6. Centang **Require status checks to pass before merging** (jika CI/CD aktif).
7. Simpan perubahan.

### Panduan Kloning Repositori (Clone)

Untuk mulai berkontribusi pada proyek ini, ikuti langkah-langkah berikut untuk menduplikat repositori ke komputer lokal Anda:

1. **Salin URL Repositori**
   Dapatkan URL repositori dari platform repositori Anda (misalnya GitHub atau GitLab).

2. **Jalankan Perintah Clone**
   Buka terminal di komputer Anda, lalu jalankan perintah berikut:

   ```bash
   git clone <url-repositori-anda>
   ```

3. **Masuk ke Folder Proyek**
   Setelah proses pengunduhan selesai, masuk ke dalam direktori repositori:

   ```bash
   cd backend-smartcity
   ```

4. **Periksa Remote Repository**
   Pastikan konfigurasi remote remote mengarah ke repositori utama dengan benar:
   ```bash
   git remote -v
   ```

---

## 🚀 CI/CD Pipeline (GitHub Actions)

Proyek ini menggunakan GitHub Actions untuk menjalankan pengujian otomatis dan build Docker images.

### Cara Setup GitHub Secrets

Agar pipeline **Build & Push** berjalan sukses, Anda harus menambahkan dua **Repository Secrets** di GitHub:

1. Pergi ke repository Anda di GitHub.
2. Klik **Settings** > **Secrets and variables** > **Actions**.
3. Klik **New repository secret**.
4. Tambahkan secret berikut:
   - `DOCKER_USERNAME`: Username Docker Hub Anda.
   - `DOCKER_PASSWORD`: Personal Access Token (PAT) dari Docker Hub (bukan password akun!).

### Alur Kerja (Workflow)

- **Lint & Test**: Berjalan otomatis di branch `main` dan `dev` setiap ada `push` atau `pull_request`.
- **Build & Push**: Hanya berjalan di branch `main` setelah tahap pengujian berhasil. Docker images akan di-push ke Docker Hub dengan tag `latest` dan short SHA commit.

---

### Panduan Membuat Pull Request (PR) untuk Berkontribusi

Kami menerapkan alur kerja Git Flow yang aman dan terstruktur untuk menerima kontribusi baru. Ikuti alur berikut sebelum mengajukan penggabungan kode ke branch `main`:

1. **Perbarui Branch Main Lokal**
   Sebelum mulai menulis kode baru, pastikan branch `main` di komputer lokal Anda sinkron dengan kode terbaru di server repositori pusat:

   ```bash
   git checkout main
   git pull origin main
   ```

2. **Buat Branch Fitur Baru**
   Jangan pernah melakukan commit langsung ke branch `main`. Buat branch baru yang menjelaskan fitur atau perbaikan yang akan Anda kerjakan (misalnya, `feature/nama-fitur` atau `bugfix/nama-perbaikan`):

   ```bash
   git checkout -b feature/tambah-crud-farmer
   ```

3. **Lakukan Perubahan & Commit**
   Setelah selesai mengembangkan fitur atau memperbaiki bug di editor kode Anda, simpan perubahan tersebut dengan membuat git commit yang memiliki deskripsi jelas:

   ```bash
   git add .
   git commit -m "feat: menambahkan endpoint crud untuk data farmer"
   ```

4. **Kirim (Push) Branch ke Remote**
   Unggah branch fitur baru Anda ke server repositori remote agar dapat diakses secara online:

   ```bash
   git push origin feature/tambah-crud-farmer
   ```

5. **Buat Pull Request (PR)**
   - Buka repositori proyek Anda di browser (GitHub atau GitLab).
   - Halaman repositori Anda biasanya akan otomatis menampilkan tombol **"Compare & pull request"**. Klik tombol tersebut.
   - Jika tidak muncul, masuk ke tab **"Pull Requests"**, klik **"New Pull Request"**, lalu pilih branch fitur Anda untuk digabungkan ke branch `main`.
   - Isi judul Pull Request dan beri deskripsi penjelasan detail mengenai perubahan apa saja yang telah Anda lakukan.
   - Klik tombol **"Create pull request"**.

6. **Tinjauan Kode (Code Review)**
   - Rekan tim atau pengelola repositori akan memeriksa kode Anda.
   - Jika ada saran perubahan, lakukan perbaikan langsung di branch fitur lokal Anda, lakukan commit, lalu push kembali. Perubahan tersebut otomatis akan memperbarui halaman Pull Request.
   - Setelah disetujui (approve) dan seluruh pengujian lolos, PR Anda akan digabungkan (merge) ke branch `main`!

---

## Setup Keamanan TLS/HTTPS

Kami telah mengamankan akses API Gateway menggunakan HTTPS melalui Kubernetes Ingress dengan self-signed TLS certificate. Berikut adalah langkah-langkah lengkap untuk menyiapkan sertifikat di environment baru:

### Prasyarat

- `openssl` terinstall di sistem
- `kubectl` terhubung ke cluster Kubernetes
- Namespace `agricity` sudah ada (`kubectl apply -f k8s/namespace.yaml`)

### Langkah 1 — Generate Self-Signed Certificate

Jalankan perintah berikut di root direktori proyek untuk membuat sertifikat lokal:

```bash
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout tls.key -out tls.crt \
  -subj "/CN=103.147.92.134/O=SmartAgriCity"
```

> ⚠️ File `tls.key` dan `tls.crt` sudah masuk ke `.gitignore` — **jangan pernah di-commit ke Git**.

### Langkah 2 — Buat Kubernetes Secret

Daftarkan sertifikat ke namespace `agricity` dengan nama `agri-tls-secret`:

```bash
kubectl create secret tls agri-tls-secret \
  --cert=tls.crt --key=tls.key \
  -n agricity
```

Verifikasi secret berhasil dibuat:

```bash
kubectl get secret agri-tls-secret -n agricity
```

### Langkah 3 — Terapkan Ingress dengan TLS

File `k8s/ingress.yaml` sudah dikonfigurasi dengan blok TLS dan SSL redirect. Jalankan:

```bash
kubectl apply -f k8s/ingress.yaml -n agricity
```

Verifikasi Ingress aktif:

```bash
kubectl get ingress -n agricity
```

### Langkah 4 — Verifikasi Akses HTTPS

Gunakan flag `-k` (insecure) karena menggunakan self-signed certificate:

```bash
curl -k https://103.147.92.134/health
```

Response yang diharapkan (JSON valid dari Gateway port 3000):

```json
{ "status": "ok" }
```

### Catatan Keamanan

- Self-signed certificate diterima untuk lingkungan staging/development
- Untuk production, pertimbangkan penggunaan Let's Encrypt via cert-manager
- File `tls.key` dan `tls.crt` **tidak boleh masuk ke Git** (dilindungi via `.gitignore` pattern `*.key` dan `*.crt`)

## Lembar Contekan Makefile

- `make up` : Membangun semua microservices & mengambil (pull) base image.
- `make down` : Menghentikan dan menghapus semua jaringan, container, dan volume docker.
- `make ps` : Memeriksa status kesehatan container saat runtime.
- `make logs` : Memantau log sistem secara real-time.
- `make clean` : Membersihkan artefak build dan file cache Python.
- `make k8s-apply` : Menerapkan semua manifes langsung ke kluster.
