CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','owner','employee','client') NOT NULL,
    phone VARCHAR(30) NULL,
    birth_date DATE NULL,
    city VARCHAR(100) NULL,
    state CHAR(2) NULL,
    bio VARCHAR(500) NULL,
    avatar_url VARCHAR(700) NULL,
    avatar_public_id VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS establishments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT NULL,
    logo_url VARCHAR(700) NULL,
    logo_public_id VARCHAR(255) NULL,
    cover_url VARCHAR(700) NULL,
    cover_public_id VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    postal_code VARCHAR(10) NULL,
    street VARCHAR(150) NULL,
    address_number VARCHAR(30) NULL,
    complement VARCHAR(100) NULL,
    neighborhood VARCHAR(100) NULL,
    address_line VARCHAR(190) NULL,
    city VARCHAR(100) NULL,
    state CHAR(2) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    geocoded_at DATETIME NULL,
    geocoding_provider VARCHAR(40) NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_establishments_public_search (active, state, city),
    CONSTRAINT fk_establishment_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS establishment_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner','employee') NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_establishment_user (establishment_id, user_id),
    KEY idx_establishment_users_user (user_id, active),
    CONSTRAINT fk_establishment_users_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_establishment_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(30) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_user (establishment_id, user_id),
    UNIQUE KEY uq_customers_email (establishment_id, email),
    KEY idx_customers_name (establishment_id, name),
    KEY idx_customers_phone (establishment_id, phone),
    CONSTRAINT fk_customers_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    image_url VARCHAR(700) NULL,
    image_public_id VARCHAR(255) NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_services_tenant_active (establishment_id, active),
    KEY idx_services_public_search (establishment_id, active, name),
    CONSTRAINT fk_services_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_services (
    employee_user_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (employee_user_id, service_id),
    CONSTRAINT fk_employee_services_employee FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_employee_services_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=segunda ... 7=domingo',
    opens_at TIME NULL,
    closes_at TIME NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_business_hours (establishment_id, weekday),
    CONSTRAINT fk_business_hours_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT chk_business_hours_weekday CHECK (weekday BETWEEN 1 AND 7)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS business_hour_ranges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=segunda ... 7=domingo',
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_business_hour_ranges_day (establishment_id, weekday, sort_order),
    CONSTRAINT fk_business_hour_ranges_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT chk_business_hour_ranges_weekday CHECK (weekday BETWEEN 1 AND 7)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS special_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    special_date DATE NOT NULL,
    name VARCHAR(150) NOT NULL,
    source ENUM('national','custom','emergency') NOT NULL DEFAULT 'custom',
    is_closed TINYINT(1) NOT NULL DEFAULT 1,
    opens_at TIME NULL,
    closes_at TIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_special_hours_date (establishment_id, special_date),
    KEY idx_special_hours_lookup (establishment_id, special_date, is_closed),
    CONSTRAINT fk_special_hours_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS blocked_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    employee_user_id BIGINT UNSIGNED NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_blocked_periods_lookup (establishment_id, employee_user_id, starts_at, ends_at),
    CONSTRAINT fk_blocked_periods_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_blocked_periods_employee FOREIGN KEY (employee_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS provider_absences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    kind ENUM('date_range','weekly') NOT NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    weekday TINYINT UNSIGNED NULL COMMENT '1=segunda ... 7=domingo',
    all_day TINYINT(1) NOT NULL DEFAULT 1,
    starts_at TIME NULL,
    ends_at TIME NULL,
    reason VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_provider_absences_lookup (establishment_id, user_id, starts_on, ends_on, kind),
    KEY idx_provider_absences_weekly (establishment_id, user_id, weekday, starts_on),
    CONSTRAINT fk_provider_absences_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_absences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_provider_absences_weekday CHECK (weekday IS NULL OR weekday BETWEEN 1 AND 7)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    employee_user_id BIGINT UNSIGNED NOT NULL,
    client_user_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    status ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'confirmed',
    attendance_response ENUM('pending','confirmed') NOT NULL DEFAULT 'pending',
    attendance_responded_at DATETIME NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_appointments_employee_time (employee_user_id, starts_at, ends_at, status),
    KEY idx_appointments_tenant_time (establishment_id, starts_at, status),
    KEY idx_appointments_client (client_user_id, starts_at),
    KEY idx_appointments_customer (customer_id, starts_at),
    KEY idx_appointments_attendance (establishment_id, attendance_response, starts_at, status),
    CONSTRAINT fk_appointments_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id),
    CONSTRAINT fk_appointments_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_appointments_employee FOREIGN KEY (employee_user_id) REFERENCES users(id),
    CONSTRAINT fk_appointments_client FOREIGN KEY (client_user_id) REFERENCES users(id),
    CONSTRAINT fk_appointments_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_appointments_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS provider_hour_ranges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    establishment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=segunda ... 7=domingo',
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_provider_hour_ranges_day (establishment_id, user_id, weekday, sort_order),
    CONSTRAINT fk_provider_hour_ranges_establishment FOREIGN KEY (establishment_id) REFERENCES establishments(id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_hour_ranges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_provider_hour_ranges_weekday CHECK (weekday BETWEEN 1 AND 7)
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

CREATE TABLE IF NOT EXISTS booking_settings (
    establishment_id BIGINT UNSIGNED PRIMARY KEY,
    min_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_advance_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    cancellation_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    reschedule_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    waitlist_offer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
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
    offer_token_hash CHAR(64) NULL,
    offer_expires_at DATETIME NULL,
    accepted_at DATETIME NULL,
    appointment_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_waitlist_match (waitlist_entry_id, employee_user_id, slot_start),
    KEY idx_waitlist_matches_tenant (establishment_id, status, slot_start),
    KEY idx_waitlist_matches_offer_token (offer_token_hash),
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


CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts_email_time (email_hash, attempted_at),
    KEY idx_login_attempts_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB;
