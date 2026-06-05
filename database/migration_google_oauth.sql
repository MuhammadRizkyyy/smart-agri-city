-- Migration: Tambah kolom untuk Google OAuth
-- Jalankan sekali jika database sudah running
-- Compatible dengan MySQL 8.0

USE agriCity;

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE: frm_farmers
-- ══════════════════════════════════════════════════════════════════════════

-- Helper stored procedure untuk ADD COLUMN IF NOT EXISTS
DELIMITER //

DROP PROCEDURE IF EXISTS AddColumnIfNotExists//
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(128),
    IN columnName VARCHAR(128),
    IN columnDef VARCHAR(512)
)
BEGIN
    DECLARE colExists INT;
    
    SELECT COUNT(*) INTO colExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = tableName
      AND COLUMN_NAME = columnName;
    
    IF colExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', tableName, ' ADD COLUMN ', columnName, ' ', columnDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✅ Added column ', columnName, ' to ', tableName) AS message;
    ELSE
        SELECT CONCAT('ℹ️  Column ', columnName, ' already exists in ', tableName) AS message;
    END IF;
END//

DELIMITER ;

-- Tambah kolom-kolom Google OAuth ke frm_farmers
CALL AddColumnIfNotExists('frm_farmers', 'email', 'VARCHAR(100) UNIQUE NULL DEFAULT NULL AFTER name');
CALL AddColumnIfNotExists('frm_farmers', 'password', 'VARCHAR(255) NULL DEFAULT NULL AFTER email');
CALL AddColumnIfNotExists('frm_farmers', 'google_id', 'VARCHAR(100) UNIQUE NULL DEFAULT NULL AFTER password');
CALL AddColumnIfNotExists('frm_farmers', 'avatar', 'VARCHAR(500) NULL DEFAULT NULL AFTER google_id');
CALL AddColumnIfNotExists('frm_farmers', 'role', "ENUM('petani','petugas','admin') DEFAULT 'petani' AFTER avatar");
CALL AddColumnIfNotExists('frm_farmers', 'deleted_at', 'TIMESTAMP NULL DEFAULT NULL AFTER updated_at');

-- ══════════════════════════════════════════════════════════════════════════
-- TABLE: oauth_tokens
-- ══════════════════════════════════════════════════════════════════════════

-- Tambah kolom refresh token ke oauth_tokens
CALL AddColumnIfNotExists('oauth_tokens', 'refresh_token', 'VARCHAR(500) NULL DEFAULT NULL AFTER access_token');
CALL AddColumnIfNotExists('oauth_tokens', 'refresh_token_expires_at', 'TIMESTAMP NULL DEFAULT NULL AFTER expires_at');

-- ══════════════════════════════════════════════════════════════════════════
-- INDEXES (Manual karena CREATE INDEX IF NOT EXISTS tidak support MySQL 8.0)
-- ══════════════════════════════════════════════════════════════════════════

-- Cek dan tambah index untuk frm_farmers.email
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'frm_farmers' 
  AND INDEX_NAME = 'idx_frm_farmers_email';

SET @sql = IF(@index_exists = 0, 
  'ALTER TABLE frm_farmers ADD INDEX idx_frm_farmers_email (email)',
  'SELECT "ℹ️  Index idx_frm_farmers_email already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cek dan tambah index untuk frm_farmers.google_id
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'frm_farmers' 
  AND INDEX_NAME = 'idx_frm_farmers_google_id';

SET @sql = IF(@index_exists = 0, 
  'ALTER TABLE frm_farmers ADD INDEX idx_frm_farmers_google_id (google_id)',
  'SELECT "ℹ️  Index idx_frm_farmers_google_id already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cek dan tambah index untuk frm_farmers.deleted_at
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'frm_farmers' 
  AND INDEX_NAME = 'idx_frm_farmers_deleted';

SET @sql = IF(@index_exists = 0, 
  'ALTER TABLE frm_farmers ADD INDEX idx_frm_farmers_deleted (deleted_at)',
  'SELECT "ℹ️  Index idx_frm_farmers_deleted already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cek dan tambah index untuk oauth_tokens.refresh_token
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'oauth_tokens' 
  AND INDEX_NAME = 'idx_oauth_tokens_refresh';

SET @sql = IF(@index_exists = 0, 
  'ALTER TABLE oauth_tokens ADD INDEX idx_oauth_tokens_refresh (refresh_token)',
  'SELECT "ℹ️  Index idx_oauth_tokens_refresh already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ══════════════════════════════════════════════════════════════════════════
-- OAUTH CLIENT
-- ══════════════════════════════════════════════════════════════════════════

-- Tambah oauth client untuk Google login
INSERT IGNORE INTO oauth_clients (id, client_id, client_secret, redirect_uri) 
VALUES (7, 'web-client', 'web_client_secret_google', 'http://localhost:3002/oauth/google/callback');

-- ══════════════════════════════════════════════════════════════════════════
-- CLEANUP
-- ══════════════════════════════════════════════════════════════════════════

DROP PROCEDURE IF EXISTS AddColumnIfNotExists;

-- ══════════════════════════════════════════════════════════════════════════
-- SUMMARY
-- ══════════════════════════════════════════════════════════════════════════

SELECT '✅ Migration completed successfully!' AS status;

SELECT 'frm_farmers' AS table_name, COUNT(*) AS total_columns 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_farmers';

SELECT 'oauth_tokens' AS table_name, COUNT(*) AS total_columns 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oauth_tokens';

SELECT 'oauth_clients' AS table_name, COUNT(*) AS total_rows 
FROM oauth_clients;
