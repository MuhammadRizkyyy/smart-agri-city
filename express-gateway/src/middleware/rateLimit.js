'use strict';

const rateLimit = require('express-rate-limit');
const { rateLimitHitsTotal } = require('./metrics');

const tooManyRequestsResponse = {
  status: 'error',
  code: 429,
  message: 'Too many requests',
};

const globalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
  message: tooManyRequestsResponse,
  handler(req, res, next, options) {
    rateLimitHitsTotal.inc({ limiter: 'global' });
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

const authLimiter = rateLimit({
  windowMs: 1 * 60 * 1000, // 1 minute
  max: 500,
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => req.headers['authorization'] || req.ip,
  message: tooManyRequestsResponse,
  handler(req, res, next, options) {
    rateLimitHitsTotal.inc({ limiter: 'auth' });
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

const iotLimiter = rateLimit({
  windowMs: 1 * 60 * 1000, // 1 minute
  max: 2000, // Higher limit untuk IoT data ingestion
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => req.headers['authorization'] || req.ip,
  message: tooManyRequestsResponse,
  handler(req, res, next, options) {
    rateLimitHitsTotal.inc({ limiter: 'iot' });
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

module.exports = { globalLimiter, authLimiter, iotLimiter };
