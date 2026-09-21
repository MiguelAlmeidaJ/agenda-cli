CREATE TABLE IF NOT EXISTS appointment_series (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    employee_user_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    frequency ENUM('weekly') NOT NULL DEFAULT 'weekly',
    interval_weeks TINYINT UNSIGNED NOT NULL DEFAULT 1,
    occurrences_count SMALLINT UNSIGNED NOT NULL,
    starts_on DATE NOT NULL,
    starts_at TIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_appointment_series_tenant (establishment_id, starts_on),
    KEY idx_appointment_series_customer (customer_id, starts_on),
    CONSTRAINT fk_appointment_series_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_series_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_series_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_appointment_series_employee FOREIGN KEY (employee_user_id) REFERENCES users(id),
    CONSTRAINT fk_appointment_series_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'appointments'
          AND column_name = 'series_id'
    ),
    'SELECT 1',
    'ALTER TABLE appointments ADD COLUMN series_id BIGINT UNSIGNED NULL AFTER customer_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'appointments'
          AND column_name = 'series_position'
    ),
    'SELECT 1',
    'ALTER TABLE appointments ADD COLUMN series_position SMALLINT UNSIGNED NULL AFTER series_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'appointments'
          AND index_name = 'idx_appointments_series'
    ),
    'SELECT 1',
    'ALTER TABLE appointments ADD KEY idx_appointments_series (series_id, series_position)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'appointments'
          AND constraint_name = 'fk_appointments_series'
    ),
    'SELECT 1',
    'ALTER TABLE appointments ADD CONSTRAINT fk_appointments_series FOREIGN KEY (series_id) REFERENCES appointment_series(id) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
