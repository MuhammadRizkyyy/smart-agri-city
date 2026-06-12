'use strict';

const client = require('prom-client');

const httpRequestsTotal = new client.Counter({
  name: 'http_requests_total',
  help: 'Total HTTP requests handled by the gateway',
  labelNames: ['method', 'path', 'status'],
});

const httpRequestDuration = new client.Histogram({
  name: 'http_request_duration_seconds',
  help: 'HTTP request duration in seconds (gateway)',
  labelNames: ['method', 'path', 'status'],
  buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5],
});

const rateLimitHitsTotal = new client.Counter({
  name: 'rate_limit_hits_total',
  help: 'Total requests blocked by rate limiter',
  labelNames: ['limiter'],
});

function normalisePath(originalUrl) {
  return originalUrl
    .replace(/\/\d+/g, '/:id')  
    .split('?')[0]               
    .replace(/\/$/, '') || '/';  
}

function requestMetrics(req, res, next) {
  const start = process.hrtime.bigint();

  res.on('finish', () => {
    const durationSec = Number(process.hrtime.bigint() - start) / 1e9;
    const path   = normalisePath(req.originalUrl);
    const method = req.method;
    const status = String(res.statusCode);

    httpRequestsTotal.inc({ method, path, status });
    httpRequestDuration.observe({ method, path, status }, durationSec);
  });

  next();
}

module.exports = { requestMetrics, httpRequestsTotal, httpRequestDuration, rateLimitHitsTotal };
