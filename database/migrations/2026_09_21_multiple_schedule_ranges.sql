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

INSERT INTO business_hour_ranges (establishment_id, weekday, opens_at, closes_at, sort_order)
SELECT bh.establishment_id, bh.weekday, bh.opens_at, bh.closes_at, 0
FROM business_hours bh
WHERE bh.is_closed = 0
  AND bh.opens_at IS NOT NULL
  AND bh.closes_at IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM business_hour_ranges bhr
      WHERE bhr.establishment_id = bh.establishment_id AND bhr.weekday = bh.weekday
  );

INSERT INTO provider_hour_ranges (establishment_id, user_id, weekday, opens_at, closes_at, sort_order)
SELECT ph.establishment_id, ph.user_id, ph.weekday, ph.opens_at, ph.closes_at, 0
FROM provider_hours ph
WHERE ph.is_off = 0
  AND ph.opens_at IS NOT NULL
  AND ph.closes_at IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM provider_hour_ranges phr
      WHERE phr.establishment_id = ph.establishment_id
        AND phr.user_id = ph.user_id
        AND phr.weekday = ph.weekday
  );
