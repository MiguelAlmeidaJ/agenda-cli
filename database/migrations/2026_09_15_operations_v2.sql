CREATE TABLE IF NOT EXISTS provider_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=segunda ... 7=domingo',
    opens_at TIME NULL,
    closes_at TIME NULL,
    is_off TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_hours (establishment_id, user_id, weekday),
    KEY idx_provider_hours_user (user_id, weekday),
    CONSTRAINT fk_provider_hours_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_hours_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_provider_hours_weekday CHECK (weekday BETWEEN 1 AND 7)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointment_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    establishment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(40) NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_appointment_events_appointment (appointment_id, created_at),
    KEY idx_appointment_events_tenant (establishment_id, created_at),
    CONSTRAINT fk_appointment_events_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_events_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;