# Kubernetes Manifests — Smart AgriCity

Namespace: `agricity`  
Environment: Production-grade K8s deployment  
Host: `agri.kelompok1.local`

---

## Prasyarat

Sebelum deploy, pastikan sudah terinstall:

### 1. Kubernetes Cluster — Pilih Salah Satu

**Opsi A — Minikube (Recommended untuk lokal)**
```bash
# Install Minikube
winget install Kubernetes.minikube

# Start cluster (4 CPU, 6GB RAM)
minikube start --cpus=4 --memory=6144 --driver=docker

# Verify
minikube status
kubectl cluster-info
```

**Opsi B — Docker Desktop Kubernetes**
```bash
# Enable di Settings → Kubernetes → Enable Kubernetes
# Tunggu status hijau (3-5 menit)

# Verify
kubectl cluster-info
```

### 2. Ingress Controller

```bash
# Minikube (built-in)
minikube addons enable ingress
minikube addons enable metrics-server

# Docker Desktop
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/controller-v1.9.5/deploy/static/provider/baremetal/deploy.yaml
kubectl apply -f https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
```

### 3. kubectl CLI

```bash
# Verify installed
kubectl version --client

# Config context
kubectl config current-context
```

---

## Quick Start — Deploy dalam 3 Langkah

### Langkah 1 — Setup Secrets Lokal

```bash
# Copy template secrets
cp k8s/secrets.example.yaml k8s/secrets.yaml

# Encode password development (atau gunakan yang sudah ada)
# Windows PowerShell:
[Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes('password123'))
# Output: cGFzc3dvcmQxMjM=

# Edit k8s/secrets.yaml — copy paste base64 values ke field yang sesuai
# Gunakan nilai yang konsisten untuk semua password development
```

**Development values yang aman untuk testing:**
```yaml
data:
  DB_PASSWORD: cGFzc3dvcmQxMjM=                               # password123
  MYSQL_ROOT_PASSWORD: cGFzc3dvcmQxMjM=                       # password123
  JWT_SECRET: YWdyaWNpdHlfand0X3N1cGVyX3NlY3JldF9rZXlfMzJjaGFycyE= # agricity_jwt_super_secret_key_32chars!
  RABBITMQ_PASSWORD: cGFzc3dvcmQxMjM=                         # password123
  MQTT_PASSWORD: cGFzc3dvcmQxMjM=                             # password123
  GOOGLE_CLIENT_ID: cGxhY2Vob2xkZXItZ29vZ2xlLWNsaWVudC1pZA==       # placeholder
  GOOGLE_CLIENT_SECRET: cGxhY2Vob2xkZXItZ29vZ2xlLWNsaWVudC1zZWNyZXQ= # placeholder
```

### Langkah 2 — Build Docker Images

Semua service perlu Docker image terlebih dahulu:

```bash
# Arahkan Docker ke Minikube (Minikube only)
eval $(minikube docker-env)  # Linux/Mac
# Windows PowerShell:
& minikube -p minikube docker-env --shell powershell | Invoke-Expression

# Build semua images
docker build -t smart-agri/oauth-server:latest      ./oauth-server
docker build -t smart-agri/api-gateway:latest        ./express-gateway
docker build -t smart-agri/php-farmer:latest         ./php-farmer
docker build -t smart-agri/php-crop:latest           ./php-crop
docker build -t smart-agri/php-irrigation:latest     ./php-irrigation
docker build -t smart-agri/python-ml-service:latest  ./python-ml-service

# Verify
docker images | grep smart-agri
```

### Langkah 3 — Deploy ke Kubernetes

```bash
# Deploy semua manifests (urutan dijamin via kustomize)
kubectl apply -k k8s/

# Atau manual (tanpa kustomize):
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/secrets.yaml
kubectl apply -f k8s/mysql-statefulset.yaml
kubectl apply -f k8s/rabbitmq-deployment.yaml
kubectl apply -f k8s/oauth-server-deployment.yaml
kubectl apply -f k8s/gateway-deployment.yaml
kubectl apply -f k8s/python-ml-deployment.yaml
kubectl apply -f k8s/php-deployments.yaml
kubectl apply -f k8s/ingress.yaml
kubectl apply -f k8s/hpa.yaml
```

---

## Verifikasi Deployment

### 1. Cek Semua Pod Running

```bash
# Watch pods sampai semua 1/1 Running
kubectl get pods -n agricity -w

# Output yang diharapkan:
# NAME                              READY   STATUS    RESTARTS
# api-gateway-xxx (2 pods)          1/1     Running   0
# mysql-0                           1/1     Running   0
# oauth-server-xxx                  1/1     Running   0
# php-crop-xxx                      1/1     Running   0
# php-farmer-xxx                    1/1     Running   0
# php-irrigation-xxx                1/1     Running   0
# python-ml-xxx                     1/1     Running   0
# rabbitmq-xxx                      1/1     Running   0
```

### 2. Cek Services & Ingress

```bash
# Check services
kubectl get svc -n agricity

# Check ingress
kubectl get ingress -n agricity

# Expected ingress ADDRESS: 192.168.49.2 (Minikube)
```

### 3. Cek HPA Status

```bash
kubectl get hpa -n agricity

# Expected: python-ml-hpa dengan minReplicas=1, maxReplicas=5
```

### 4. Test Gateway Health

**Opsi A — Via port-forward (paling mudah)**
```bash
# Terminal 1: Setup port forward
kubectl port-forward -n agricity svc/gateway-service 3000:3000

# Terminal 2: Test health
curl http://localhost:3000/health

# Expected response:
# {
#   "status": "ok",
#   "service": "api-gateway",
#   "upstreams": [
#     { "name": "oauth-server",   "status": "up" },
#     { "name": "php-farmer",     "status": "up" },
#     { "name": "php-crop",       "status": "up" },
#     { "name": "php-irrigation", "status": "up" },
#     { "name": "python-ml",      "status": "up" }
#   ]
# }
```

**Opsi B — Via Ingress hostname**
```bash
# Update hosts file dengan Minikube IP
# Linux/Mac: /etc/hosts
# Windows: C:\Windows\System32\drivers\etc\hosts (run as admin)

# Minikube tunnel (terminal baru, biarkan terbuka)
minikube tunnel

# Edit hosts file, tambahkan:
127.0.0.1  agri.kelompok1.local

# Test
curl http://agri.kelompok1.local/health
```

---

## File Structure & Penjelasan

```
k8s/
├── namespace.yaml                # Namespace agricity & labeling
├── configmap.yaml                # Non-sensitive env vars (DB_HOST, RABBITMQ_HOST, dll)
├── secrets.yaml                  # REAL SECRETS (git-ignored) — setup lokal setiap dev
├── secrets.example.yaml          # Template placeholder — push ke repo
│
├── mysql-statefulset.yaml        # StatefulSet MySQL + PVC 5Gi + Service
├── rabbitmq-deployment.yaml      # Deployment RabbitMQ + ClusterIP + NodePort
├── oauth-server-deployment.yaml  # Deployment OAuth Server + Service
├── gateway-deployment.yaml       # Deployment API Gateway (2 replicas) + NodePort
├── python-ml-deployment.yaml     # Deployment Python ML + Service
├── php-deployments.yaml          # 3x Deployment (farmer/crop/irrigation) + Service
│
├── ingress.yaml                  # Ingress NGINX → gateway (agri.kelompok1.local)
├── hpa.yaml                      # HPA Python ML (CPU 70%, Memory 80%)
├── kustomization.yaml            # Apply order & namespace
│
└── README.md                     # Dokumentasi ini
```

---

## Common Operations

### Update ConfigMap (tanpa rebuild image)

```bash
kubectl set env configmap/agricity-config \
  NODE_ENV=development \
  -n agricity

# Trigger rolling update di deployment yang pakai configmap
kubectl rollout restart deployment/api-gateway -n agricity
```

### Update Secrets (tanpa rebuild image)

```bash
# Edit secrets.yaml
nano k8s/secrets.yaml

# Apply changes
kubectl apply -f k8s/secrets.yaml

# Trigger pod restart untuk pick up secret baru
kubectl delete pod <pod-name> -n agricity
# Pod akan auto-restart dengan secret baru
```

### Scale Manual (override HPA)

```bash
# Scale Python ML ke 3 replicas
kubectl scale deployment python-ml --replicas=3 -n agricity

# Note: HPA akan override jika CPU/Memory tidak match thresholds
```

### Rolling Update (Zero Downtime)

```bash
# Update image gateway
kubectl set image deployment/api-gateway \
  api-gateway=smart-agri/api-gateway:v1.1.0 \
  -n agricity

# Monitor rolling update
kubectl rollout status deployment/api-gateway -n agricity -w

# Rollback jika ada masalah
kubectl rollout undo deployment/api-gateway -n agricity
```

### View Logs

```bash
# Real-time logs pod
kubectl logs -f <pod-name> -n agricity

# Last 100 lines
kubectl logs --tail=100 <pod-name> -n agricity

# All pods in deployment
kubectl logs -f deployment/api-gateway -n agricity
```

### Access RabbitMQ Management UI

```bash
# Port forward ke management UI
kubectl port-forward -n agricity svc/rabbitmq-management 15672:15672

# Buka browser
http://localhost:15672

# Login: guest / password123
```

### Debug Pod

```bash
# Shell ke pod
kubectl exec -it <pod-name> -n agricity -- /bin/bash

# View pod details
kubectl describe pod <pod-name> -n agricity

# Check readiness/liveness probe
kubectl get events -n agricity --sort-by='.lastTimestamp'
```

---

## Testing & Monitoring

### Load Test HPA (Auto-Scaling)

```bash
# Terminal 1: Monitor HPA
kubectl get hpa python-ml-hpa -n agricity -w

# Terminal 2: Run load test
kubectl run load-test \
  --image=busybox \
  --restart=Never \
  -n agricity \
  --rm -it \
  -- /bin/sh -c "while true; do wget -q -O- http://python-ml-service:5000/health; done"

# Lihat REPLICAS naik dari 1 → 2 → 3 saat CPU naik > 70%
# Turun kembali ke 1 setelah beban turun (~5 menit)

# Stop load test
# Ctrl+C di terminal 2, pod otomatis cleanup
```

### Test Pod Failure Recovery

```bash
# Terminal 1: Monitor pods
kubectl get pods -n agricity -w

# Terminal 2: Kill pod gateway
kubectl delete pod <api-gateway-pod-name> -n agricity

# Lihat di Terminal 1: pod langsung di-replace dengan pod baru
# User tidak merasa downtime karena gateway ada 2 replicas
```

### Test Rolling Update (Zero Downtime)

```bash
# Terminal 1: Monitor deployment
kubectl rollout status deployment/api-gateway -n agricity -w

# Terminal 2: Trigger restart
kubectl rollout restart deployment/api-gateway -n agricity

# Lihat: pod bergantian di-replace, tidak ada saat keduanya down
```

---

## Troubleshooting

| Masalah | Debug | Solusi |
|---|---|---|
| Pod `CrashLoopBackOff` | `kubectl logs <pod>` | Cek env vars, database connection |
| Pod `ImagePullBackOff` | `docker images` di Minikube | Rebuild image atau eval docker-env |
| Ingress tidak accessible | `kubectl get ingress` | Jalankan `minikube tunnel` atau port-forward |
| HPA tidak scale | `kubectl describe hpa` | Pastikan metrics-server running |
| `Connection refused` | `kubectl get svc` | Pastikan semua service up |
| MySQL pod `Running` tapi db tidak ready | `kubectl logs mysql-0` | Tunggu ~1 menit untuk initialization |

---

## Security — Production Checklist

**Development (sekarang):**
- ✅ Secrets di file YAML (git-ignored)
- ✅ Base64 encoded (bukan encrypted)
- ⚠️ Hanya untuk lokal, jangan publish ke public

**Production upgrade (diperlukan):**
- ☐ Sealed Secrets atau HashiCorp Vault
- ☐ Private Docker registry (ECR, GCR, Harbor)
- ☐ RBAC (Role-Based Access Control)
- ☐ Network policies untuk pod-to-pod communication
- ☐ Pod security policies
- ☐ TLS/HTTPS di Ingress (cert-manager)

---

## Struktur Deployment

```
┌─────────────────────────────────────────────┐
│       Kubernetes Cluster (agricity)         │
├─────────────────────────────────────────────┤
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │  Ingress NGINX                      │   │
│  │  (agri.kelompok1.local:80)          │   │
│  └────────────────┬────────────────────┘   │
│                   │                        │
│  ┌────────────────▼────────────────────┐   │
│  │  API Gateway (2 replicas)           │   │
│  │  Service: gateway-service:3000      │   │
│  │  RollingUpdate (zero-downtime)      │   │
│  └────────────────┬────────────────────┘   │
│                   │                        │
│  ┌────────────────▼─────────────────────┐  │
│  │     Internal Service Mesh            │  │
│  │ ┌───────────┐ ┌──────────────────┐  │  │
│  │ │OAuth      │ │Python ML         │  │  │
│  │ │3002       │ │5000 (HPA: 1-5)   │  │  │
│  │ └───────────┘ └──────────────────┘  │  │
│  │ ┌──────────────────────────────────┐ │  │
│  │ │PHP Services (farmer/crop/irrig) │ │  │
│  │ │8000/8001/8002                   │ │  │
│  │ └──────────────────────────────────┘ │  │
│  └──────────────────────────────────────┘  │
│                                             │
│  ┌──────────────────────────────────────┐  │
│  │    Data Layer                        │  │
│  │ ┌──────────┐  ┌────────────────────┐│  │
│  │ │MySQL     │  │RabbitMQ            ││  │
│  │ │StatefulSet   Message Broker      ││  │
│  │ │PVC: 5Gi  │  │ClusterIP: 5672     ││  │
│  │ └──────────┘  │NodePort: 31672     ││  │
│  │               └────────────────────┘│  │
│  └──────────────────────────────────────┘  │
│                                             │
└─────────────────────────────────────────────┘
```

---

## Next Steps

1. **Development local:** Run testing sesuai section "Testing & Monitoring"
2. **Team setup:** Copy `secrets.example.yaml` → `secrets.yaml` di masing-masing dev
3. **Staging:** Deploy ke staging cluster dengan Sealed Secrets
4. **Production:** Setup proper monitoring, logging (ELK/Prometheus), dan auto-backup

---

## Resources

- [Kubernetes Official Docs](https://kubernetes.io/docs/)
- [kubectl Cheatsheet](https://kubernetes.io/docs/reference/kubectl/cheatsheet/)
- [Minikube](https://minikube.sigs.k8s.io/)
- [NGINX Ingress](https://kubernetes.github.io/ingress-nginx/)
- [Sealed Secrets](https://github.com/bitnami-labs/sealed-secrets)

---

**Last updated:** June 2026  
**Status:** Production-ready for agriCity platform
