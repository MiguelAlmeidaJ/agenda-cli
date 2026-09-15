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
