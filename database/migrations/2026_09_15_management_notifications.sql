CREATE TABLE IF NOT EXISTS notification_settings (
    establishment_id BIGINT UNSIGNED PRIMARY KEY,
    whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    provider VARCHAR(40) NULL,
    confirmation_enabled TINYINT(1) NOT NULL DEFAULT 1,
    cancellation_enabled TINYINT(1) NOT NULL DEFAULT 1,
    reminder_24h_enabled TINYINT(1) NOT NULL DEFAULT 1,
    reminder_2h_enabled TINYINT(1) NOT NULL DEFAULT 0,
    waitlist_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_settings_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS waitlist_matches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    waitlist_entry_id BIGINT UNSIGNED NOT NULL,
    employee_user_id BIGINT UNSIGNED NOT NULL,
    slot_start DATETIME NOT NULL,
    status ENUM('available','queued','notified','expired','converted') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_waitlist_match (waitlist_entry_id, employee_user_id, slot_start),
    KEY idx_waitlist_matches_tenant (establishment_id, status, slot_start),
    CONSTRAINT fk_waitlist_matches_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_matches_entry FOREIGN KEY (waitlist_entry_id) REFERENCES waitlist_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_waitlist_matches_provider FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    appointment_id BIGINT UNSIGNED NULL,
    waitlist_entry_id BIGINT UNSIGNED NULL,
    channel ENUM('whatsapp') NOT NULL DEFAULT 'whatsapp',
    event_type VARCHAR(50) NOT NULL,
    recipient VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    scheduled_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    provider_message_id VARCHAR(190) NULL,
    dedupe_key VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_notification_outbox_dedupe (dedupe_key),
    KEY idx_notification_outbox_queue (status, scheduled_at),
    KEY idx_notification_outbox_tenant (establishment_id, status, scheduled_at),
    CONSTRAINT fk_notification_outbox_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_outbox_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_outbox_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_outbox_waitlist FOREIGN KEY (waitlist_entry_id) REFERENCES waitlist_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB;
