const OAuthServer = require("oauth2-server");
const oauthModel = require("../models/oauthModel");

const oauth = new OAuthServer({
  model: oauthModel,
  accessTokenLifetime: 3600,
  refreshTokenLifetime: 604800,
});

const authenticate = async (req, res, next) => {
  const request = new OAuthServer.Request(req);
  const response = new OAuthServer.Response(res);

  try {
    const token = await oauth.authenticate(request, response);
    req.user = token.user;
    req.client = token.client;
    next();
  } catch (err) {
    res.status(err.code || 401).json({
      status: "error",
      message: err.message || "Unauthorized",
    });
  }
};

module.exports = authenticate;
