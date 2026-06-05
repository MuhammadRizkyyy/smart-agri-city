'use strict';

/**
 * Unit Tests — JWT & Rate Limit Middleware
 */

process.env.JWT_SECRET = 'test_secret_key_for_jest';

const jwt = require('jsonwebtoken');
const httpMocks = require('node-mocks-http');

const JWT_SECRET = process.env.JWT_SECRET;

function makeReqRes(authHeader) {
  const req = httpMocks.createRequest({
    method: 'GET',
    url: '/api/farmers',
    headers: authHeader ? { authorization: authHeader } : {},
  });
  const res = httpMocks.createResponse();
  return { req, res };
}

describe('JWT Middleware', () => {
  let jwtMiddleware;

  beforeAll(() => {
    jwtMiddleware = require('../src/middleware/jwt');
  });

  test('token valid → memanggil next() dan set req.user', () => {
    const token = jwt.sign({ sub: '42', role: 'farmer' }, JWT_SECRET, { expiresIn: '1h' });
    const { req, res } = makeReqRes(`Bearer ${token}`);
    const next = jest.fn();

    jwtMiddleware(req, res, next);

    expect(next).toHaveBeenCalledTimes(1);
    expect(req.user).toBeDefined();
    expect(req.user.sub).toBe('42');
    expect(res.statusCode).not.toBe(401);
  });

  test('token expired → return 401 dengan pesan expired', () => {
    const token = jwt.sign({ sub: '99' }, JWT_SECRET, { expiresIn: '-1s' });
    const { req, res } = makeReqRes(`Bearer ${token}`);
    const next = jest.fn();

    jwtMiddleware(req, res, next);

    expect(next).not.toHaveBeenCalled();
    expect(res.statusCode).toBe(401);
    const body = res._getJSONData();
    expect(body.code).toBe(401);
    expect(body.message).toMatch(/expired/i);
  });

  test('token missing → return 401', () => {
    const { req, res } = makeReqRes(null);
    const next = jest.fn();

    jwtMiddleware(req, res, next);

    expect(next).not.toHaveBeenCalled();
    expect(res.statusCode).toBe(401);
    expect(res._getJSONData().code).toBe(401);
  });

  test('header ada tapi tanpa Bearer prefix → return 401', () => {
    const { req, res } = makeReqRes('Token some_token');
    const next = jest.fn();

    jwtMiddleware(req, res, next);

    expect(next).not.toHaveBeenCalled();
    expect(res.statusCode).toBe(401);
  });

  test('token invalid/tampered → return 401 dengan pesan invalid', () => {
    const { req, res } = makeReqRes('Bearer this.is.not.valid');
    const next = jest.fn();

    jwtMiddleware(req, res, next);

    expect(next).not.toHaveBeenCalled();
    expect(res.statusCode).toBe(401);
    const body = res._getJSONData();
    expect(body.message).toMatch(/invalid/i);
  });
});

describe('Rate Limit Middleware', () => {
  let express, app, request;

  beforeAll(() => {
    express = require('express');
    request = require('supertest');
    const { globalLimiter } = require('../src/middleware/rateLimit');

    app = express();
    app.use(globalLimiter);
    app.get('/ping', (req, res) => res.status(200).json({ ok: true }));
  });

  test('request pertama → return 200', async () => {
    const res = await request(app).get('/ping');
    expect(res.statusCode).toBe(200);
  });

  test('burst 101 request → request ke-101 return 429', async () => {
    const promises = [];
    for (let i = 0; i < 100; i++) {
      promises.push(request(app).get('/ping'));
    }
    await Promise.all(promises);

    const blockedRes = await request(app).get('/ping');
    expect(blockedRes.statusCode).toBe(429);
    expect(blockedRes.body.code).toBe(429);
    expect(blockedRes.body.message).toMatch(/too many/i);
  }, 30000);
});
