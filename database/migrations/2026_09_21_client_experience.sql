SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'booking_settings' AND column_name = 'reschedule_notice_minutes'),
    'SELECT 1',
    'ALTER TABLE booking_settings ADD COLUMN reschedule_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER cancellation_notice_minutes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'booking_settings' AND column_name = 'waitlist_offer_minutes'),
    'SELECT 1',
    'ALTER TABLE booking_settings ADD COLUMN waitlist_offer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER reschedule_notice_minutes'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'waitlist_matches' AND column_name = 'offer_token_hash'),
    'SELECT 1',
    'ALTER TABLE waitlist_matches ADD COLUMN offer_token_hash CHAR(64) NULL AFTER status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'waitlist_matches' AND column_name = 'offer_expires_at'),
    'SELECT 1',
    'ALTER TABLE waitlist_matches ADD COLUMN offer_expires_at DATETIME NULL AFTER offer_token_hash'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'waitlist_matches' AND column_name = 'accepted_at'),
    'SELECT 1',
    'ALTER TABLE waitlist_matches ADD COLUMN accepted_at DATETIME NULL AFTER offer_expires_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'waitlist_matches' AND column_name = 'appointment_id'),
    'SELECT 1',
    'ALTER TABLE waitlist_matches ADD COLUMN appointment_id BIGINT UNSIGNED NULL AFTER accepted_at'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'waitlist_matches' AND index_name = 'idx_waitlist_matches_offer_token'),
    'SELECT 1',
    'ALTER TABLE waitlist_matches ADD KEY idx_waitlist_matches_offer_token (offer_token_hash)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
