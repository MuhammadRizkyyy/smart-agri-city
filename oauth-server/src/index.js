const express = require("express");
const bodyParser = require("body-parser");
const cors = require("cors");
const session = require("express-session");
const passport = require("passport");
const path = require("path");
require("dotenv").config({ path: path.join(__dirname, "../../.env") });

const db = require("./config/db");
const { initGoogleStrategy } = require("./config/passport");
const oauthController = require("./controllers/oauthController");
const { initiateGoogleLogin, googleCallback } = require("./controllers/googleAuthController");
const { handleGoogleCallback } = require("./middleware/googleAuth");

const app = express();
// Support both PORT (Docker Compose inject) and OAUTH_SERVER_PORT (.env)
const PORT = process.env.PORT || process.env.OAUTH_SERVER_PORT || 3002;

if (!PORT) {
  console.error("Error: PORT or OAUTH_SERVER_PORT environment variable is not set.");
  process.exit(1);
}

// Middleware 
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: false }));

// Session diperlukan untuk passport redirect flow
app.use(
  session({
    secret: process.env.JWT_SECRET,
    resave: false,
    saveUninitialized: false,
    cookie: { secure: process.env.NODE_ENV === "production", httpOnly: true, maxAge: 600_000 },
  })
);
app.use(passport.initialize());

initGoogleStrategy();

//  Health Check 
app.get("/health", async (req, res) => {
  let dbStatus = "ok";
  let dbMessage = "Connected";

  try {
    const conn = await db.getConnection();
    conn.release();
  } catch (err) {
    dbStatus = "error";
    dbMessage = err.message;
  }

  const isHealthy = dbStatus === "ok";
  res.status(isHealthy ? 200 : 503).json({
    status: isHealthy ? "success" : "error",
    service: "oauth-server",
    timestamp: new Date().toISOString(),
    database: {
      status: dbStatus,
      message: dbMessage,
    },
  });
});

// Standard OAuth Routes 
app.post("/oauth/token", oauthController.token);
app.post("/oauth/introspect", oauthController.introspect);
app.post("/oauth/revoke", oauthController.revoke);

// Google OAuth Routes 
app.get("/oauth/google", initiateGoogleLogin);
app.get("/oauth/google/callback", handleGoogleCallback, googleCallback);

// Global Error Handler 
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({ status: "error", message: "Internal Server Error" });
});

app.listen(PORT, () => {
  console.log(`OAuth Server running on http://localhost:${PORT}`);
});
