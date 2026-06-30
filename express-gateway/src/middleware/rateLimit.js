'use strict';

const rateLimit = require('express-rate-limit');
const { rateLimitHitsTotal } = require('./metrics');

const GLOBAL_RATE_WINDOW = parseInt(process.env.RATE_LIMIT_GLOBAL_WINDOW_MS || '900000'); 
const GLOBAL_RATE_MAX = parseInt(process.env.RATE_LIMIT_GLOBAL_MAX || '1000'); 
const AUTH_RATE_WINDOW = parseInt(process.env.RATE_LIMIT_AUTH_WINDOW_MS || '60000'); 
const AUTH_RATE_MAX = parseInt(process.env.RATE_LIMIT_AUTH_MAX || '1000'); 
const IOT_RATE_WINDOW = parseInt(process.env.RATE_LIMIT_IOT_WINDOW_MS || '60000'); 
const IOT_RATE_MAX = parseInt(process.env.RATE_LIMIT_IOT_MAX || '10000');

const tooManyRequestsResponse = {
  status: 'error',
  code: 429,
  message: 'Too many requests',
};

const globalLimiter = rateLimit({
  windowMs: GLOBAL_RATE_WINDOW,
  max: GLOBAL_RATE_MAX,
  standardHeaders: true,
  legacyHeaders: false,
  message: tooManyRequestsResponse,
  handler(req, res, options) {
    rateLimitHitsTotal.inc({ limiter: 'global' });
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

const authLimiter = rateLimit({
  windowMs: AUTH_RATE_WINDOW,
  max: AUTH_RATE_MAX,
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => req.headers['authorization'] || req.ip,
  message: tooManyRequestsResponse,
  handler(req, res, options) {
    rateLimitHitsTotal.inc({ limiter: 'auth' });
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

const iotLimiter = rateLimit({
  windowMs: IOT_RATE_WINDOW,
  max: IOT_RATE_MAX,
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => req.headers['authorization'] || req.ip,
  message: tooManyRequestsResponse,
  handler(req, res, options) {
    rateLimitHitsTotal.inc({ limiter: 'iot' });
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

module.exports = { globalLimiter, authLimiter, iotLimiter };
