# Socket Hang Up Fix - Smart Agri City

**Date**: 28 June 2026  
**Status**: ✅ **RESOLVED**

## Problem Summary
Node-RED experienced persistent "socket hang up" errors when posting alert data to `/api/alerts` endpoint, while sensor data ingestion worked fine.

## Root Causes Identified & Fixed

### 1. Missing Microservices (PRIMARY CAUSE)
**Issue**: Docker compose only started 3 services instead of 13
- ✓ Running: MySQL, OAuth Server, API Gateway
- ✗ Missing: PHP-Crop, PHP-Farmer, PHP-Irrigation, Python-ML, Node-RED, and others

**Symptom**: Node-RED trying to connect to http://api-gateway:3000/api/alerts but API Gateway trying to forward to http://php-crop:8001 which doesn't exist → connection reset

**Fix**: 
```bash
docker compose down
docker compose up --build -d
```
All 13 services now running and healthy ✅

### 2. Alert Severity Value Mismatch
**Issue**: POST /api/alerts returning 400 Bad Request

**Root Cause**: 
- Node-RED flows sending: `severity: "tinggi"`, `"sedang"`, `"kritis"` (Indonesian)
- PHP-Crop validator only accepting: `"high"`, `"medium"`, `"low"`, `"critical"` (English)

**Validation Logic** (php-crop/app/Validators/InputValidator.php):
```php
$validSeverities = ['low', 'medium', 'high', 'critical'];
if (!empty($data['severity']) && !in_array(strtolower($data['severity']), $validSeverities)) {
    $errors[] = "Severity must be one of: " . implode(', ', $validSeverities);
}
```

**Fix** (iot/flows.json):
Changed Node-RED alert building function to map Indonesian to English:
- `"tinggi"` → `"high"`
- `"sedang"` → `"medium"`  
- `"kritis"` → `"critical"`

### 3. PHP Fatal Error in RabbitMQPublisher
**Issue**: POST /api/alerts endpoint crashing

**Error**:
```
Fatal error: Call to undefined method PhpAmqpLib\Connection\AMQPStreamConnection::set_heartbeat()
```

**Root Cause**: 
File: `php-crop/app/Services/RabbitMQPublisher.php:54`  
Code was calling `$connection->set_heartbeat(5)` but this method not available in installed PhpAmqpLib version

**Fix**: 
Removed the heartbeat call - it's optional and connection timeout handles this anyway:
```php
// Note: set_heartbeat() not available in all versions of PhpAmqpLib
// Connection will auto-close after timeout anyway
```

---

## Verification Results

### ✅ Before Fix
```
Sensor Requests:    201 ✓ Success (19-68ms)
Alert Requests:     "socket hang up" ✗ FAIL
```

### ✅ After Fix
```
Sensor Requests:    201 ✓ Success (37-45ms)
Alert Requests:     201 ✓ Success (40-43ms)
```

### Service Status (All 13 containers)
```
✅ agri-mysql                  Healthy
✅ agri-rabbitmq               Healthy
✅ agri-mosquitto              Healthy
✅ agri-oauth-server           Healthy
✅ agri-api-gateway            Healthy
✅ agri-php-farmer             Healthy
✅ agri-php-crop               Healthy (FIXED)
✅ agri-php-irrigation         Healthy
✅ agri-python-ml              Healthy
✅ agri-node-red               Healthy
✅ agri-iot-simulator          Healthy
✅ agri-prometheus             Healthy
✅ agri-grafana                Healthy
```

### API Gateway Logs
```
[2026-06-28T12:13:29.345Z] POST /api/alerts → php-crop:8001 | 201 | 40.268 ms
[2026-06-28T12:13:29.355Z] POST /api/alerts → php-crop:8001 | 201 | 43.648 ms
[2026-06-28T12:13:29.329Z] POST /iot/sensor → php-irrigation:8002 | 201 | 45.213 ms
```

---

## Files Modified

1. **iot/flows.json**
   - Changed alert severity mapping from Indonesian to English
   - `"tinggi"` → `"high"`
   - `"sedang"` → `"medium"`
   - `"kritis"` → `"critical"`

2. **php-crop/app/Services/RabbitMQPublisher.php**
   - Removed line 54: `$connection->set_heartbeat(5)`
   - Added comment explaining method not available in all PhpAmqpLib versions

3. **No changes needed**:
   - express-gateway rate limiting (already fixed in previous session)
   - docker-compose.yml (correct configuration)
   - .env.example (already has rate limit configs)

---

## Lessons Learned

1. **Docker Compose Must Start ALL Services**: Even if compose file defines them, need `docker compose up` to actually start them
2. **Consistency in API Contracts**: Different language values can cause validation failures
3. **Library Version Compatibility**: Methods may not exist in all versions - use try/catch or feature detection
4. **Socket Hang Up Root Cause**: Usually upstream service unavailable, not the proxy itself

---

## Testing Recommendations

1. **Continuous Monitoring**:
   - Watch API Gateway logs for any 503/502 errors
   - Check Node-RED debug output for new alert flows
   - Monitor PHP-Crop logs for RabbitMQ connection issues

2. **Load Testing**: 
   - Test with all 5 zones sending sensors + alerts simultaneously
   - Verify no connection pool exhaustion at 100+ req/min

3. **Failover Testing**:
   - Simulate PHP-Crop crash and verify gateway handles gracefully
   - Test RabbitMQ connection loss scenarios

---

## Production Checklist

- [x] All 13 services running and healthy
- [x] Sensor ingestion working (201 success)
- [x] Alert creation working (201 success)
- [x] No socket hang up errors
- [x] Response times acceptable (40-70ms)
- [ ] Load test with full production data volume
- [ ] Monitor for 24+ hours before full deployment
