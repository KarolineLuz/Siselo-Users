USE intranet_cadh;

UPDATE users
SET
  email = CONCAT('deleted+user-', id, '-', UNIX_TIMESTAMP(COALESCE(deleted_at, NOW())), '@local.invalid')
WHERE deleted_at IS NOT NULL
  AND email NOT LIKE 'deleted+user-%@local.invalid';
