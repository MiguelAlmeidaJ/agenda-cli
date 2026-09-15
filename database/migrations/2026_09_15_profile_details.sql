SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'birth_date'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN birth_date DATE NULL AFTER phone'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'city'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN city VARCHAR(100) NULL AFTER birth_date'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'state'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN state CHAR(2) NULL AFTER city'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'bio'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN bio VARCHAR(500) NULL AFTER state'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
