# Penjelasan Desain Database AgriCity

## Ringkasan Struktur Database

Database AgriCity dirancang dengan **microservices architecture** menggunakan **service-based schema pattern**. Setiap service memiliki prefik tersendiri (irr_, frm_, crp_, oauth_) untuk memisahkan concern dan memudahkan skalabilitas.

---

## 1. IRRIGATION SERVICE (irr_)

### Tabel: `irr_zones`
**Fungsi**: Menyimpan data zona irigasi fisik di lapangan

| Field | Alasan |
|-------|--------|
| `id` | Primary key untuk identifikasi unik zona |
| `name` | Nama identitas zona (e.g., "Sawah Utara", "Ladang Barat") untuk kemudahan referensi |
| `area_ha` | Luas dalam hektar untuk perhitungan efisiensi irigasi dan analisis produktivitas |
| `status` | Track apakah zona aktif/nonaktif (bisa rusak/maintenance) |
| `lat, lng` | Koordinat GPS untuk pemetaan lokasi nyata zona di lapangan |
| `flow_rate_liters_per_minute` | Kapasitas pompa/debit untuk mengatur volume irigasi otomatis |
| `created_at` | Audit trail waktu zona dibuat |

**Mengapa tabel ini penting**: Adalah backbone sistem irigasi otomatis. Mengetahui lokasi fisik, kapasitas, dan status zona adalah fondasi untuk mengontrol pompa dan mengoptimalkan air.

---

### Tabel: `irr_sensor_readings`
**Fungsi**: Log real-time pembacaan sensor dari IoT devices di lapangan

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `zone_id` | Foreign key ke irr_zones untuk mengetahui pembacaan berasal dari zona mana |
| `moisture` | Kelembaban tanah (%) - KUNCI untuk memutuskan kapan menyiram |
| `temperature` | Suhu tanah untuk analisis kondisi tumbuhan |
| `ph` | Keasaman tanah penting untuk jenis tanaman tertentu |
| `nitrogen, phosphorus, potassium` | NPK - nutrisi tanah esensial yang perlu dimonitor |
| `air_temp` | Suhu udara untuk korelasi dengan evaporasi air |
| `air_humidity` | Kelembaban udara untuk prediksi cuaca & penyakit tanaman |
| `light_lux` | Intensitas cahaya untuk crops yang sensitif pencahayaan |
| `recorded_at` | Timestamp pembacaan untuk time-series analysis & machine learning |

**Mengapa tabel ini penting**: Adalah data mentah dari sensor. Data ini digunakan untuk:
- Machine learning model prediksi kebutuhan air
- Alert system deteksi anomali (pH terlalu rendah, moisture kritis)
- Historical analysis untuk improvement

---

### Tabel: `irr_irrigation_logs`
**Fungsi**: Mencatat setiap aktivitas penyiraman (kapan mulai, kapan selesai, volume)

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `zone_id` | Zona mana yang disiram |
| `started_at` | Waktu pompa mulai aktif |
| `ended_at` | Waktu pompa berhenti (NULL jika masih running) |
| `volume_liters` | Total air yang digunakan untuk cost analysis & efisiensi |
| `trigger_type` | ENUM(manual/otomatis_ml/otomatis_jadwal) - untuk audit trail keputusan irigasi |
| `created_at` | Waktu log tercatat |

**Mengapa tabel ini penting**: 
- Transparency: Petani bisa lihat siapa yg nyiram, kapan, dan berapa air
- Cost tracking: Hitung pemakaian air & biaya operasional
- Model validation: Bandingkan prediksi ML dengan hasil aktual

---

## 2. FARMER SERVICE (frm_)

### Tabel: `frm_farmers`
**Fungsi**: Master data petani/pengguna sistem

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `name` | Nama petani untuk identifikasi |
| `email` | Kontak digital, bisa NULL kalau ada nomor HP saja |
| `password` | Hash password untuk login (nullable karena bisa login via Google) |
| `google_id` | ID Google untuk OAuth login (modern auth) |
| `avatar` | URL foto profil dari Google atau upload |
| `role` | ENUM(petani/petugas/admin) untuk access control & authorization |
| `nik` | Nomor Induk Kependudukan (unique) untuk verifikasi identitas KTP |
| `phone` | Nomor telepon untuk komunikasi & notifikasi |
| `address` | Alamat fisik untuk referensi lokasi |
| `created_at, updated_at` | Audit trail perubahan data |
| `deleted_at` | Soft delete - data tidak benar-benar dihapus (compliance & historical reference) |

**Mengapa tabel ini penting**: 
- Single source of truth untuk pengguna
- Multi-authentication (email/password + Google OAuth)
- Role-based access control (RBAC)
- NIK untuk compliance/legal requirement

---

### Tabel: `frm_lands`
**Fungsi**: Data lahan yang dimiliki/dikelola petani

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `farmer_id` | Foreign key - lahan milik petani mana |
| `zone_id` | FK ke irr_zones - mapping lahan fisik ke zona irigasi |
| `name` | Nama lahan (e.g., "Sawah Timur", "Greenhouse Tomat") |
| `area_ha` | Luas lahan untuk perhitungan yield per hectare (produktivitas) |
| `soil_type` | Jenis tanah (clay/sandy/loam) mempengaruhi kebutuhan air & nutrisi |
| `lat, lng` | Koordinat untuk pemetaan & IoT device placement |
| `created_at` | Audit trail |

**Mengapa tabel ini penting**: 
- Link antara petani → lahan → zona irigasi
- Soil type penting untuk rekomendasi irigasi (clay retain water lebih lama)
- Location data untuk precision agriculture

---

### Tabel: `frm_harvests`
**Fungsi**: Catatan hasil panen per lahan & musim

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `land_id` | Lahan mana yang dipanen |
| `crop_type` | Jenis tanaman (padi/jagung/tomat) untuk tracking |
| `yield_ton` | Hasil panen dalam ton untuk productivity metrics |
| `harvest_date` | Tanggal panen untuk seasonal analysis |
| `notes` | Catatan tambahan (kondisi, masalah, treatment) |
| `created_at` | Audit trail |

**Mengapa tabel ini penting**: 
- ROI analysis: bandingkan input (air, pupuk) dengan output (yield)
- Seasonal planning: kapan harus mulai tanam musim depan
- Machine learning features: prediksi hasil berdasarkan sensor readings

---

## 3. CROP SERVICE (crp_)

### Tabel: `crp_crop_schedules`
**Fungsi**: Rencana tanam & tumbuh tanaman per lahan

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `land_id` | Lahan mana yang direncanakan |
| `crop_type` | Jenis tanaman |
| `plant_date` | Tanggal mulai tanam (kalender pertanian) |
| `expected_harvest` | Target panen untuk planning (panen tepat waktu = kualitas lebih baik) |
| `growth_phase` | Fase tumbuh (vegetatif/generatif/matang) - berpengaruh pada kebutuhan air |
| `created_at` | Audit trail |

**Mengapa tabel ini penting**: 
- Knowledge base: tahu fase tumbuh mana diperlukan berapa banyak air
- Alert system: bisa trigger rekomendasi irigasi berbeda per fase
- Automation: ML model berbeda per fase & crop type

---

### Tabel: `crp_alerts`
**Fungsi**: Alert/notifikasi masalah kondisi tanah/lingkungan

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `zone_id` | Zone mana yang bermasalah |
| `alert_type` | Jenis alert (pH_rendah, moisture_kritis, N_defisiensi) |
| `severity` | ENUM(rendah/sedang/tinggi/kritis) untuk prioritas respons |
| `description` | Detail masalah & rekomendasi (e.g., "pH 4.2, perlu kapur") |
| `resolved_at` | Kapan masalah teratasi (NULL jika belum) |
| `created_at` | Waktu alert terdeteksi |

**Mengapa tabel ini penting**: 
- Early warning system: cegah masalah sebelum panen rusak
- Actionable insights: memberikan rekomendasi konkret ke petani
- Severity-based routing: alert kritis langsung ke WhatsApp/SMS

---

### Tabel: `crp_soil_conditions`
**Fungsi**: Riwayat analisis laboratorium kondisi tanah

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `land_id` | Lahan mana yang dianalisis |
| `ph, nitrogen, phosphorus, potassium` | Hasil lab NPK - baseline untuk irigasi & pupuk |
| `recorded_at` | Kapan analisis dilakukan (biasanya per musim) |

**Mengapa tabel ini penting**: 
- Baseline data: tahu kondisi awal tanah sebelum tanam
- Precision fertilization: tahu perlu tambah berapa NPK
- Correlation analysis: bandingkan hasil lab dengan sensor readings

---

## 4. OAUTH SERVICE (oauth_)

### Tabel: `oauth_clients`
**Fungsi**: Daftar aplikasi/service yang boleh akses API

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `client_id` | ID unik aplikasi (e.g., "mobile-app", "web-dashboard") |
| `client_secret` | Password aplikasi (hash) untuk server-to-server auth |
| `grant_types` | Jenis OAuth flow yang diizinkan (password, client_credentials, refresh_token) |
| `redirect_uri` | URL callback untuk OAuth redirect (security check) |

**Mengapa tabel ini penting**: 
- Multi-app ecosystem: bisa ada mobile app, web dashboard, IoT gateway dengan permission berbeda
- Security: client_secret untuk mencegah unauthorized access

---

### Tabel: `oauth_tokens`
**Fungsi**: Session/token management untuk access control

| Field | Alasan |
|-------|--------|
| `id` | Primary key |
| `client_id` | Aplikasi mana yang login |
| `user_id` | Petani mana yang login (FK ke frm_farmers) |
| `access_token` | JWT token untuk request (short-lived, ~1 jam) |
| `refresh_token` | Token untuk refresh access token tanpa login ulang (long-lived) |
| `expires_at` | Kapan access_token expire → force re-login untuk security |
| `refresh_token_expires_at` | Kapan refresh token expire → user harus login manual ulang |

**Mengapa tabel ini penting**: 
- Stateful token management: server bisa revoke token kapan saja
- Audit trail: lihat siapa login kapan & dari aplikasi mana
- Security: expiry time mencegah token digunakan selamanya

---

## Desain Pattern Explanations

### 1. **Service-Based Prefix (irr_, frm_, crp_, oauth_)**
**Mengapa?**
- Multi-microservices architecture: setiap service punya database sendiri
- Easy scaling: service irigasi bisa di-scale terpisah dari service farmer
- Data consistency: jelas mana table yang belong ke service mana
- Team ownership: team irrigation focus ke irr_* tables

### 2. **Soft Delete (deleted_at)**
**Hanya di `frm_farmers` - Mengapa?**
- Compliance: bisa audit history akun tertentu
- Data preservation: jika delete hard, kehilangan history harvest/loans
- GDPR-ready: bisa implement data retention policy

### 3. **Foreign Keys dengan CASCADE**
**Mengapa?**
- Data integrity: jika lahan dihapus, harvest juga otomatis dihapus (no orphaned data)
- Consistency: jangan bisa ada harvest untuk lahan yg sudah tidak ada

### 4. **Timestamps (created_at, updated_at)**
**Mengapa?**
- Audit trail: track kapan data dibuat/diubah
- Machine learning: temporal analysis (learning pattern across seasons)
- Debugging: trace bug based on kapan terjadi

### 5. **Indexes (INDEX)**
**Mengapa?**
- Query performance: lookup zone_id atau farmer_id jadi cepat
- Composite indexes (zone_id, recorded_at): optimize time-series queries untuk sensor readings

---

## Contoh Jawaban untuk Dosen

### Pertanyaan: "Kenapa harus pisah irr_zones dan frm_lands?"

**Jawab:**
> "Zone irigasi adalah infrastruktur fisik (pump, pipe, control valve) yang bisa melayani multiple petani. Sedangkan land adalah property petani. Satu petani bisa punya lahan di zona berbeda, dan satu zona bisa melayani lahan dari multiple petani. Schema ini flexible untuk skenario real-world. Zone fokus ke hardware/automation, land fokus ke ownership/productivity."

---

### Pertanyaan: "Kenapa banyak sekali field di irr_sensor_readings?"

**Jawab:**
> "Setiap field adalah input untuk machine learning model & alert system kami:
> - Moisture + temperature + humidity → predict plant water stress
> - NPK + pH → detect nutrient deficiency
> - Light_lux → identify pest risk (disease prefer low light)
> - Air_humidity → predict fungal disease probability
>
> Ini comprehensive sensor data untuk precision agriculture. Tidak semua field digunakan setiap saat, tapi ada untuk flexibility future features."

---

### Pertanyaan: "Kenapa ada oauth_clients dan oauth_tokens?"

**Jawab:**
> "System kami support multiple clients (mobile app, web dashboard, IoT gateway sensor). oauth_clients adalah registry aplikasi yang boleh akses API. oauth_tokens adalah session management.
>
> Contoh: Mobile app petani login → dapat access_token → pakai token untuk request data → server check di oauth_tokens table apakah token valid dan user_id apa.
>
> Ini pattern industry standard (like Google OAuth) untuk stateful token management & audit trail."

---

### Pertanyaan: "Kenapa harvest di tabel terpisah, bukan column di frm_lands?"

**Jawab:**
> "Karena satu lahan bisa panen multiple crops dalam setahun (crop rotation). Jika harvest di-embed dalam lands, jadi bloated & sulit query history. Dengan table terpisah, bisa:
> - Track yield trends per crop type
> - Analyze seasonal patterns
> - Query 'berapa banyak padi dipanen dalam 3 tahun terakhir' dengan mudah"

---

## Key Takeaway

Database dirancang dengan **3 prinsip utama**:

1. **Modularity**: Service-based prefix memudahkan microservices scaling
2. **Auditability**: Timestamps & soft delete untuk compliance & debugging
3. **Feature Support**: Field dipilih spesifik untuk IoT ingestion, ML training, & alert system

Bukan "random fields", tapi setiap field ada purpose untuk support business logic & technical features.
