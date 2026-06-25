'use strict';

require('dotenv').config();
const axios = require('axios');

const OAUTH_SERVER_URL = process.env.OAUTH_SERVER_URL || 'http://oauth-server:3002';
const CACHE_TTL_MS = 30 * 1000; // 30 seconds

const introspectionCache = new Map();

function pruneCache() {
  const now = Date.now();
  for (const [key, value] of introspectionCache.entries()) {
    if (now >= value.expiresAt) {
      introspectionCache.delete(key);
    }
  }
}

async function oauthIntrospect(req, res, next) {
  pruneCache();

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

  const cached = introspectionCache.get(token);
  if (cached) {
    if (!cached.active) {
      return res.status(401).json({
        status: 'error',
        code: 401,
        message: 'Unauthorized: Token is inactive or revoked (cached)',
        timestamp: new Date().toISOString(),
      });
    }
    req.oauthToken = cached.tokenData;
    req.user = cached.tokenData;
    return next();
  }

  try {
    // Create axios with timeout
    const axiosInstance = axios.create({ timeout: 5000 });
    
    const response = await axiosInstance.post(
      `${OAUTH_SERVER_URL}/oauth/introspect`,
      new URLSearchParams({ token }),
      {
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      }
    );

    const data = response.data;
    console.log('[oauthIntrospect] Response received:', { active: data.active, sub: data.sub, role: data.role });

    // Check if token is active
    if (!data.active) {
      console.warn('[oauthIntrospect] Token inactive');
      return res.status(401).json({
        status: 'error',
        code: 401,
        message: 'Unauthorized: Token is inactive or revoked',
        timestamp: new Date().toISOString(),
      });
    }

    // Extract token data (everything except 'active' flag)
    const { active, ...tokenData } = data;

    introspectionCache.set(token, {
      active: true,
      tokenData,
      expiresAt: Date.now() + CACHE_TTL_MS,
    });

    console.log('[oauthIntrospect] Token valid, setting user:', { sub: tokenData.sub, role: tokenData.role });
    
    req.oauthToken = tokenData;
    req.user = tokenData;
    next();
  } catch (err) {
    console.error(`[oauthIntrospect] Error: ${err.code} - ${err.message}`, err.response?.status);
    const status = err.response?.status;

    if (status === 401 || status === 403) {
      return res.status(401).json({
        status: 'error',
        code: 401,
        message: 'Unauthorized: OAuth introspection failed',
        timestamp: new Date().toISOString(),
      });
    }

    return res.status(503).json({
      status: 'error',
      code: 503,
      message: 'Service Unavailable: OAuth server is not responding',
      timestamp: new Date().toISOString(),
    });
  }
}

module.exports = oauthIntrospect;
