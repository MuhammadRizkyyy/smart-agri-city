'use strict';

require('dotenv').config();
const jwt = require('jsonwebtoken');

const JWT_SECRET = process.env.JWT_SECRET;

if (!JWT_SECRET) {
  console.error('[JWT] FATAL: JWT_SECRET environment variable is not set');
  process.exit(1);
}

function jwtMiddleware(req, res, next) {
  const authHeader = req.headers['authorization'];

  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: 'Unauthorized: Missing or malformed Authorization header',
      timestamp: new Date().toISOString(),
    });
  }

  const token = authHeader.slice(7);

  try {
    const decoded = jwt.verify(token, JWT_SECRET);
    req.user = decoded;
    next();
  } catch (err) {
    const isExpired = err.name === 'TokenExpiredError';
    return res.status(401).json({
      status: 'error',
      code: 401,
      message: isExpired
        ? 'Unauthorized: Token has expired'
        : 'Unauthorized: Invalid token',
      timestamp: new Date().toISOString(),
    });
  }
}

module.exports = jwtMiddleware;
