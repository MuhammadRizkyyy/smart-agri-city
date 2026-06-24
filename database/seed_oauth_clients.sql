USE agriCity;

INSERT INTO oauth_clients (client_id, client_secret, grant_types, redirect_uri) 
VALUES 
  ('web-client', 'web_client_secret_google', 'password,client_credentials,refresh_token', 'http://localhost:3002/oauth/callback'),
  ('web-app', 'web_secret_123', 'password,refresh_token', 'http://localhost:3000/callback'),
  ('mobile-app', 'mobile_secret_456', 'password,refresh_token', 'com.agri.city://oauth/callback')
ON DUPLICATE KEY UPDATE 
  client_secret = VALUES(client_secret),
  grant_types = VALUES(grant_types),
  redirect_uri = VALUES(redirect_uri);

SELECT 'OAuth Clients seeded successfully' AS status;
SELECT COUNT(*) AS total_clients FROM oauth_clients;
SELECT * FROM oauth_clients;
