USE intranet_cadh;

ALTER TABLE users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;

-- (Opcional) garantir que o admin.manage continue como super-permissão já coberta.