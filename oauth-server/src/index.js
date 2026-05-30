const express = require('express');
const app = express();
const PORT = process.env.PORT || 3002;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.get('/health', (req, res) => {
  res.json({ status: 'ok', service: 'oauth-server', timestamp: new Date().toISOString() });
});

app.post('/oauth/token', (req, res) => {
  res.status(501).json({ error: 'not_implemented', error_description: 'OAuth server not yet implemented' });
});

app.listen(PORT, '0.0.0.0', () => {
  console.log(`OAuth Server running on port ${PORT}`);
});
