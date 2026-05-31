const express = require("express");
const bodyParser = require("body-parser");
const cors = require("cors");
const path = require("path");
require("dotenv").config({ path: path.join(__dirname, "../../.env") });

const oauthController = require("./controllers/oauthController");

const app = express();
const PORT = process.env.OAUTH_SERVER_PORT;

if (!PORT) {
  console.error("Error: OAUTH_SERVER_PORT environment variable is not set.");
  process.exit(1);
}

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: false }));

// Health Check
app.get("/health", (req, res) => {
  res.json({
    status: "success",
    service: "oauth-server",
    timestamp: new Date().toISOString(),
  });
});

// OAuth Routes
app.post("/oauth/token", oauthController.token);
app.post("/oauth/introspect", oauthController.introspect);
app.post("/oauth/revoke", oauthController.revoke);

// Error Handling Global
app.use((err, req, res, next) => {
  console.error(err.stack);
  res.status(500).json({ status: "error", message: "Internal Server Error" });
});

app.listen(PORT, () => {
  console.log(`OAuth Server running on http://localhost:${PORT}`);
});
