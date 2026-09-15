SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'postal_code'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN postal_code VARCHAR(10) NULL AFTER email'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'street'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN street VARCHAR(150) NULL AFTER postal_code'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'address_number'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN address_number VARCHAR(30) NULL AFTER street'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'complement'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN complement VARCHAR(100) NULL AFTER address_number'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'neighborhood'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN neighborhood VARCHAR(100) NULL AFTER complement'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'latitude'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN latitude DECIMAL(10,7) NULL AFTER state'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'longitude'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'geocoded_at'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN geocoded_at DATETIME NULL AFTER longitude'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'geocoding_provider'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN geocoding_provider VARCHAR(40) NULL AFTER geocoded_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS geocoding_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(40) NOT NULL,
    query_hash CHAR(64) NOT NULL,
    query_text VARCHAR(500) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    found TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_geocoding_cache_provider_query (provider, query_hash)
) ENGINE=InnoDB;
