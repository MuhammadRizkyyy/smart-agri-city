const OAuthServer = require("oauth2-server");
const Request = OAuthServer.Request;
const Response = OAuthServer.Response;
const oauthModel = require("../models/oauthModel");
const jwt = require("jsonwebtoken");

const oauth = new OAuthServer({
  model: oauthModel,
  accessTokenLifetime: 3600, // 1 jam sesuai issue
  refreshTokenLifetime: 604800, // 7 hari sesuai issue
  allowBearerTokensInQueryString: true,
});

module.exports = {
  // Handler untuk POST /oauth/token
  token: async (req, res) => {
    const request = new Request(req);
    const response = new Response(res);

    try {
      const token = await oauth.token(request, response);
      res.json({
        access_token: token.accessToken,
        token_type: "Bearer",
        expires_in: 3600,
        refresh_token: token.refreshToken,
      });
    } catch (err) {
      res.status(err.code || 500).json({
        status: "error",
        message: err.message,
      });
    }
  },

  // Handler untuk POST /oauth/introspect
  introspect: async (req, res) => {
    const { token } = req.body;
    if (!token) {
      return res
        .status(400)
        .json({ active: false, message: "Token is required" });
    }

    try {
      // 1. Cek keberadaan token di database
      const tokenData = await oauthModel.getAccessToken(token);

      // 2. Jika tidak ada di DB atau sudah expired
      if (!tokenData || new Date() > new Date(tokenData.accessTokenExpiresAt)) {
        return res.json({ active: false });
      }

      // 3. Jika ada dan valid
      res.json({
        active: true,
        client_id: tokenData.client.id,
        sub: tokenData.user.id,
        role: tokenData.user.role,
        exp: Math.floor(
          new Date(tokenData.accessTokenExpiresAt).getTime() / 1000,
        ),
      });
    } catch (err) {
      res.status(500).json({ active: false, error: err.message });
    }
  },

  // Handler untuk POST /oauth/revoke
  revoke: async (req, res) => {
    const { token } = req.body;
    if (!token) {
      return res
        .status(400)
        .json({ status: "error", message: "Token is required" });
    }
    try {
      // Kita kirimkan token string-nya saja
      await oauthModel.revokeToken(token);

      res.json({
        status: "success",
        message: "Token revoked successfully",
      });
    } catch (err) {
      res.status(500).json({
        status: "error",
        message: err.message,
      });
    }
  },
};
