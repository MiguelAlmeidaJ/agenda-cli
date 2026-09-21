SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'appointments'
          AND column_name = 'attendance_response'
    ),
    'SELECT 1',
    'ALTER TABLE appointments ADD COLUMN attendance_response ENUM(''pending'',''confirmed'') NOT NULL DEFAULT ''pending'' AFTER status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'appointments'
          AND column_name = 'attendance_responded_at'
    ),
    'SELECT 1',
    'ALTER TABLE appointments ADD COLUMN attendance_responded_at DATETIME NULL AFTER attendance_response'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'appointments'
          AND index_name = 'idx_appointments_attendance'
    ),
    'SELECT 1',
    'ALTER TABLE appointments ADD KEY idx_appointments_attendance (establishment_id, attendance_response, starts_at, status)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS appointment_attendance_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_appointment_attendance_token (token_hash),
    KEY idx_appointment_attendance_lookup (appointment_id, expires_at, used_at),
    KEY idx_appointment_attendance_tenant (establishment_id, expires_at),
    CONSTRAINT fk_appointment_attendance_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_attendance_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;
