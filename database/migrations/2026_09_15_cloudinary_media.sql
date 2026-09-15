SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'avatar_url'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN avatar_url VARCHAR(700) NULL AFTER phone'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'avatar_public_id'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN avatar_public_id VARCHAR(255) NULL AFTER avatar_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'logo_url'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN logo_url VARCHAR(700) NULL AFTER description'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'logo_public_id'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN logo_public_id VARCHAR(255) NULL AFTER logo_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'cover_url'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN cover_url VARCHAR(700) NULL AFTER logo_public_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'establishments' AND column_name = 'cover_public_id'),
    'SELECT 1',
    'ALTER TABLE establishments ADD COLUMN cover_public_id VARCHAR(255) NULL AFTER cover_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'services' AND column_name = 'image_url'),
    'SELECT 1',
    'ALTER TABLE services ADD COLUMN image_url VARCHAR(700) NULL AFTER description'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'services' AND column_name = 'image_public_id'),
    'SELECT 1',
    'ALTER TABLE services ADD COLUMN image_public_id VARCHAR(255) NULL AFTER image_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS media_cleanup_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(255) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_media_cleanup_public_id (public_id),
    KEY idx_media_cleanup_pending (next_attempt_at, attempts)
) ENGINE=InnoDB;
