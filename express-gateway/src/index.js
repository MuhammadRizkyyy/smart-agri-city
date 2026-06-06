'use strict';

require('dotenv').config();

const express = require('express');
const { createProxyMiddleware } = require('http-proxy-middleware');
const axios = require('axios');
const client = require('prom-client');

const logger = require('./middleware/logger');
const { requestMetrics } = require('./middleware/metrics');
const { globalLimiter, authLimiter } = require('./middleware/rateLimit');
const jwtMiddleware = require('./middleware/jwt');
const oauthIntrospect = require('./middleware/oauthIntrospect');

const PORT                = process.env.PORT                || 3000;
const OAUTH_SERVER_URL    = process.env.OAUTH_SERVER_URL    || 'http://oauth-server:3002';
const FARMER_SERVICE_URL  = process.env.FARMER_SERVICE_URL  || 'http://php-farmer:8000';
const CROP_SERVICE_URL    = process.env.CROP_SERVICE_URL    || 'http://php-crop:8001';
const IRRIGATION_SERVICE_URL = process.env.IRRIGATION_SERVICE_URL || 'http://php-irrigation:8002';
const PYTHON_ML_URL       = process.env.PYTHON_ML_URL       || 'http://python-ml:5000';

const app = express();
client.collectDefaultMetrics();

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.use(logger);
app.use(globalLimiter);
app.use(requestMetrics);

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

// OAuth Routes
app.use('/oauth', proxyTo(OAUTH_SERVER_URL));

// IoT Routes — strip /iot prefix before forwarding to irrigation service
app.use('/iot', oauthIntrospect, proxyTo(IRRIGATION_SERVICE_URL, { '^/iot': '' }));

// Farmer Service
app.use('/api/farmers',   authLimiter, jwtMiddleware, proxyTo(FARMER_SERVICE_URL, { '^/api/farmers': '/farmers' }));
app.use('/api/lands',     authLimiter, jwtMiddleware, proxyTo(FARMER_SERVICE_URL, { '^/api/lands': '/lands' }));
app.use('/api/harvests',  authLimiter, jwtMiddleware, proxyTo(FARMER_SERVICE_URL, { '^/api/harvests': '/harvests' }));

// Crop Service
app.use('/api/crops',            authLimiter, jwtMiddleware, proxyTo(CROP_SERVICE_URL, { '^/api/crops': '/crops' }));
app.use('/api/alerts',           authLimiter, jwtMiddleware, proxyTo(CROP_SERVICE_URL, { '^/api/alerts': '/alerts' }));
app.use('/api/soil-conditions',  authLimiter, jwtMiddleware, proxyTo(CROP_SERVICE_URL, { '^/api/soil-conditions': '/soil-conditions' }));
app.use('/api/recommend',        authLimiter, jwtMiddleware, proxyTo(CROP_SERVICE_URL, { '^/api/recommend': '/recommend' }));

// Irrigation Service
app.use('/api/irrigation', authLimiter, jwtMiddleware, proxyTo(IRRIGATION_SERVICE_URL, { '^/api/irrigation': '/irrigation' }));
app.use('/api/sensors',    authLimiter, jwtMiddleware, proxyTo(IRRIGATION_SERVICE_URL, { '^/api/sensors': '/sensors' }));
app.use('/api/zones',      authLimiter, jwtMiddleware, proxyTo(IRRIGATION_SERVICE_URL, { '^/api/zones': '/zones' }));

// Python ML Service
app.use('/predict', authLimiter, jwtMiddleware, proxyTo(PYTHON_ML_URL));
app.use('/detect',  authLimiter, jwtMiddleware, proxyTo(PYTHON_ML_URL));

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

// Hanya start server jika dijalankan langsung (bukan saat di-require oleh Jest)
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
