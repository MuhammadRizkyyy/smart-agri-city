-- Add flow_rate field to irr_zones table
ALTER TABLE irr_zones ADD COLUMN flow_rate_liters_per_minute DECIMAL(8,2) DEFAULT 100.00;
