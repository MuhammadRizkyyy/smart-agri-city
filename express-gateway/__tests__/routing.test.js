'use strict';

/**
 * Integration Tests — Routing & Proxy
 */

process.env.JWT_SECRET             = 'test_secret_key_for_jest';
process.env.OAUTH_SERVER_URL       = 'http://127.0.0.1:19002';
process.env.FARMER_SERVICE_URL     = 'http://127.0.0.1:19000';
process.env.CROP_SERVICE_URL       = 'http://127.0.0.1:19001';
process.env.IRRIGATION_SERVICE_URL = 'http://127.0.0.1:19003';
process.env.PYTHON_ML_URL          = 'http://127.0.0.1:19005';

const jwt     = require('jsonwebtoken');
const request = require('supertest');
const http    = require('http');
const axios   = require('axios');

const stubs = {};

function startStub(port, handler) {
  return new Promise((resolve, reject) => {
    const server = http.createServer(handler);
    server.listen(port, '127.0.0.1', () => resolve(server));
    server.on('error', reject);
  });
}

beforeAll(async () => {
  stubs.farmer = await startStub(19000, (req, res) => {
    res.setHeader('Content-Type', 'application/json');
    if (req.url === '/health') {
      res.writeHead(200);
      res.end(JSON.stringify({ status: 'ok' }));
    } else if (req.url.startsWith('/farmers')) {
      // pathRewrite dari dev: '^/api/farmers' → '/farmers'
      res.writeHead(200);
      res.end(JSON.stringify({
        status: 'success',
        forwarded_auth: req.headers['authorization'] || '',
        data: [],
      }));
    } else {
      res.writeHead(404); res.end('{}');
    }
  });

  stubs.irrigation = await startStub(19003, (req, res) => {
    res.setHeader('Content-Type', 'application/json');
    if (req.url === '/health') {
      res.writeHead(200);
      res.end(JSON.stringify({ status: 'ok' }));
    } else if (req.url.startsWith('/sensor') || req.url === '/') {
      // pathRewrite dari dev: '^/iot' → '' sehingga '/iot/sensor' → '/sensor'
      res.writeHead(200);
      res.end(JSON.stringify({ status: 'success', message: 'sensor received' }));
    } else {
      res.writeHead(404); res.end('{}');
    }
  });
});

afterAll(async () => {
  await Promise.all(Object.values(stubs).map((s) => new Promise((r) => s.close(r))));
});

const app = require('../src/index');

const makeValidJWT = () =>
  jwt.sign({ sub: '1', role: 'farmer' }, process.env.JWT_SECRET, { expiresIn: '1h' });

describe('GET /health', () => {
  let getSpy;
  afterEach(() => getSpy && getSpy.mockRestore());

  test('semua upstream up → return 200 dan berisi semua service name', async () => {
    getSpy = jest.spyOn(axios, 'get').mockResolvedValue({ data: { status: 'ok' } });

    const res = await request(app).get('/health');
    expect([200, 207]).toContain(res.statusCode);
    const names = res.body.upstreams.map((u) => u.name);
    ['php-farmer', 'php-crop', 'php-irrigation', 'python-ml'].forEach((n) =>
      expect(names).toContain(n)
    );
  });

  test('semua upstream down → return 207 degraded', async () => {
    getSpy = jest.spyOn(axios, 'get').mockRejectedValue(
      Object.assign(new Error('ECONNREFUSED'), { code: 'ECONNREFUSED' })
    );

    const res = await request(app).get('/health');
    expect(res.statusCode).toBe(207);
    expect(res.body.status).toBe('degraded');
  });
});

describe('GET /api/farmers proxy', () => {
  test('JWT valid → di-forward ke FARMER_SERVICE_URL dengan header Authorization', async () => {
    const token = makeValidJWT();
    const res = await request(app)
      .get('/api/farmers')
      .set('Authorization', `Bearer ${token}`);

    expect(res.statusCode).toBe(200);
    expect(res.body.forwarded_auth).toBe(`Bearer ${token}`);
  });

  test('tanpa JWT → gateway return 401 sebelum proxy', async () => {
    const res = await request(app).get('/api/farmers');
    expect(res.statusCode).toBe(401);
    expect(res.body.code).toBe(401);
  });

  test('JWT expired → gateway return 401 dengan pesan expired', async () => {
    const expiredToken = jwt.sign({ sub: '1' }, process.env.JWT_SECRET, { expiresIn: '-1s' });
    const res = await request(app)
      .get('/api/farmers')
      .set('Authorization', `Bearer ${expiredToken}`);

    expect(res.statusCode).toBe(401);
    expect(res.body.message).toMatch(/expired/i);
  });
});

describe('POST /iot/sensor — IoT route (OAuth client_credentials)', () => {
  let postSpy;
  afterEach(() => postSpy && postSpy.mockRestore());

  test('OAuth token valid (active:true) → di-forward ke IRRIGATION_SERVICE_URL (200)', async () => {
    postSpy = jest.spyOn(axios, 'post').mockResolvedValueOnce({
      data: { active: true, client_id: 'iot_device', scope: 'sensor:write' },
    });

    const res = await request(app)
      .post('/iot/sensor')
      .set('Authorization', 'Bearer valid_oauth_token')
      .send({ zone: 'zona1', soil_moisture: 45.0 });

    expect(res.statusCode).toBe(200);
    expect(res.body.status).toBe('success');
  });

  test('OAuth token inactive → gateway return 401 tidak sampai upstream', async () => {
    postSpy = jest.spyOn(axios, 'post').mockResolvedValueOnce({
      data: { active: false },
    });

    const res = await request(app)
      .post('/iot/sensor')
      .set('Authorization', 'Bearer inactive_token')
      .send({ zone: 'zona1' });

    expect(res.statusCode).toBe(401);
  });

  test('tanpa Authorization header → return 401', async () => {
    const res = await request(app)
      .post('/iot/sensor')
      .send({ zone: 'zona1' });

    expect(res.statusCode).toBe(401);
  });
});

describe('Route tidak terdaftar', () => {
  test('GET /unknown-route → 404', async () => {
    const res = await request(app).get('/unknown-route-xyz');
    expect(res.statusCode).toBe(404);
    expect(res.body.code).toBe(404);
  });
});
