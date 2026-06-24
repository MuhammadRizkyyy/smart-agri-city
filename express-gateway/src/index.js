'use strict';

require('dotenv').config();

const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const axios = require('axios');
const client = require('prom-client');

const logger = require('./middleware/logger');
const { requestMetrics } = require('./middleware/metrics');
const { globalLimiter, authLimiter, iotLimiter } = require('./middleware/rateLimit');
const jwtMiddleware = require('./middleware/jwt');
const oauthIntrospect = require('./middleware/oauthIntrospect');
const { requireRole } = require('./middleware/roleMiddleware');

const PORT                = process.env.PORT                || 3000;
const OAUTH_SERVER_URL    = process.env.OAUTH_SERVER_URL    || 'http://oauth-server:3002';
const FARMER_SERVICE_URL  = process.env.FARMER_SERVICE_URL  || 'http://php-farmer:8000';
const CROP_SERVICE_URL    = process.env.CROP_SERVICE_URL    || 'http://php-crop:8001';
const IRRIGATION_SERVICE_URL = process.env.IRRIGATION_SERVICE_URL || 'http://php-irrigation:8002';
const PYTHON_ML_URL       = process.env.PYTHON_ML_URL       || 'http://python-ml:5000';

const app = express();
client.collectDefaultMetrics();

const { fixRequestBody } = require('http-proxy-middleware');

app.use(logger);
app.use(globalLimiter);
app.use(requestMetrics);

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.use((err, req, res, next) => {
  if (err.type === 'entity.parse.failed') {
    return res.status(400).json({
      status: 'error',
      code: 400,
      message: 'Bad Request: Invalid JSON in request body',
      timestamp: new Date().toISOString(),
    });
  }
  next(err);
});

app.post('/iot/sensor', iotLimiter, async (req, res) => {
  try {
    console.log('[/iot/sensor] Received request:', { body: req.body });

    const response = await axios.post(
      `${IRRIGATION_SERVICE_URL}/sensor`,
      req.body,
      {
        headers: {
          'Content-Type': 'application/json',
          'X-Forwarded-For': req.ip || req.socket.remoteAddress,
          'X-Gateway-Version': '1.0.0',
        },
        timeout: 10000,
      }
    );

    console.log('[/iot/sensor] Response from upstream:', response.status);
    res.status(response.status).json(response.data);
  } catch (err) {
    console.error('[/iot/sensor] Error:', err.message);
    const statusCode = err.response?.status || 503;
    const message = err.response?.data?.message || 'Service Unavailable';

    res.status(statusCode).json({
      status: 'error',
      code: statusCode,
      message,
      timestamp: new Date().toISOString(),
    });
  }
});

function proxyTo(target, pathRewrite = {}) {
  return createProxyMiddleware({
    target,
    changeOrigin: true,
    pathRewrite: Object.keys(pathRewrite).length ? pathRewrite : undefined,
    on: {
      proxyReq: (proxyReq, req) => {
        proxyReq.setHeader('X-Forwarded-For', req.ip || req.socket.remoteAddress);
        proxyReq.setHeader('X-Gateway-Version', '1.0.0');
        if (req.user) {
          proxyReq.setHeader('X-User-Id', req.user.sub || req.user.id || '');
          proxyReq.setHeader('X-User-Role', req.user.role || '');
        }
        // Re-stream body that was consumed by express.json()
        fixRequestBody(proxyReq, req);
      },
      error: (err, req, res) => {
        const isConnRefused =
          err.code === 'ECONNREFUSED' || err.code === 'ENOTFOUND';
        const statusCode = isConnRefused ? 503 : 502;
        const message = isConnRefused
          ? 'Service Unavailable: Upstream service is down'
          : 'Bad Gateway: Upstream service did not respond correctly';

        if (!res.headersSent) {
          res.status(statusCode).json({
            status: 'error',
            code: statusCode,
            message,
            timestamp: new Date().toISOString(),
          });
        }
      },
    },
  });
}

async function checkService(name, url) {
  const start = Date.now();
  try {
    await axios.get(`${url}/health`, { timeout: 5000 });
    return { name, status: 'up', latency_ms: Date.now() - start };
  } catch (err) {
    return {
      name,
      status: 'down',
      latency_ms: Date.now() - start,
      error: err.message,
    };
  }
}

app.get('/metrics', async (req, res) => {
  res.set('Content-Type', client.register.contentType);
  res.end(await client.register.metrics());
}); 

app.get('/health', async (req, res) => {
  const checks = await Promise.all([
    checkService('oauth-server',    OAUTH_SERVER_URL),
    checkService('php-farmer',      FARMER_SERVICE_URL),
    checkService('php-crop',        CROP_SERVICE_URL),
    checkService('php-irrigation',  IRRIGATION_SERVICE_URL),
    checkService('python-ml',       PYTHON_ML_URL),
  ]);

  const allUp = checks.every((c) => c.status === 'up');

  res.status(allUp ? 200 : 207).json({
    status: allUp ? 'ok' : 'degraded',
    service: 'api-gateway',
    timestamp: new Date().toISOString(),
    upstreams: checks,
  });
});

// Endpoint publik untuk petani: lihat status lahan berdasarkan nomor telepon
app.get('/public/petani/:phone/status', globalLimiter, async (req, res) => {
  try {
    let { phone } = req.params;
    if (!phone.startsWith('+')) {
      phone = '+' + phone;
    }
    console.log(`[PUBLIC] Petani status request | Phone: ${phone}`);

    const farmerRes = await axios.get(
      `${FARMER_SERVICE_URL}/farmers/by-phone/${phone}`,
      { timeout: 5000 }
    );

    if (!farmerRes.data?.data) {
      console.warn(`[PUBLIC] Phone not found: ${phone}`);
      return res.status(404).json({
        status: 'error',
        code: 404,
        message: 'Nomor telepon tidak terdaftar di sistem',
        timestamp: new Date().toISOString(),
        service: 'api-gateway',
      });
    }

    const farmer = farmerRes.data.data;
    const { id: farmer_id } = farmer;
    
    let zone_id = req.query.zone_id;
    if (!zone_id && farmer.lands && farmer.lands.length > 0) {
      zone_id = farmer.lands[0].zone_id;
    }

    if (!zone_id) {
      console.warn(`[PUBLIC] No zone_id found for farmer: ${farmer.name}`);
      return res.status(400).json({
        status: 'error',
        code: 400,
        message: 'Zone ID tidak ditemukan. Petani belum memiliki lahan yang terdaftar.',
        timestamp: new Date().toISOString(),
        service: 'api-gateway',
      });
    }

    let sensor = {
      zone_id: 1,
      moisture: 55.23,
      temperature: 32,
      air_temp: 34,
      ph: 6.16,
      light_lux: 62031,
      air_humidity: 70.95,
      recorded_at: '2026-06-24 12:29:24',
      valve_open: false
    };
    
    // For production, this should fetch from irrigation service
    // Currently using demo data from most recent sensor reading in DB
    console.log(`[PUBLIC] Using sensor data for zone ${zone_id} (moisture: ${sensor.moisture}%)`);
    
    let alerts = [];
    try {
      const alertRes = await axios.get(
        `${CROP_SERVICE_URL}/alerts/active?zone_id=${zone_id}`,
        { timeout: 5000 }
      );
      alerts = alertRes.data?.data || [];
    } catch (err) {
      console.log(`[PUBLIC] Could not fetch alerts: ${err.message}`);
    }
    
    let kondisi = '🟢 Baik';
    let pesan = 'Lahan dalam kondisi normal.';

    if (sensor.moisture < 25) {
      kondisi = '🔴 Sangat Kering';
      pesan = 'Tanah sangat kering — irigasi otomatis sedang berjalan.';
    } else if (sensor.moisture < 50) {
      kondisi = '🟡 Mulai Kering';
      pesan = 'Kelembaban tanah mulai berkurang. Pantau terus.';
    }

    const urgentAlert = alerts.find((a) => a.severity === 'kritis' || a.severity === 'tinggi');
    if (urgentAlert) {
      kondisi = '🔴 Ada Peringatan';
      pesan = `${urgentAlert.description}`;
    }

    console.log(`[PUBLIC] Status returned for farmer: ${farmer.name}`);

    return res.json({
      status: 'success',
      code: 200,
      data: {
        nama_petani: farmer.name,
        zona_lahan: zone_id,
        kondisi_lahan: kondisi,
        pesan: pesan,
        sensor: {
          kelembaban_tanah: `${sensor.moisture}%`,
          suhu_udara: `${sensor.air_temp}°C`,
          ph_tanah: `${sensor.ph}`,
          cahaya: `${sensor.light_lux} lux`,
        },
        alert_aktif: alerts.length,
        irigasi_otomatis: sensor.valve_open || false,
        terakhir_update: sensor.recorded_at || null,
      },
      message: 'Data kondisi lahan berhasil diambil',
      timestamp: new Date().toISOString(),
      service: 'api-gateway',
    });
  } catch (err) {
    console.error(`[PUBLIC] Error: ${err.message}`);
    const statusCode = err.response?.status || 503;
    const message = err.response?.status === 404
      ? 'Nomor telepon tidak terdaftar di sistem'
      : 'Data lahan sementara tidak tersedia';
    
    return res.status(statusCode).json({
      status: 'error',
      code: statusCode,
      message,
      timestamp: new Date().toISOString(),
      service: 'api-gateway',
    });
  }
});

app.get('/public/zones/:zone_id/alerts', globalLimiter, async (req, res) => {
  try {
    const { zone_id } = req.params;
    console.log(`[PUBLIC] Alerts request for zone: ${zone_id}`);

    const alertRes = await axios.get(
      `${CROP_SERVICE_URL}/alerts?zone_id=${zone_id}`,
      { timeout: 5000 }
    );

    const alerts = alertRes.data?.data || [];

    return res.json({
      status: 'success',
      code: 200,
      data: alerts,
      message: 'Alert aktif berhasil diambil',
      timestamp: new Date().toISOString(),
      service: 'api-gateway',
    });
  } catch (err) {
    console.error(`[PUBLIC] Error: ${err.message}`);
    return res.status(503).json({
      status: 'error',
      code: 503,
      message: 'Data alert sementara tidak tersedia',
      timestamp: new Date().toISOString(),
      service: 'api-gateway',
    });
  }
});

app.use('/oauth', proxyTo(OAUTH_SERVER_URL));

// Farmer Service
app.use('/api/farmers',   authLimiter, oauthIntrospect, proxyTo(FARMER_SERVICE_URL, { '^/api/farmers': '/farmers' }));
app.use('/api/lands',     authLimiter, oauthIntrospect, proxyTo(FARMER_SERVICE_URL, { '^/api/lands': '/lands' }));
app.use('/api/harvests',  authLimiter, oauthIntrospect, proxyTo(FARMER_SERVICE_URL, { '^/api/harvests': '/harvests' }));

// Alerts - GET dan POST (tanpa role check)
app.get('/api/alerts',
  authLimiter,
  oauthIntrospect,
  proxyTo(CROP_SERVICE_URL, { '^/api/alerts': '/alerts' })
);

app.post('/api/alerts',
  authLimiter,
  oauthIntrospect,
  proxyTo(CROP_SERVICE_URL, { '^/api/alerts': '/alerts' })
);

// Alerts - PATCH /resolve requires role check (petugas/admin only)
app.patch('/api/alerts/:id/resolve',
  authLimiter,
  oauthIntrospect,
  requireRole('petugas', 'admin'),
  proxyTo(CROP_SERVICE_URL, { '^/api/alerts': '/alerts' })
);

// Alerts - GET by ID
app.get('/api/alerts/:id',
  authLimiter,
  oauthIntrospect,
  proxyTo(CROP_SERVICE_URL, { '^/api/alerts': '/alerts' })
);

app.use('/api/crops',            authLimiter, oauthIntrospect, proxyTo(CROP_SERVICE_URL, { '^/api/crops': '/crops' }));
app.use('/api/soil-conditions',  authLimiter, oauthIntrospect, proxyTo(CROP_SERVICE_URL, { '^/api/soil-conditions': '/soil-conditions' }));
app.use('/api/recommend',        authLimiter, oauthIntrospect, proxyTo(CROP_SERVICE_URL, { '^/api/recommend': '/recommend' }));

// Irrigation Service — perintah manual hanya admin
app.post('/api/irrigation/command',
  authLimiter,
  oauthIntrospect,
  requireRole('admin'),
  proxyTo(IRRIGATION_SERVICE_URL, { '^/api/irrigation': '/irrigation' })
);

// Sensor baca bisa semua yang login
app.use('/api/sensors',    authLimiter, oauthIntrospect, proxyTo(IRRIGATION_SERVICE_URL, { '^/api/sensors': '/sensors' }));
app.use('/api/zones',      authLimiter, oauthIntrospect, proxyTo(IRRIGATION_SERVICE_URL, { '^/api/zones': '/zones' }));

// Irrigation general access
app.use('/api/irrigation', authLimiter, oauthIntrospect, proxyTo(IRRIGATION_SERVICE_URL, { '^/api/irrigation': '/irrigation' }));

// Python ML Service
app.use('/predict', authLimiter, oauthIntrospect, proxyTo(PYTHON_ML_URL));
app.use('/detect',  authLimiter, oauthIntrospect, proxyTo(PYTHON_ML_URL));

app.use((req, res) => {
  res.status(404).json({
    status: 'error',
    code: 404,
    message: `Not Found: ${req.method} ${req.originalUrl}`,
    timestamp: new Date().toISOString(),
  });
});

app.use((err, req, res, next) => {
  console.error('[Gateway] Unhandled error:', err);
  res.status(500).json({
    status: 'error',
    code: 500,
    message: 'Internal Server Error',
    timestamp: new Date().toISOString(),
  });
});

if (require.main === module) {
  app.listen(PORT, '0.0.0.0', () => {
    console.log(`[Gateway] API Gateway running on port ${PORT}`);
    console.log(`[Gateway] Upstreams:`);
    console.log(`  OAuth Server    → ${OAUTH_SERVER_URL}`);
    console.log(`  Farmer Service  → ${FARMER_SERVICE_URL}`);
    console.log(`  Crop Service    → ${CROP_SERVICE_URL}`);
    console.log(`  Irrigation Svc  → ${IRRIGATION_SERVICE_URL}`);
    console.log(`  Python ML       → ${PYTHON_ML_URL}`);
    console.log(`[Gateway] Crop Service routes:`);
    console.log(`  /api/crops            → ${CROP_SERVICE_URL}/crops`);
    console.log(`  /api/alerts           → ${CROP_SERVICE_URL}/alerts`);
    console.log(`  /api/soil-conditions  → ${CROP_SERVICE_URL}/soil-conditions`);
    console.log(`  /api/recommend        → ${CROP_SERVICE_URL}/recommend`);
  });
}

module.exports = app;
