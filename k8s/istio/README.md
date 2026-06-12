# Istio Service Mesh — Namespace `agricity`

Implementasi Istio Service Mesh sesuai **PLAN.md §16.2 Poin Bonus**.

## Prerequisites

- Kubernetes cluster running (semua pod di `agricity` harus `Running`)
- `istioctl` terinstall di PATH
- Minimum RAM server: 4 GB (jika < 4 GB, gunakan Linkerd — lihat bagian bawah)

---

## Langkah Instalasi

### 1. Install Istio (profile=demo)

```bash
# Download Istio 1.20.x
curl -L https://istio.io/downloadIstio | ISTIO_VERSION=1.20.0 sh -

# Tambah ke PATH
export PATH=$PWD/istio-1.20.0/bin:$PATH

# Install Istio ke cluster
istioctl install --set profile=demo -y

# Verifikasi
kubectl get pods -n istio-system
# istiod, istio-ingressgateway, istio-egressgateway harus Running
```

### 2. Label Namespace & Re-deploy

```bash
# Enable sidecar injection di namespace agricity
kubectl label namespace agricity istio-injection=enabled

# Verifikasi label
kubectl get namespace agricity --show-labels

# Restart semua deployment agar sidecar di-inject
kubectl rollout restart deployment -n agricity

# Verifikasi semua pod READY 2/2 (app + istio-proxy)
kubectl get pods -n agricity
# Contoh output yang diharapkan:
# NAME                           READY   STATUS    RESTARTS
# api-gateway-xxx                2/2     Running   0
# oauth-server-xxx               2/2     Running   0
# php-farmer-xxx                 2/2     Running   0
# php-crop-xxx                   2/2     Running   0
# php-irrigation-xxx             2/2     Running   0
# python-ml-xxx                  2/2     Running   0
```

### 3. Deploy Istio Manifests

```bash
# Apply semua Istio resources (Gateway, VirtualService, DestinationRule, PeerAuthentication)
kubectl apply -k k8s/istio/

# Atau apply per file:
kubectl apply -f k8s/istio/gateway.yaml
kubectl apply -f k8s/istio/virtual-service.yaml
kubectl apply -f k8s/istio/destination-rule.yaml
kubectl apply -f k8s/istio/peer-authentication.yaml

# Verifikasi resources
kubectl get gateway,virtualservice,destinationrule,peerauthentication -n agricity
```

### 4. Label Pod Canary untuk Traffic Splitting

Canary routing di `virtual-service.yaml` membutuhkan pod dengan label `version`:

```bash
# Label pod stable (existing api-gateway pods)
kubectl label pods -n agricity -l app=api-gateway version=stable

# Untuk deploy canary: buat deployment baru dengan label version=canary
# Contoh patch cepat (test):
kubectl patch deployment api-gateway -n agricity --type=json \
  -p='[{"op":"add","path":"/spec/template/metadata/labels/version","value":"stable"}]'
```

### 5. Deploy Observability Addons

```bash
# Kiali — service graph
kubectl apply -f https://raw.githubusercontent.com/istio/istio/release-1.20/samples/addons/kiali.yaml
kubectl rollout status deployment/kiali -n istio-system

# Jaeger — distributed tracing
kubectl apply -f https://raw.githubusercontent.com/istio/istio/release-1.20/samples/addons/jaeger.yaml
kubectl rollout status deployment/jaeger -n istio-system

# Prometheus (Istio metrics — jika belum ada)
kubectl apply -f https://raw.githubusercontent.com/istio/istio/release-1.20/samples/addons/prometheus.yaml

# Grafana (opsional — jika ingin dashboard Istio)
kubectl apply -f https://raw.githubusercontent.com/istio/istio/release-1.20/samples/addons/grafana.yaml

# Akses Kiali (port-forward)
kubectl port-forward svc/kiali 20001:20001 -n istio-system

# Akses Jaeger
kubectl port-forward svc/tracing 16686:80 -n istio-system
```

### 6. Verifikasi mTLS

```bash
# Check mTLS status semua service di agricity
istioctl authn tls-check \
  $(kubectl get pod -n agricity -l app=api-gateway -o jsonpath='{.items[0].metadata.name}').agricity

# Verifikasi PeerAuthentication aktif
kubectl get peerauthentication -n agricity

# Cek mTLS untuk service spesifik
istioctl authn tls-check \
  $(kubectl get pod -n agricity -l app=python-ml -o jsonpath='{.items[0].metadata.name}').agricity \
  python-ml-service.agricity.svc.cluster.local
```

Output yang diharapkan: semua service menunjukkan `OK` dengan mode `mTLS`.

### 7. Verifikasi Circuit Breaker (python-ml)

```bash
# Test circuit breaker dengan inject fault
kubectl apply -f - <<EOF
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: python-ml-fault-test
  namespace: agricity
spec:
  hosts:
  - python-ml-service.agricity.svc.cluster.local
  http:
  - fault:
      abort:
        percentage:
          value: 100
        httpStatus: 503
    route:
    - destination:
        host: python-ml-service.agricity.svc.cluster.local
        port:
          number: 5000
EOF

# Kirim 6+ request dan lihat circuit terbuka di Kiali
# Setelah test, hapus fault injection:
kubectl delete virtualservice python-ml-fault-test -n agricity
```

### 8. Verifikasi Prometheus Metrics

```bash
# Query Istio request metrics
kubectl port-forward svc/prometheus 9090:9090 -n istio-system

# Di browser: http://localhost:9090
# Query: istio_requests_total{destination_service=~".*agricity.*"}
# Query: istio_request_duration_milliseconds_bucket{destination_service="python-ml-service.agricity.svc.cluster.local"}
```

---

## File Structure

```
k8s/istio/
├── gateway.yaml             # Istio Gateway + VirtualService ingress eksternal
├── virtual-service.yaml     # Canary routing api-gateway (90/10) + internal services
├── destination-rule.yaml    # Circuit breaker python-ml + connection pool semua service
├── peer-authentication.yaml # mTLS STRICT namespace agricity + AuthorizationPolicy
├── kustomization.yaml       # kubectl apply -k k8s/istio/
└── README.md                # Dokumen ini
```

---

## Arsitektur Traffic Flow

```
Internet / External
        │
        ▼
Istio IngressGateway (port 80/443)
  [agricity-gateway]
        │
        ▼ (agricity-ingress-vs → gateway-service:3000)
api-gateway [2/2 w/ sidecar]
        │
        ├──→ oauth-server-service:3002   [mTLS]
        ├──→ farmer-service:8000         [mTLS]
        ├──→ crop-service:8001           [mTLS]
        ├──→ irrigation-service:8002     [mTLS]
        └──→ python-ml-service:5000      [mTLS + Circuit Breaker]
                  │
                  └── (RabbitMQ agri.events → async)
```

---

## Alternatif: Linkerd (jika RAM < 4 GB)

Linkerd lebih ringan (~200MB RAM vs Istio ~1GB):

```bash
# Install Linkerd CLI
curl -sL run.linkerd.io/install | sh
export PATH=$PATH:$HOME/.linkerd2/bin

# Pre-check cluster
linkerd check --pre

# Install Linkerd
linkerd install --crds | kubectl apply -f -
linkerd install | kubectl apply -f -
linkerd check

# Inject sidecar ke namespace agricity
kubectl annotate namespace agricity \
  linkerd.io/inject=enabled

# Restart deployments
kubectl rollout restart deployment -n agricity

# Install observability (Viz extension)
linkerd viz install | kubectl apply -f -
linkerd viz check

# Akses dashboard
linkerd viz dashboard &
```

Catatan: Linkerd menggunakan mTLS secara default tanpa konfigurasi tambahan.
File Istio di `k8s/istio/` tidak berlaku untuk Linkerd.

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Pod tetap `1/1` setelah rollout restart | Pastikan `kubectl get namespace agricity --show-labels` menampilkan `istio-injection=enabled` |
| `istiod` CrashLoopBackOff | Cek resource node: `kubectl top nodes` — minimal 2 CPU, 4GB RAM |
| mTLS check error `CONFLICT` | Hapus PeerAuthentication lama: `kubectl delete peerauthentication -n agricity --all` |
| Circuit breaker tidak aktif | Pastikan DestinationRule sudah apply dan pod `python-ml` ada di mesh (2/2) |
| Kiali tidak menampilkan service graph | Kirim beberapa request ke `/health` semua service untuk generate traffic data |
| IngressGateway tidak menerima traffic | Cek `kubectl get svc istio-ingressgateway -n istio-system` — pastikan EXTERNAL-IP terisi |
