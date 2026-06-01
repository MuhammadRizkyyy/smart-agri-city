'use strict';

const rateLimit = require('express-rate-limit');

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
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

const authLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 500,
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: (req) => req.headers['authorization'] || req.ip,
  message: tooManyRequestsResponse,
  handler(req, res, next, options) {
    res.status(options.statusCode).json({
      ...tooManyRequestsResponse,
      timestamp: new Date().toISOString(),
    });
  },
});

module.exports = { globalLimiter, authLimiter };
