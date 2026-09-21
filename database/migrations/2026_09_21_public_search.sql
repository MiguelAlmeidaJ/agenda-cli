SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'establishments'
          AND index_name = 'idx_establishments_public_search'
    ),
    'SELECT 1',
    'ALTER TABLE establishments ADD KEY idx_establishments_public_search (active, state, city)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'services'
          AND index_name = 'idx_services_public_search'
    ),
    'SELECT 1',
    'ALTER TABLE services ADD KEY idx_services_public_search (establishment_id, active, name)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
