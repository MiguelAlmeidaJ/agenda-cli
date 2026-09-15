CREATE TABLE customers (
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

ALTER TABLE appointments
    MODIFY client_user_id BIGINT UNSIGNED NULL,
    ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER client_user_id,
    ADD KEY idx_appointments_customer (customer_id, starts_at),
    ADD CONSTRAINT fk_appointments_customer FOREIGN KEY (customer_id) REFERENCES customers(id);

INSERT INTO customers (establishment_id, user_id, name, email, phone)
SELECT DISTINCT a.establishment_id, u.id, u.name, LOWER(u.email), u.phone
FROM appointments a
JOIN users u ON u.id = a.client_user_id
WHERE a.client_user_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    phone = COALESCE(VALUES(phone), phone),
    updated_at = CURRENT_TIMESTAMP;

UPDATE appointments a
JOIN customers c
  ON c.establishment_id = a.establishment_id
 AND c.user_id = a.client_user_id
SET a.customer_id = c.id
WHERE a.customer_id IS NULL
  AND a.client_user_id IS NOT NULL;
