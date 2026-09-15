CREATE TABLE IF NOT EXISTS booking_settings (
    establishment_id BIGINT UNSIGNED PRIMARY KEY,
    min_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_advance_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    cancellation_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    allow_waitlist TINYINT(1) NOT NULL DEFAULT 1,
    cancellation_policy VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_settings_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS waitlist_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    preferred_employee_user_id BIGINT UNSIGNED NULL,
    desired_date DATE NOT NULL,
    time_period ENUM('any','morning','afternoon','evening') NOT NULL DEFAULT 'any',
    status ENUM('waiting','notified','converted','cancelled') NOT NULL DEFAULT 'waiting',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_waitlist_tenant_date (establishment_id, desired_date, status),
    KEY idx_waitlist_service (service_id, desired_date, status),
    KEY idx_waitlist_customer (customer_id, status),
    CONSTRAINT fk_waitlist_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_provider FOREIGN KEY (preferred_employee_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
