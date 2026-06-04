const mysql = require("mysql2/promise");
const path = require("path");
require("dotenv").config({ path: path.join(__dirname, "../../../.env") });
const pool = mysql.createPool({
  host: process.env.DB_HOST || "localhost",
  user: process.env.DB_USERNAME || "root",
  password: process.env.DB_PASSWORD || "",
  database: process.env.DB_DATABASE || process.env.DB_NAME || "agriCity",
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
});

// Test koneksi
pool
  .getConnection()
  .then((conn) => {
    console.log("OAuth: Connected to MySQL Database");
    conn.release();
  })
  .catch((err) => {
    console.error("OAuth: Database Connection Failed:", err.message);
  });

module.exports = pool;
