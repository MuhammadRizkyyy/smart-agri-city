# Fix untuk Error 429 "Too Many Requests" di Node-RED

## Masalah
Node-RED menerima error **429 "Too many requests"** saat mengirim sensor data dan alert dari 5 zone secara bersamaan ke API Gateway.

## Penyebab
API Gateway memiliki rate limiting yang ketat:
- **Global limiter**: 100 req/15 min (terlalu rendah)
- **Auth limiter**: 500 req/min per IP
- **IoT limiter**: 2000 req/min (belum cukup untuk 5 zone dengan burst traffic)

Node-RED mengirim:
- 5 zone × 2 request per zone (sensor + alert) = 10 req/interval
- Interval 3-10 detik = potentially 100-333 req/min
- Tercapai limit dengan cepat

## Solusi

### 1. Update Rate Limits (SUDAH DILAKUKAN)
File: `express-gateway/src/middleware/rateLimit.js`

**Perubahan:**
```javascript
// Global: 100 → 1000 req/15min
RATE_LIMIT_GLOBAL_MAX = 1000

// Auth: 500 → 1000 req/min
RATE_LIMIT_AUTH_MAX = 1000

// IoT: 2000 → 10000 req/min
RATE_LIMIT_IOT_MAX = 10000
```

**Configurable via environment variables:**
```bash
RATE_LIMIT_GLOBAL_MAX=1000
RATE_LIMIT_IOT_MAX=10000
```

### 2. Rebuild & Restart Services
```bash
docker compose down
docker compose up --build
```

### 3. Rekomendasi untuk Node-RED

#### A. Add Request Delay Between Zones
Gunakan **Delay node** untuk menghindari burst requests:
- Tambahkan 1-2 detik delay antara zone posts
- Atau gunakan **sequential flow** bukannya parallel

#### B. Implement Retry Logic
Gunakan **Catch node** untuk retry failed requests:
```javascript
// Function node
if (msg.statusCode === 429) {
  // Retry dengan exponential backoff
  return null; // delay dulu
}
```

#### C. Batch Requests
Jika memungkinkan, kirim multiple zones dalam 1 request:
```json
POST /api/sensor/batch
{
  "sensors": [
    { "zone": "a", "moisture": 50 },
    { "zone": "b", "moisture": 55 },
    ...
  ]
}
```

#### D. Monitor Rate Limit Headers
Node-RED dapat membaca header:
```
RateLimit-Limit: 10000
RateLimit-Remaining: 9990
RateLimit-Reset: 1719603893
```

Gunakan untuk **backoff otomatis**.

### 4. Verify Fix

#### Check Rate Limits Status
```bash
curl http://localhost:3106/health
```

#### Monitor Real-Time
```bash
# Watch gateway logs
docker logs -f agri-api-gateway

# Check prometheus metrics
curl http://localhost:9091/api/v1/query?query=rate_limit_hits_total
```

#### Test dengan Postman
```bash
# Run S1 E2E test 5x dalam rapid succession
# Sebelum fix: 429 errors
# Sesudah fix: All 200 OK
```

---

## Environment Variables Reference

### Default Values (dalam code)
| Variable | Default | Deskripsi |
|----------|---------|-----------|
| `RATE_LIMIT_GLOBAL_WINDOW_MS` | 900000 | 15 menit (global) |
| `RATE_LIMIT_GLOBAL_MAX` | 1000 | 1000 req per window |
| `RATE_LIMIT_AUTH_WINDOW_MS` | 60000 | 1 menit (auth) |
| `RATE_LIMIT_AUTH_MAX` | 1000 | 1000 req per window |
| `RATE_LIMIT_IOT_WINDOW_MS` | 60000 | 1 menit (IoT) |
| `RATE_LIMIT_IOT_MAX` | 10000 | 10000 req per window |

### Custom Configuration (dalam .env)
```bash
# Increase IoT limit lebih untuk production
RATE_LIMIT_IOT_MAX=20000

# Atau customize lainnya
RATE_LIMIT_GLOBAL_MAX=2000
```

---

## Monitoring & Troubleshooting

### 1. Check Current Rate Limit Usage
```bash
# Di Prometheus/Grafana
rate_limit_hits_total{limiter="iot"}
```

### 2. Gradual Load Testing
```bash
# Run sequentially dulu (3-5 interval antara requests)
# Monitor: apakah ada 429?
# Jika tidak ada, increase frequency

# Simulasi real load:
for i in {1..100}; do
  curl -X POST http://localhost:3106/iot/sensor \
    -H "Content-Type: application/json" \
    -d '{"zone":"a","moisture":50}'
done
```

### 3. Debug Node-RED Flow
- Tambahkan **debug node** setelah setiap POST
- Check: status code, headers, response time
- Monitor interval antar requests di flow

### 4. Check Gateway Health
```bash
curl http://localhost:3106/health | jq
```

---

## Performance Tuning

### Untuk Production
Pertimbangkan:
1. **Connection pooling** di Node-RED (batch requests)
2. **Queue-based ingestion** (async processing via RabbitMQ)
3. **CDN/caching** untuk frequent reads
4. **Separate rate limit untuk IoT** (by zone atau by token)

### Advanced Configuration
```javascript
// Custom keyGenerator untuk IoT limiter
keyGenerator: (req) => {
  // Rate limit per zone, bukan global
  return req.body.zone || req.ip;
}
```

---

## Testing Checklist

- [ ] Restart docker-compose
- [ ] Open http://localhost:1881 (Node-RED)
- [ ] Run flow with 5 zones
- [ ] Check logs: `docker logs -f agri-api-gateway`
- [ ] Verify: No more 429 errors
- [ ] Check Grafana: Request rate chart
- [ ] Confirm: Data flowing to database

---

## Reference

- Rate Limit Middleware: `express-gateway/src/middleware/rateLimit.js`
- Gateway Config: `express-gateway/src/index.js`
- Docker Setup: `docker-compose.yml`
- Environment Template: `.env.example`
