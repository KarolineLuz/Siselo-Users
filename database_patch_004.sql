USE intranet_cadh;

SET @has_is_approved := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'is_approved'
);

SET @sql_add_is_approved := IF(
  @has_is_approved = 0,
  'ALTER TABLE users ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active',
  'SELECT 1'
);

PREPARE stmt_add_is_approved FROM @sql_add_is_approved;
EXECUTE stmt_add_is_approved;
DEALLOCATE PREPARE stmt_add_is_approved;

SET @has_approved_at := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'approved_at'
);

SET @sql_add_approved_at := IF(
  @has_approved_at = 0,
  'ALTER TABLE users ADD COLUMN approved_at DATETIME NULL AFTER is_approved',
  'SELECT 1'
);

PREPARE stmt_add_approved_at FROM @sql_add_approved_at;
EXECUTE stmt_add_approved_at;
DEALLOCATE PREPARE stmt_add_approved_at;

SET @has_approved_by_user_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'approved_by_user_id'
);

SET @sql_add_approved_by_user_id := IF(
  @has_approved_by_user_id = 0,
  'ALTER TABLE users ADD COLUMN approved_by_user_id INT NULL AFTER approved_at',
  'SELECT 1'
);

PREPARE stmt_add_approved_by_user_id FROM @sql_add_approved_by_user_id;
EXECUTE stmt_add_approved_by_user_id;
DEALLOCATE PREPARE stmt_add_approved_by_user_id;

UPDATE users
SET approved_at = COALESCE(approved_at, created_at, NOW())
WHERE is_approved = 1
  AND approved_at IS NULL;

UPDATE users
SET
  is_active = 1,
  is_approved = 1,
  approved_at = COALESCE(approved_at, NOW()),
  must_change_password = 0
WHERE email = 'admin@local';
