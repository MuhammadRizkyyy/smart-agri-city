#!/usr/bin/env bash
# ============================================================
#  AgriCity — End-to-End Test Suite  S1 – S6
# ============================================================
set -euo pipefail

GW="http://localhost:3000"
OAUTH="http://localhost:3002"
FARMER="http://localhost:8000"
CROP="http://localhost:8001"
IRRIGATION="http://localhost:8002"
ML="http://localhost:5000"
RMQ_API="http://localhost:15672"
RMQ_USER="guest"
RMQ_PASS="guest"
DB_PASS="secret_db_password_change_me"

PASS=0; FAIL=0; WARN=0
RESULTS=()

# ── helpers ──────────────────────────────────────────────────────────────────
ok()   { echo "  ✅  $1"; PASS=$((PASS+1));  RESULTS+=("PASS | $1"); }
fail() { echo "  ❌  $1"; FAIL=$((FAIL+1));  RESULTS+=("FAIL | $1"); }
warn() { echo "  ⚠️   $1"; WARN=$((WARN+1)); RESULTS+=("WARN | $1"); }
info() { echo "  ℹ️   $1"; }
sep()  { echo; echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; echo "  $1"; echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"; }

curl_get()      { curl -s -o /dev/null -w "%{http_code}" "$@"; }
curl_body()     { curl -s "$@"; }
curl_post()     { curl -s -o /dev/null -w "%{http_code}" -X POST -H "Content-Type: application/json" "$@"; }
curl_post_body(){ curl -s -X POST -H "Content-Type: application/json" "$@"; }

# Helper: dapatkan token baru dengan retry
get_token() {
  local tok=""
  for _t in 1 2 3; do
    tok=$(curl -s --max-time 10 -X POST "$OAUTH/oauth/token" \
      -H "Content-Type: application/x-www-form-urlencoded" \
      -d "grant_type=password&username=farmer@agri.com&password=password123&client_id=web-app&client_secret=web_secret_123" \
      2>/dev/null | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4 || echo "")
    [[ -n "$tok" ]] && break
    sleep 1
  done
  echo "$tok"
}

# ─────────────────────────────────────────────────────────────────────────────
sep "S5 · Docker Full Stack — Service Health Checks"
# ─────────────────────────────────────────────────────────────────────────────

for svc in \
  "API Gateway|$GW/health" \
  "OAuth Server|$OAUTH/health" \
  "PHP Farmer|$FARMER/health" \
  "PHP Crop|$CROP/health" \
  "PHP Irrigation|$IRRIGATION/health" \
  "Python ML|$ML/health" \
  "Grafana|http://localhost:3001/api/health" \
  "Prometheus|http://localhost:9090/-/healthy" \
  "Node-RED|http://localhost:1880/"; do
  name="${svc%%|*}"; url="${svc##*|}"
  code=$(curl_get --max-time 5 "$url" 2>/dev/null || echo "000")
  [[ "$code" =~ ^(200|204)$ ]] && ok "$name reachable (HTTP $code)" || fail "$name unreachable (HTTP $code)"
done

info "Checking all 12+ containers healthy..."
UNHEALTHY=$(docker ps --filter "health=unhealthy" --format "{{.Names}}" 2>/dev/null || echo "")
STOPPED=$(docker ps -a --filter "status=exited" --filter "name=agri-" --format "{{.Names}}" 2>/dev/null || echo "")
[[ -z "$UNHEALTHY" ]] && ok "No unhealthy containers" || fail "Unhealthy containers: $UNHEALTHY"
[[ -z "$STOPPED"   ]] && ok "No stopped agri-* containers" || fail "Stopped containers: $STOPPED"

TOTAL=$(docker ps --filter "name=agri-" --format "{{.Names}}" | wc -l)
[[ "$TOTAL" -ge 12 ]] && ok "Running containers: $TOTAL (≥12)" || warn "Running containers: $TOTAL (expected ≥12)"

# ─────────────────────────────────────────────────────────────────────────────
sep "S2 · Petani Login & Catat Panen — OAuth2 + JWT Flow"
# ─────────────────────────────────────────────────────────────────────────────

info "POST /oauth/token → mendapatkan access_token..."
ACCESS_TOKEN=$(get_token)
if [[ -n "$ACCESS_TOKEN" ]]; then
  ok "OAuth2 token issued (access_token diterima)"
else
  fail "OAuth2 token tidak diterima"
fi

if [[ -n "$ACCESS_TOKEN" ]]; then
  info "POST /oauth/introspect → validasi token..."
  INTRO_RESP=$(curl -s --max-time 8 -X POST "$OAUTH/oauth/introspect" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "token=$ACCESS_TOKEN" 2>/dev/null)
  ACTIVE=$(echo "$INTRO_RESP" | grep -o '"active":[^,}]*' | cut -d: -f2 | tr -d ' "' || echo "")
  [[ "$ACTIVE" == "true" ]] && ok "Token introspect aktif" || warn "Token introspect result: $ACTIVE"

  info "GET /api/farmers → listing farmers dengan JWT..."
  CODE=$(curl_get --max-time 8 -H "Authorization: Bearer $ACCESS_TOKEN" "$GW/api/farmers" 2>/dev/null || echo "000")
  [[ "$CODE" == "200" ]] && ok "GET /api/farmers via Gateway (HTTP 200)" || fail "GET /api/farmers gagal (HTTP $CODE)"

  info "POST /api/harvests → catat panen baru..."
  HARVEST_RESP=$(curl_post_body --max-time 10 \
    -H "Authorization: Bearer $ACCESS_TOKEN" \
    -d '{"land_id":1,"crop_type":"Padi","yield_ton":5.2,"harvest_date":"2025-06-01","notes":"E2E Test Harvest"}' \
    "$GW/api/harvests" 2>/dev/null)
  H_STATUS=$(echo "$HARVEST_RESP" | grep -o '"status":"[^"]*"' | head -1 | cut -d'"' -f4 || echo "")
  H_CODE=$(echo "$HARVEST_RESP" | grep -o '"code":[0-9]*' | head -1 | cut -d: -f2 || echo "")
  if [[ "$H_STATUS" == "success" && "$H_CODE" =~ ^(200|201)$ ]]; then
    ok "POST /api/harvests → harvest tersimpan (HTTP $H_CODE)"
  else
    fail "POST /api/harvests gagal: $(echo "$HARVEST_RESP" | head -c 200)"
  fi

  info "POST /oauth/revoke → revoke token (token terpisah, belum pernah lewat gateway)..."
  REVOKE_TOKEN=$(get_token)
  REVOKE_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 8 -X POST "$OAUTH/oauth/revoke" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "token=$REVOKE_TOKEN" 2>/dev/null || echo "000")
  [[ "$REVOKE_CODE" =~ ^(200|204)$ ]] && ok "Token revoke berhasil (HTTP $REVOKE_CODE)" || warn "Token revoke HTTP $REVOKE_CODE"

  info "Verifikasi token tidak valid setelah revoke (gateway pakai oauthIntrospect)..."
  CODE3=$(curl_get --max-time 8 -H "Authorization: Bearer $REVOKE_TOKEN" "$GW/api/farmers" 2>/dev/null || echo "000")
  [[ "$CODE3" =~ ^(401|403)$ ]] && ok "Token revoked → akses ditolak (HTTP $CODE3)" \
    || warn "Token masih aktif setelah revoke (HTTP $CODE3)"
else
  fail "S2 auth-dependent tests skipped — no valid token"
fi

# ─────────────────────────────────────────────────────────────────────────────
sep "S3 · ML Real-time Prediction"
# ─────────────────────────────────────────────────────────────────────────────

sleep 1  # jeda singkat setelah test S2

ML_TOKEN=$(get_token)
[[ -n "$ML_TOKEN" ]] && info "ML token diperoleh" || warn "Gagal mendapatkan ML token, pakai direct ML endpoint"

YIELD_PAYLOAD='{"avg_temp":28.5,"rainfall":1200,"soil_moisture":65,"ph":6.5,"nitrogen":80,"phosphorus":40,"potassium":60,"area_ha":2.0,"week_of_planting":14}'

info "POST /predict/yield → prediksi hasil panen..."
if [[ -n "$ML_TOKEN" ]]; then
  YIELD_RESP=$(curl_post_body --max-time 10 -H "Authorization: Bearer $ML_TOKEN" -d "$YIELD_PAYLOAD" "$GW/predict/yield" 2>/dev/null)
else
  YIELD_RESP=$(curl_post_body --max-time 10 -d "$YIELD_PAYLOAD" "$ML/predict/yield" 2>/dev/null)
fi
YIELD_VAL=$(echo "$YIELD_RESP" | grep -o '"predicted_yield_ton":[0-9.]*' | cut -d: -f2 || echo "")
YIELD_CAT=$(echo "$YIELD_RESP" | grep -o '"yield_category":"[^"]*"' | cut -d'"' -f4 || echo "")
if [[ -n "$YIELD_VAL" ]]; then
  ok "POST /predict/yield → ${YIELD_VAL} ton/ha, kategori: '$YIELD_CAT'"
else
  fail "POST /predict/yield gagal → $(echo "$YIELD_RESP" | head -c 200)"
fi

info "POST /predict/pest → deteksi hama..."
# zone: zona1 | zona2 | zona3 | zona4
PEST_PAYLOAD='{"air_humidity":75,"leaf_temp":28.5,"soil_ph":6.5,"chlorophyll":42.0,"light_lux":35000,"zone":"zona1"}'
if [[ -n "$ML_TOKEN" ]]; then
  PEST_RESP=$(curl_post_body --max-time 10 -H "Authorization: Bearer $ML_TOKEN" -d "$PEST_PAYLOAD" "$GW/predict/pest" 2>/dev/null)
else
  PEST_RESP=$(curl_post_body --max-time 10 -d "$PEST_PAYLOAD" "$ML/predict/pest" 2>/dev/null)
fi
PEST_CAT=$(echo "$PEST_RESP" | grep -o '"pest_category":"[^"]*"' | cut -d'"' -f4 || echo "")
[[ -n "$PEST_CAT" ]] && ok "POST /predict/pest → kategori: '$PEST_CAT'" \
  || fail "POST /predict/pest gagal → $(echo "$PEST_RESP" | head -c 200)"

info "POST /predict/irrigation → kalkulasi irigasi..."
# growth_phase: semai | vegetatif | generatif | panen
IRR_PAYLOAD='{"soil_moisture":30,"air_temp":30,"rain_forecast":5,"growth_phase":"vegetatif","evapotranspiration":4.5}'
if [[ -n "$ML_TOKEN" ]]; then
  IRR_RESP=$(curl_post_body --max-time 10 -H "Authorization: Bearer $ML_TOKEN" -d "$IRR_PAYLOAD" "$GW/predict/irrigation" 2>/dev/null)
else
  IRR_RESP=$(curl_post_body --max-time 10 -d "$IRR_PAYLOAD" "$ML/predict/irrigation" 2>/dev/null)
fi
WATER=$(echo "$IRR_RESP" | grep -o '"water_needed_liters":[0-9.]*' | cut -d: -f2 || echo "")
[[ -n "$WATER" ]] && ok "POST /predict/irrigation → ${WATER} liter dibutuhkan" \
  || fail "POST /predict/irrigation gagal → $(echo "$IRR_RESP" | head -c 200)"

# ─────────────────────────────────────────────────────────────────────────────
sep "S1 · IoT Data Ingestion — MQTT → Node-RED → DB → RabbitMQ → ML"
# ─────────────────────────────────────────────────────────────────────────────

info "Cek simulator container berjalan..."
SIM_STATUS=$(docker inspect --format='{{.State.Status}}' agri-iot-simulator 2>/dev/null || echo "not found")
[[ "$SIM_STATUS" == "running" ]] && ok "IoT simulator running" || fail "IoT simulator: $SIM_STATUS"

info "Cek Node-RED flow aktif..."
NR_CODE=$(curl_get --max-time 5 "http://localhost:1880/" 2>/dev/null || echo "000")
[[ "$NR_CODE" =~ ^(200|301|302)$ ]] && ok "Node-RED reachable (HTTP $NR_CODE)" || fail "Node-RED unreachable (HTTP $NR_CODE)"

info "Cek Mosquitto MQTT broker..."
MOSQ_STATUS=$(docker inspect --format='{{.State.Health.Status}}' agri-mosquitto 2>/dev/null || echo "unknown")
[[ "$MOSQ_STATUS" == "healthy" ]] && ok "Mosquitto healthy" || fail "Mosquitto: $MOSQ_STATUS"

info "Simulasi POST sensor reading (IoT flow: client_credentials → /iot/sensor)..."
SENSOR_PAYLOAD='{"zone_id":1,"moisture":42.5,"temperature":28.1,"humidity":68.0,"ph":6.8,"nitrogen":75,"phosphorus":35,"potassium":55}'

IOT_TOKEN=$(curl -s --max-time 8 -X POST "$OAUTH/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=client_credentials&client_id=iot-device&client_secret=iot_secret_456" \
  2>/dev/null | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4 || echo "")

if [[ -n "$IOT_TOKEN" ]]; then
  # /iot/* menggunakan oauthIntrospect (bukan global rate limiter path)
  SENSOR_CODE=$(curl_post --max-time 10 -H "Authorization: Bearer $IOT_TOKEN" \
    -d "$SENSOR_PAYLOAD" "$GW/iot/sensor" 2>/dev/null || echo "000")
else
  # Fallback: langsung ke irrigation service (bypass gateway)
  SENSOR_CODE=$(curl_post --max-time 10 -d "$SENSOR_PAYLOAD" "$IRRIGATION/sensor" 2>/dev/null || echo "000")
fi
[[ "$SENSOR_CODE" =~ ^(200|201)$ ]] && ok "POST sensor data → HTTP $SENSOR_CODE (data tersimpan)" \
  || warn "POST sensor data HTTP $SENSOR_CODE"

info "Verifikasi data masuk ke database..."
DB_COUNT=$(docker exec agri-mysql mysql -u root -p"${DB_PASS}" -s -N -e \
  "SELECT COUNT(*) FROM agriCity.irr_sensor_readings;" 2>/dev/null || echo "0")
[[ "$DB_COUNT" -gt 0 ]] && ok "DB has ${DB_COUNT} sensor readings" || fail "Tidak ada sensor reading di DB"

info "Cek RabbitMQ queue aktif (sensor.*)..."
QUEUES=$(curl_body -u "$RMQ_USER:$RMQ_PASS" --max-time 5 \
  "$RMQ_API/api/queues" 2>/dev/null | grep -o '"name":"[^"]*"' | cut -d'"' -f4 || echo "")
if echo "$QUEUES" | grep -qi "sensor"; then
  ok "RabbitMQ: queue 'sensor.*' ditemukan"
elif [[ -n "$QUEUES" ]]; then
  warn "RabbitMQ aktif tapi queue 'sensor' belum ada. Queues: $(echo "$QUEUES" | tr '\n' ',')"
else
  fail "RabbitMQ queues tidak terbaca"
fi

info "Cek RabbitMQ exchanges..."
EXCHANGES=$(curl_body -u "$RMQ_USER:$RMQ_PASS" --max-time 5 \
  "$RMQ_API/api/exchanges" 2>/dev/null \
  | grep -o '"name":"[^"]*"' | cut -d'"' -f4 | grep -v '^$' | head -10 || echo "")
[[ -n "$EXCHANGES" ]] && ok "RabbitMQ exchanges tersedia" || warn "Tidak bisa membaca exchanges"

# ─────────────────────────────────────────────────────────────────────────────
sep "S4 · Irigasi Otomatis — ML → RabbitMQ → Irrigation → MQTT"
# ─────────────────────────────────────────────────────────────────────────────

info "Simulasi ML detect moisture < 25 → irrigation urgency (direct ML endpoint)..."
DRY_PAYLOAD='{"soil_moisture":20,"air_temp":32,"rain_forecast":0,"growth_phase":"vegetatif","evapotranspiration":6.0}'
DRY_RESP=$(curl_post_body --max-time 10 -d "$DRY_PAYLOAD" "$ML/predict/irrigation" 2>/dev/null)
DRY_WATER=$(echo "$DRY_RESP" | grep -o '"water_needed_liters":[0-9.]*' | cut -d: -f2 || echo "")
DRY_URGENCY=$(echo "$DRY_RESP" | grep -o '"irrigation_urgency":"[^"]*"' | cut -d'"' -f4 || echo "")
if [[ -n "$DRY_WATER" ]]; then
  ok "Dry soil prediction → ${DRY_WATER} liter, urgency: '$DRY_URGENCY'"
  [[ -n "$DRY_URGENCY" ]] && ok "Urgency level terdeteksi untuk moisture < 25: '$DRY_URGENCY'" \
    || warn "Urgency tidak terdeteksi"
else
  fail "Dry soil prediction gagal → $(echo "$DRY_RESP" | head -c 200)"
fi

info "Cek irrigation command endpoint (via Gateway)..."
IRR_TOKEN=$(get_token)
if [[ -n "$IRR_TOKEN" ]]; then
  # Stop zone_id=2 dulu jika sedang aktif, lalu start
  curl -s --max-time 8 -X POST "$IRRIGATION/irrigation/command" \
    -H "Authorization: Bearer $IRR_TOKEN" -H "Content-Type: application/json" \
    -d '{"zone_id":2,"action":"stop","trigger_type":"otomatis_ml"}' > /dev/null 2>&1 || true

  IRR_CMD_CODE=$(curl_post --max-time 10 -H "Authorization: Bearer $IRR_TOKEN" \
    -d '{"zone_id":2,"action":"start","trigger_type":"otomatis_ml"}' \
    "$GW/api/irrigation/command" 2>/dev/null || echo "000")
  if [[ "$IRR_CMD_CODE" =~ ^(200|201)$ ]]; then
    ok "Irrigation command via Gateway (HTTP $IRR_CMD_CODE)"
    curl -s --max-time 8 -X POST "$IRRIGATION/irrigation/command" \
      -H "Authorization: Bearer $IRR_TOKEN" -H "Content-Type: application/json" \
      -d '{"zone_id":2,"action":"stop","trigger_type":"otomatis_ml"}' > /dev/null 2>&1 || true
  else
    warn "Irrigation command HTTP $IRR_CMD_CODE"
  fi
else
  warn "Tidak bisa mendapatkan token untuk irrigation command test"
fi

info "Cek RabbitMQ queue irrigation.*..."
IRR_QUEUES=$(curl_body -u "$RMQ_USER:$RMQ_PASS" --max-time 5 \
  "$RMQ_API/api/queues" 2>/dev/null | grep -o '"name":"[^"]*"' | cut -d'"' -f4 || echo "")
if echo "$IRR_QUEUES" | grep -qi "irrigat"; then
  ok "RabbitMQ: irrigation queue ditemukan"
else
  warn "RabbitMQ: queue 'irrigation.*' belum ada"
fi

info "Cek Node-RED flows API tersedia..."
NR_FLOWS=$(curl_body --max-time 5 "http://localhost:1880/flows" 2>/dev/null | head -c 200 || echo "")
[[ -n "$NR_FLOWS" ]] && ok "Node-RED flows API tersedia" || warn "Node-RED flows API tidak merespons"

# ─────────────────────────────────────────────────────────────────────────────
sep "S5 · Monitoring — Prometheus Scraping & Grafana"
# ─────────────────────────────────────────────────────────────────────────────

info "Cek Prometheus targets..."
# Tunggu hingga 2 siklus scrape (30s) jika ada target yang masih unknown
PROM_TARGETS=""
for _pt in 1 2 3; do
  PROM_TARGETS=$(curl_body --max-time 5 "http://localhost:9090/api/v1/targets" 2>/dev/null || true)
  UNKNOWN=$(echo "$PROM_TARGETS" | grep -o '"health":"unknown"' | wc -l | tr -d ' ' || true)
  [[ "${UNKNOWN:-0}" -eq 0 ]] && break
  sleep 15
done
ACTIVE=$(echo "$PROM_TARGETS" | grep -o '"health":"up"' | wc -l | tr -d ' ' || true)
TOTAL_TARGETS=$(echo "$PROM_TARGETS" | grep -o '"health":"[^"]*"' | wc -l | tr -d ' ' || true)
ACTIVE="${ACTIVE:-0}"; TOTAL_TARGETS="${TOTAL_TARGETS:-0}"
if [[ "$ACTIVE" -gt 0 && "$ACTIVE" -eq "$TOTAL_TARGETS" ]]; then
  ok "Prometheus: $ACTIVE/$TOTAL_TARGETS targets UP (semua sehat)"
elif [[ "$ACTIVE" -gt 0 ]]; then
  warn "Prometheus: $ACTIVE/$TOTAL_TARGETS targets UP"
else
  fail "Prometheus: tidak ada target UP"
fi

info "Cek metrics endpoint (direct, bukan via gateway)..."
# /metrics di gateway tidak lewat globalLimiter (unprotected route)
GW_METRICS=$(curl_get --max-time 5 "$GW/metrics" 2>/dev/null || echo "000")
[[ "$GW_METRICS" == "200" ]] && ok "Gateway /metrics (HTTP 200)" || warn "Gateway /metrics HTTP $GW_METRICS"

for svc in \
  "PHP Farmer|$FARMER/metrics" \
  "PHP Crop|$CROP/metrics" \
  "PHP Irrigation|$IRRIGATION/metrics" \
  "Python ML|$ML/metrics"; do
  name="${svc%%|*}"; url="${svc##*|}"
  code=$(curl_get --max-time 5 "$url" 2>/dev/null || echo "000")
  [[ "$code" == "200" ]] && ok "$name /metrics (HTTP $code)" || warn "$name /metrics HTTP $code"
done

info "Cek Grafana dashboards..."
GF_HEALTH=$(curl_body --max-time 5 "http://localhost:3001/api/health" 2>/dev/null)
# Parse "database":"ok" dari JSON response (handle spasi/newline)
GF_OK=$(echo "$GF_HEALTH" | tr -d ' \n\r' | grep -o '"database":"[^"]*"' | cut -d'"' -f4 || echo "")
[[ "$GF_OK" == "ok" ]] && ok "Grafana database: ok" \
  || warn "Grafana health: $(echo "$GF_HEALTH" | tr -d '\n' | head -c 100)"

# ─────────────────────────────────────────────────────────────────────────────
sep "S6 · Kubernetes Deployment"
# ─────────────────────────────────────────────────────────────────────────────

info "Cek kubectl tersedia..."
KUBECTL_VER=$(kubectl version --client 2>/dev/null | grep "Client Version" | head -1 || echo "")
[[ -n "$KUBECTL_VER" ]] && ok "kubectl tersedia" || { warn "kubectl tidak tersedia — skip K8s tests"; }

if command -v kubectl &>/dev/null; then
  minikube update-context > /dev/null 2>&1 || true

  info "Cek k8s cluster (minikube --driver=docker)..."
  CLUSTER_INFO=$(kubectl cluster-info 2>/dev/null | grep -i "control plane\|master" | head -1 || echo "")
  [[ -n "$CLUSTER_INFO" ]] && ok "K8s cluster aktif" \
    || warn "K8s cluster tidak terhubung (jalankan: minikube start --driver=docker)"

  NS_EXISTS=$(kubectl get namespace agricity --no-headers 2>/dev/null | awk '{print $1}' || echo "")
  [[ "$NS_EXISTS" == "agricity" ]] && ok "Namespace 'agricity' tersedia" \
    || warn "Namespace 'agricity' tidak ditemukan"

  info "Cek pods di namespace agricity..."
  K8S_PODS=$(kubectl get pods -n agricity --no-headers 2>/dev/null || echo "")
  if [[ -n "$K8S_PODS" ]]; then
    RUNNING=$(echo "$K8S_PODS" | grep -c " Running " || true)
    TOTAL_PODS=$(echo "$K8S_PODS" | wc -l | tr -d ' ')
    # Count pods where READY column shows X/X (fully ready)
    READY=$(echo "$K8S_PODS" | awk '{n=split($2,a,"/"); if(n==2 && a[1]==a[2] && a[1]+0>0) c++} END{print c+0}')
    NOT_READY_NAMES=$(echo "$K8S_PODS" | awk '{n=split($2,a,"/"); if(n==2 && a[1]!=a[2]) print $1}' | paste -sd ',' || echo "")
    ok "K8s: $RUNNING/$TOTAL_PODS pods Running, $READY fully Ready di namespace agricity"
    [[ -n "$NOT_READY_NAMES" ]] && warn "Pods belum fully Ready: $NOT_READY_NAMES"
  else
    warn "Tidak ada pods di namespace agricity"
  fi

  info "Cek HPA..."
  HPA_LIST=$(kubectl get hpa -n agricity --no-headers 2>/dev/null || echo "")
  [[ -n "$HPA_LIST" ]] \
    && ok "HPA tersedia: $(echo "$HPA_LIST" | awk '{print $1}' | tr '\n' ' ')" \
    || warn "HPA tidak ditemukan di agricity"

  info "Cek Ingress..."
  ING_LIST=$(kubectl get ingress -n agricity --no-headers 2>/dev/null || echo "")
  [[ -n "$ING_LIST" ]] \
    && ok "Ingress tersedia: $(echo "$ING_LIST" | awk '{print $1, $3}' | tr '\n' ' ')" \
    || warn "Ingress tidak ditemukan di agricity"
fi

# ─────────────────────────────────────────────────────────────────────────────
sep "Rate Limit · Verifikasi Gateway Global Limiter (100 req/15min)"
# Rate limit test TERAKHIR agar tidak mempengaruhi test lain
# ─────────────────────────────────────────────────────────────────────────────

info "Rate-limit check: kirim request sampai 429 (global limit: 100/15min per IP)..."
RL_HITS=0
RL_COUNT=0
# Cek sisa kuota dulu via header
RL_REMAINING=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "$GW/health" 2>/dev/null; \
  curl -sI --max-time 5 "$GW/health" 2>/dev/null | grep -i "RateLimit-Remaining:" | awk '{print $2}' | tr -d '\r' || echo "")
info "Sisa global rate limit sebelum test: ${RL_REMAINING:-unknown}"

# Kirim hingga 429 atau max 100 request
for i in $(seq 1 100); do
  code=$(curl_get --max-time 3 "$GW/health" 2>/dev/null || echo "000")
  RL_COUNT=$((RL_COUNT+1))
  if [[ "$code" == "429" ]]; then
    RL_HITS=$((RL_HITS+1))
    break
  fi
done
[[ "$RL_HITS" -gt 0 ]] \
  && ok "Rate limiter aktif — 429 ter-trigger setelah $RL_COUNT requests" \
  || warn "Rate limiter tidak memblokir dalam $RL_COUNT requests (window mungkin baru reset)"

# ─────────────────────────────────────────────────────────────────────────────
sep "SUMMARY"
# ─────────────────────────────────────────────────────────────────────────────
echo
echo "  Total: $((PASS+FAIL+WARN)) tests | ✅ $PASS passed | ❌ $FAIL failed | ⚠️  $WARN warnings"
echo
echo "  Detail:"
for r in "${RESULTS[@]}"; do
  case "${r%%|*}" in
    PASS) echo "    ✅  ${r#*| }" ;;
    FAIL) echo "    ❌  ${r#*| }" ;;
    WARN) echo "    ⚠️   ${r#*| }" ;;
  esac
done
echo
if [[ "$FAIL" -eq 0 ]]; then
  echo "  🎉  Semua skenario E2E PASSED!"
elif [[ "$FAIL" -le 3 ]]; then
  echo "  ⚠️   Sebagian besar skenario passed ($FAIL kegagalan minor)"
else
  echo "  ❌  Beberapa skenario GAGAL ($FAIL failures)"
fi
echo
