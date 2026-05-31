const db = require("../config/db");
const bcrypt = require("bcryptjs");
const jwt = require("jsonwebtoken");

module.exports = {
  getClient: async (clientId, clientSecret) => {
    const [clients] = await db.execute(
      "SELECT client_id, client_secret, grant_types, redirect_uri FROM oauth_clients WHERE client_id = ?",
      [clientId],
    );
    const client = clients[0];

    if (!client) return null;
    if (clientSecret && client.client_secret !== clientSecret) return null;

    return {
      id: client.client_id,
      grants: client.grant_types.split(","),
      redirectUris: [client.redirect_uri],
    };
  },

  saveToken: async (token, client, user) => {
    await db.execute(
      "INSERT INTO oauth_tokens (client_id, user_id, access_token, refresh_token, expires_at) VALUES (?, ?, ?, ?, ?)",
      [
        client.id,
        user ? user.id : null,
        token.accessToken,
        token.refreshToken || null,
        token.accessTokenExpiresAt,
      ],
    );

    return {
      accessToken: token.accessToken,
      accessTokenExpiresAt: token.accessTokenExpiresAt,
      refreshToken: token.refreshToken,
      refreshTokenExpiresAt: token.refreshTokenExpiresAt,
      client: { id: client.id },
      user: user || { id: null },
    };
  },

  getAccessToken: async (accessToken) => {
    const [tokens] = await db.execute(
      "SELECT t.*, u.role FROM oauth_tokens t LEFT JOIN frm_farmers u ON t.user_id = u.id WHERE t.access_token = ?",
      [accessToken],
    );
    const token = tokens[0];

    if (!token) return null;

    return {
      accessToken: token.access_token,
      accessTokenExpiresAt: token.expires_at,
      client: { id: token.client_id },
      user: { id: token.user_id, role: token.role },
    };
  },

  getUser: async (username, password) => {
    const [users] = await db.execute(
      "SELECT id, name, email, role, password FROM frm_farmers WHERE email = ?",
      [username],
    );
    const user = users[0];

    if (!user) return null;
    const isValid = await bcrypt.compare(password, user.password);
    if (!isValid) return null;

    return { id: user.id, role: user.role };
  },

  getRefreshToken: async (refreshToken) => {
    const [tokens] = await db.execute(
      "SELECT * FROM oauth_tokens WHERE refresh_token = ?",
      [refreshToken],
    );
    const token = tokens[0];
    if (!token) return null;

    return {
      refreshToken: token.refresh_token,
      client: { id: token.client_id },
      user: { id: token.user_id },
    };
  },

  revokeToken: async (token) => {
    await db.execute(
      "DELETE FROM oauth_tokens WHERE access_token = ? OR refresh_token = ?",
      [token, token],
    );
    return true;
  },

  getUserFromClient: async (client) => {
    return { id: null, role: "client" };
  },

  generateAccessToken: async (client, user, scope) => {
    const payload = {
      sub: user.id,
      role: user.role || "client",
      client_id: client.id,
      iat: Math.floor(Date.now() / 1000),
    };

    return jwt.sign(payload, process.env.JWT_SECRET, {
      expiresIn: "1h",
    });
  },
};
