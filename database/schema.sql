-- database/schema.sql
CREATE DATABASE IF NOT EXISTS agriCity;
USE agriCity;

-- IRRIGATION SERVICE (prefix irr_)
CREATE TABLE irr_zones (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    area_ha         DECIMAL(6,2) NOT NULL,
    status          VARCHAR(50) DEFAULT 'active',
    lat             DECIMAL(10,7) NOT NULL,
    lng             DECIMAL(10,7) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_irr_zones_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE irr_sensor_readings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT NOT NULL,
    moisture        DECIMAL(5,2) NOT NULL,
    temperature     DECIMAL(5,2) NOT NULL,
    ph              DECIMAL(4,2) NOT NULL,
    nitrogen        DECIMAL(6,2) DEFAULT 0,
    phosphorus      DECIMAL(6,2) DEFAULT 0,
    potassium       DECIMAL(6,2) DEFAULT 0,
    air_temp        DECIMAL(5,2) DEFAULT 0,
    air_humidity    DECIMAL(5,2) DEFAULT 0,
    light_lux       DECIMAL(10,2) DEFAULT 0,
    recorded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_irr_sensor_readings_zone_recorded (zone_id, recorded_at),
    FOREIGN KEY (zone_id) REFERENCES irr_zones(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE irr_irrigation_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT NOT NULL,
    started_at      TIMESTAMP NOT NULL,
    ended_at        TIMESTAMP NULL DEFAULT NULL,
    volume_liters   DECIMAL(10,2) DEFAULT 0,
    trigger_type    ENUM('manual','otomatis_ml','otomatis_jadwal') DEFAULT 'manual',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_irr_logs_zone_started (zone_id, started_at),
    FOREIGN KEY (zone_id) REFERENCES irr_zones(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- FARMER SERVICE (prefix frm_)

CREATE TABLE IF NOT EXISTS frm_farmers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(100) UNIQUE NULL DEFAULT NULL,
    password        VARCHAR(255) NULL DEFAULT NULL,
    google_id       VARCHAR(100) UNIQUE NULL DEFAULT NULL,
    avatar          VARCHAR(500) NULL DEFAULT NULL,
    role            ENUM('petani','petugas','admin') DEFAULT 'petani',
    nik             VARCHAR(16) UNIQUE NOT NULL,
    phone           VARCHAR(20),
    address         TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_frm_farmers_nik (nik),
    INDEX idx_frm_farmers_email (email),
    INDEX idx_frm_farmers_google_id (google_id),
    INDEX idx_frm_farmers_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE frm_lands (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id       INT NOT NULL,
    zone_id         INT NULL, -- Related to irr_zones for physical location mapping
    name            VARCHAR(100) NOT NULL,
    area_ha         DECIMAL(6,2) NOT NULL,
    soil_type       VARCHAR(50) NOT NULL,
    lat             DECIMAL(10,7) NOT NULL,
    lng             DECIMAL(10,7) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_frm_lands_farmer (farmer_id),
    INDEX idx_frm_lands_zone (zone_id),
    FOREIGN KEY (farmer_id) REFERENCES frm_farmers(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES irr_zones(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE frm_harvests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    land_id         INT NOT NULL,
    crop_type       VARCHAR(50) NOT NULL,
    yield_ton       DECIMAL(8,2) NOT NULL,
    harvest_date    DATE NOT NULL,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_frm_harvests_land (land_id),
    INDEX idx_frm_harvests_crop (crop_type),
    FOREIGN KEY (land_id) REFERENCES frm_lands(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- CROP SERVICE (prefix crp_)

CREATE TABLE crp_crop_schedules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    land_id         INT NOT NULL,
    crop_type       VARCHAR(50) NOT NULL,
    plant_date      DATE NOT NULL,
    expected_harvest DATE NOT NULL,
    growth_phase    VARCHAR(50) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crp_schedules_land (land_id),
    FOREIGN KEY (land_id) REFERENCES frm_lands(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crp_alerts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT NOT NULL,
    alert_type      VARCHAR(50) NOT NULL,
    severity        VARCHAR(20) NOT NULL, -- e.g., 'rendah', 'sedang', 'tinggi', 'kritis'
    description     TEXT NOT NULL,
    resolved_at     TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crp_alerts_zone (zone_id),
    INDEX idx_crp_alerts_severity (severity),
    FOREIGN KEY (zone_id) REFERENCES irr_zones(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crp_soil_conditions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    land_id         INT NOT NULL,
    ph              DECIMAL(4,2) NOT NULL,
    nitrogen        DECIMAL(6,2) NOT NULL,
    phosphorus      DECIMAL(6,2) NOT NULL,
    potassium       DECIMAL(6,2) NOT NULL,
    recorded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crp_soil_land (land_id),
    FOREIGN KEY (land_id) REFERENCES frm_lands(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- OAUTH SERVER (prefix oauth_)

-- Migration: tambah kolom google_id dan avatar jika belum ada (untuk upgrade dari schema lama)
-- ALTER TABLE frm_farmers ADD COLUMN IF NOT EXISTS google_id VARCHAR(100) UNIQUE NULL DEFAULT NULL;
-- ALTER TABLE frm_farmers ADD COLUMN IF NOT EXISTS avatar VARCHAR(500) NULL DEFAULT NULL;
-- ALTER TABLE frm_farmers ADD INDEX IF NOT EXISTS idx_frm_farmers_google_id (google_id);

CREATE TABLE IF NOT EXISTS oauth_clients (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       VARCHAR(80) UNIQUE NOT NULL,
    client_secret   VARCHAR(80) NOT NULL,
    grant_types     VARCHAR(200) DEFAULT 'password,client_credentials,refresh_token',
    redirect_uri    TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oauth_tokens (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    client_id                 VARCHAR(80) NOT NULL,
    user_id                   INT NULL,
    access_token              VARCHAR(500) UNIQUE NOT NULL,
    refresh_token             VARCHAR(500) NULL,
    expires_at                TIMESTAMP NOT NULL,
    refresh_token_expires_at  TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_oauth_tokens_client (client_id),
    INDEX idx_oauth_tokens_user (user_id),
    INDEX idx_oauth_tokens_refresh (refresh_token),
    FOREIGN KEY (client_id) REFERENCES oauth_clients(client_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES frm_farmers(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;