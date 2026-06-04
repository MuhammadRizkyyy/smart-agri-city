const passport = require("passport");
const googleAuthModel = require("../models/googleAuthModel");

/**
 * GET /oauth/google
 * Redirect browser ke halaman consent Google.
 */
const initiateGoogleLogin = passport.authenticate("google", {
  scope: ["profile", "email"],
  session: false,
});

// GET /oauth/google/callback
const googleCallback = async (req, res) => {
  const { user, authError } = req;

  if (!user) {
    const message = authError || "Google authentication failed";
    const frontendUrl = process.env.FRONTEND_URL;

    if (frontendUrl) {
      return res.redirect(`${frontendUrl}/auth/error?message=${encodeURIComponent(message)}`);
    }
    return res.status(401).json({ status: "error", message });
  }

  try {
    const clientId = process.env.GOOGLE_OAUTH_CLIENT_ID || "web-client";
    const { accessToken, refreshToken, expiresAt } = await googleAuthModel.issueTokens(user, clientId);

    const frontendUrl = process.env.FRONTEND_URL;
    if (frontendUrl) {
      return res.redirect(
        `${frontendUrl}/auth/callback` +
          `?access_token=${accessToken}` +
          `&refresh_token=${refreshToken}` +
          `&expires_in=3600`
      );
    }

    return res.json({
      access_token: accessToken,
      token_type: "Bearer",
      expires_in: 3600,
      refresh_token: refreshToken,
      expires_at: expiresAt,
    });
  } catch (err) {
    console.error("Google callback error:", err);
    res.status(500).json({ status: "error", message: "Failed to issue token" });
  }
};

module.exports = { initiateGoogleLogin, googleCallback };
