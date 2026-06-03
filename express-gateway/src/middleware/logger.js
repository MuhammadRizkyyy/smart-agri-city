'use strict';

const morgan = require('morgan');
const fs = require('fs');
const path = require('path');

const logsDir = path.join(__dirname, '../../logs');
if (!fs.existsSync(logsDir)) {
  fs.mkdirSync(logsDir, { recursive: true });
}

const accessLogStream = fs.createWriteStream(
  path.join(logsDir, 'gateway.log'),
  { flags: 'a' }
);

function resolveUpstream(url) {
  if (url.startsWith('/oauth'))          return 'oauth-server:3002';
  if (url.startsWith('/iot'))            return 'php-irrigation:8002';
  if (url.startsWith('/api/farmers'))    return 'php-farmer:8000';
  if (url.startsWith('/api/lands'))      return 'php-farmer:8000';
  if (url.startsWith('/api/harvests'))   return 'php-farmer:8000';
  if (url.startsWith('/api/crops'))      return 'php-crop:8001';
  if (url.startsWith('/api/alerts'))     return 'php-crop:8001';
  if (url.startsWith('/api/irrigation')) return 'php-irrigation:8002';
  if (url.startsWith('/api/sensors'))    return 'php-irrigation:8002';
  if (url.startsWith('/predict'))        return 'python-ml:5000';
  if (url.startsWith('/detect'))         return 'python-ml:5000';
  if (url.startsWith('/health'))         return 'health-aggregator';
  return 'gateway';
}

morgan.token('upstream', (req) => resolveUpstream(req.originalUrl || req.url));

morgan.token('iso-timestamp', () => new Date().toISOString());

const LOG_FORMAT =
  '[:iso-timestamp] :method :url → :upstream | :status | :response-time ms';

const consoleLogger = morgan(LOG_FORMAT, {
  stream: {
    write: (message) => process.stdout.write(message),
  },
});

const fileLogger = morgan(LOG_FORMAT, { stream: accessLogStream });

function logger(req, res, next) {
  consoleLogger(req, res, (err) => {
    if (err) return next(err);
    fileLogger(req, res, next);
  });
}

module.exports = logger;
