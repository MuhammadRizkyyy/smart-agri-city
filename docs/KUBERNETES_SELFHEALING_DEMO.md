# Kubernetes Self-Healing Demo Guide
## AgriCity Platform - Docker Desktop Kubernetes

**Objective:** Demonstrasi fitur self-healing Kubernetes dimana pod yang mati otomatis di-recreate oleh Kubernetes untuk menjaga availability aplikasi.

**Environment:** Docker Desktop Kubernetes (agricity namespace)

**Duration:** ~10-15 menit

---

## Prerequisites

Pastikan sudah selesai setup sebelum mulai demo:

```bash
# 1. Cek Kubernetes cluster running
kubectl cluster-info

# 2. Cek namespace agricity ada
kubectl get namespace agricity

# 3. Cek pods agricity-demo running
kubectl get pods -n agricity
```

Expected output:
```
NAME                             READY   STATUS    RESTARTS   AGE
agricity-demo-85745b5694-hft4f   1/1     Running   0          2m
agricity-demo-85745b5694-k2c2p   1/1     Running   0          2m
agricity-demo-85745b5694-mngbs   1/1     Running   0          2m
```

Kalau belum ada, deploy dulu:
```bash
kubectl apply -f k8s/agricity-demo-deployment.yaml
kubectl apply -f k8s/dashboard-admin.yaml
```

---

## Setup: Kubernetes Dashboard

### Step 1 — Start Port-Forward

Jalankan di terminal (biarkan terbuka):

```bash
kubectl port-forward -n kubernetes-dashboard svc/kubernetes-dashboard 8443:443
```

Expected output:
```
Forwarding from 127.0.0.1:8443 -> 8443
Forwarding from [::1]:8443 -> 8443
```

### Step 2 — Generate Access Token

Di terminal lain, generate token untuk login:

```bash
kubectl -n kubernetes-dashboard create token admin-user --duration=24h
```

Copy token yang di-output (token panjang).

### Step 3 — Login ke Dashboard

1. Buka browser: **`https://localhost:8443`**
2. Browser akan warning tentang SSL certificate → Klik **Advanced → Proceed to localhost (unsafe)**
3. Di login page, pilih **Token** → Paste token dari step 2
4. Klik **Sign In**

Dashboard akan terbuka dengan tampilan Overview.

### Step 4 — Navigate ke Pods

1. Di sidebar kiri, pastikan namespace dipilih **`agricity`** (dropdown atas)
2. Klik **Workloads** → **Deployments** 
3. Klik deployment **`agricity-demo`**
4. Lihat **3 pods running** di bawah

---

## Demo Flow: Delete Pod & Watch Self-Healing

### Scenario 1: Delete 1 Pod (Single Pod Failure)

**Tujuan:** Tunjukkan Kubernetes otomatis recreate pod yang dihapus

**Langkah:**

1. **Lihat pods awal**
   - Dashboard menunjukkan 3 pods: `agricity-demo-xxx-hft4f`, `agricity-demo-xxx-k2c2p`, `agricity-demo-xxx-mngbs`
   - Semua status **Running** (hijau ✅)

2. **Delete 1 pod**
   - Klik pod pertama (`agricity-demo-xxx-hft4f`)
   - Klik tombol **Delete** (ikon tempat sampah 🗑️) di atas
   - Klik **Delete** di confirmation dialog
   - Pod akan **hilang dari list**

3. **Lihat self-healing terjadi**
   - Dashboard menunjukkan **2 pods** sebentar
   - Dalam **2-3 detik**, pod baru muncul dengan nama baru (misal: `agricity-demo-xxx-nw9xz`)
   - Status pod baru: `ContainerCreating` → `Running` (beberapa detik)
   - Total pods kembali menjadi **3** ✅

4. **Verifikasi di Terminal**

   Buka terminal baru, jalankan:
   ```bash
   kubectl get pods -n agricity -w
   ```
   
   Lihat real-time update:
   ```
   NAME                             READY   STATUS              RESTARTS   AGE
   agricity-demo-85745b5694-hft4f   1/1     Terminating         0          5m
   agricity-demo-85745b5694-k2c2p   1/1     Running             0          5m
   agricity-demo-85745b5694-mngbs   1/1     Running             0          5m
   agricity-demo-85745b5694-nw9xz   0/1     ContainerCreating   0          2s
   agricity-demo-85745b5694-nw9xz   1/1     Running             0          4s
   ```

---

### Scenario 2: Delete Multiple Pods (Cascading Failure)

**Tujuan:** Tunjukkan Kubernetes handle multiple pod failures sekaligus

**Langkah:**

1. **Delete 2 pods sekaligus**
   - Pilih 2 pods di dashboard → Klik Delete di masing-masing

2. **Lihat recovery**
   - Dashboard akan menunjukkan 1 pod sebentar
   - Dalam **5-10 detik**, 2 pods baru muncul
   - Total pods kembali ke **3** ✅

3. **Terminal view:**
   ```bash
   # Jalankan di terminal
   kubectl get pods -n agricity -w
   
   # Lihat: 2 pod Terminating, 2 pod baru ContainerCreating, lalu semuanya Running
   ```

---

### Scenario 3: Delete All Pods (Complete Failure)

**Tujuan:** Demonstrasi Kubernetes recovery dari complete failure

**Langkah:**

1. **Delete SEMUA pods**
   ```bash
   kubectl delete pods --all -n agricity
   ```

2. **Lihat dashboard/terminal**
   - Semua pods akan Terminating
   - Dalam **5-7 detik**, 3 pods baru dibuat
   - Semuanya Running lagi ✅

3. **Terminal view:**
   ```
   NAME                             READY   STATUS              RESTARTS   AGE
   agricity-demo-85745b5694-hft4f   1/1     Terminating         0          10m
   agricity-demo-85745b5694-k2c2p   1/1     Terminating         0          10m
   agricity-demo-85745b5694-mngbs   1/1     Terminating         0          10m
   agricity-demo-85745b5694-pq1rs   0/1     ContainerCreating   0          1s
   agricity-demo-85745b5694-tu2vw   0/1     ContainerCreating   0          1s
   agricity-demo-85745b5694-xy3za   0/1     ContainerCreating   0          1s
   agricity-demo-85745b5694-pq1rs   1/1     Running             0          4s
   agricity-demo-85745b5694-tu2vw   1/1     Running             0          5s
   agricity-demo-85745b5694-xy3za   1/1     Running             0          6s
   ```

---

## Understanding What's Happening

### Kubernetes Architecture During Self-Healing

```
┌─────────────────────────────────────────────────┐
│ Deployment: agricity-demo                       │
│ Configuration: replicas = 3                     │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ ReplicaSet: agricity-demo-85745b5694            │
│ Role: Ensure 3 pods are always running          │
│ Status: "Saya harus punya 3 pods"               │
└──────────────────┬──────────────────────────────┘
                   │
        ┌──────────┼──────────┐
        ▼          ▼          ▼
    ┌────────┬────────┬────────┐
    │ Pod 1  │ Pod 2  │ Pod 3  │  ← When pod deleted here
    │Running │Running │Running │     ReplicaSet detects: "only 2 pods"
    │        │        │  ❌    │     and creates new pod immediately
    └────────┴────────┴────────┘
```

### Self-Healing Timeline

```
t=0s    : [3 pods Running] ← Delete 1 pod
t=1s    : [2 pods Running] + [1 pod Terminating]
t=2s    : [2 pods Running] + [1 pod ContainerCreating] ← NEW POD
t=5s    : [3 pods Running] ✅
```

---

## Key Concepts Demonstrated

| Konsep | Penjelasan | Terlihat Saat |
|---|---|---|
| **ReplicaSet** | Selalu maintain target replicas count | Pod langsung di-recreate |
| **Pod Lifecycle** | Pod memiliki status: Pending → Running → Terminating → Deleted | Lihat status perubahan di dashboard |
| **Self-Healing** | Kubernetes otomatis repair sistem dari pod failure | Pod baru muncul tanpa manual intervention |
| **Zero Downtime** | Deployment tetap serve traffic saat pod di-replace | 2 pod lain tetap Running |
| **Health Checks** | Liveness & Readiness probes memvalidasi pod health | Pod hanya counted "Running" setelah probes pass |

---

## Terminal Commands for Real-Time Monitoring

### Watch Pods Real-Time

```bash
# Continuous watch semua pods di namespace agricity
kubectl get pods -n agricity -w

# Watch dengan lebih detail
kubectl get pods -n agricity -o wide -w

# Watch spesifik deployment
kubectl get deployment agricity-demo -n agricity -w
```

### See Events (What Kubernetes Did)

```bash
# Events di namespace agricity
kubectl get events -n agricity --sort-by='.lastTimestamp'

# Real-time events
kubectl get events -n agricity -w

# Events dari specific pod
kubectl describe pod <pod-name> -n agricity
```

### Describe Resources

```bash
# Detail deployment
kubectl describe deployment agricity-demo -n agricity

# Detail specific pod
kubectl describe pod <pod-name> -n agricity

# Detail replicaset
kubectl get replicaset -n agricity
kubectl describe replicaset <replicaset-name> -n agricity
```

---

## Troubleshooting Demo

| Masalah | Solusi |
|---|---|
| Dashboard URL error 404 | Pastikan port-forward masih jalan: `kubectl port-forward -n kubernetes-dashboard svc/kubernetes-dashboard 8443:443` |
| Token expired | Generate token baru: `kubectl -n kubernetes-dashboard create token admin-user --duration=24h` |
| Pods tidak muncul di dashboard | Refresh browser, atau ganti namespace di dropdown |
| Pod tidak auto-recreate | Cek: `kubectl describe deployment agricity-demo -n agricity` — pastikan replicas konfigurasi >= 1 |
| Pods stuck di "Pending" | Cek resources: `kubectl top pods -n agricity` atau `kubectl top nodes` |

---

## Demo Script (Presenter Version)

Gunakan script ini untuk demo yang smooth:

```
1. "Saya punya 3 pods agricity-demo yang running di Kubernetes"
   → Tunjukkan dashboard, 3 pods hijau

2. "Mari saya delete salah satu pod ini untuk simulate pod crash"
   → Delete 1 pod via dashboard

3. "Lihat — pod langsung hilang dari list"
   → Pod masuk status Terminating

4. "Sekarang watch — dalam beberapa detik, Kubernetes akan auto-create pod baru"
   → Tunggu 2-3 detik, pod baru muncul

5. "Pod baru sudah Running, dan total pods kembali 3"
   → Tunjukkan 3 pods Running lagi

6. "Ini adalah fitur Self-Healing Kubernetes — Kubernetes automatically 
   memastikan jumlah pods sesuai dengan configuration (replicas: 3)"
   
7. "Mari coba delete semua pods sekaligus untuk stress test"
   → `kubectl delete pods --all -n agricity`

8. "Lihatlah — semua pods Terminating. Tapi ReplicaSet langsung create 3 pods baru"
   → Lihat di dashboard atau terminal

9. "Dalam 7 detik, semua pods Running lagi. Inilah self-healing pada production deployment"
```

---

## Production Implications

Demo ini menunjukkan fitur Kubernetes yang **real** dan **production-grade**:

- **High Availability:** Aplikasi selalu tersedia meski pod mati
- **Automatic Recovery:** Tidak perlu manual restart
- **Resilience:** Infrastruktur bisa handle failure
- **Load Balancing:** Traffic di-route ke pods yang healthy (via Service)

Konfigurasi di `k8s/agricity-demo-deployment.yaml` adalah production-ready:
- ✅ RollingUpdate strategy (zero-downtime deployment)
- ✅ Resource requests/limits (prevent resource starvation)
- ✅ Liveness & Readiness probes (health checks)
- ✅ Graceful termination (terminationGracePeriodSeconds: 30)

---

## Next Steps

Setelah paham self-healing, pelajari:
1. **Horizontal Pod Autoscaler (HPA)** — Auto-scale pods berdasarkan CPU/Memory
2. **Rolling Updates** — Zero-downtime deployment upgrades
3. **Service Mesh** — Advanced traffic management (istio di `k8s/istio/`)
4. **Monitoring & Logging** — Prometheus + Grafana di `monitoring/`

Lihat `k8s/README.md` untuk dokumentasi lengkap.

---

**Created:** June 2026  
**For:** AgriCity Platform Kubernetes Demo  
**Status:** Production-Ready Configuration
