CREATE DATABASE IF NOT EXISTS intranet_cadh
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE intranet_cadh;

-- =========================
-- USUÁRIOS / AUTH
-- =========================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL
);

-- =========================
-- RBAC
-- =========================
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
);

CREATE TABLE permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE, -- ex: patients.view
  description VARCHAR(255) NULL
);

CREATE TABLE user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY(user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY(role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id)
);

-- =========================
-- AUDITORIA
-- =========================
CREATE TABLE audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,           -- create/update/delete/restore/login/logout
  entity VARCHAR(60) NOT NULL,           -- users/patients/care_plans etc.
  entity_id INT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  before_json JSON NULL,
  after_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =========================
-- PACIENTES (MVP)
-- =========================
CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_cadh_date DATE NULL,
  full_name VARCHAR(180) NOT NULL,
  ses VARCHAR(60) NULL,
  cpf VARCHAR(14) NULL,
  birth_date DATE NULL,
  sex VARCHAR(20) NULL,
  race VARCHAR(40) NULL,
  responsible_name VARCHAR(180) NULL,
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  ubs_ref VARCHAR(120) NULL,
  team_ref VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY uniq_cpf (cpf)
);

-- =========================
-- SEEDS: ROLES + PERMISSIONS
-- =========================
INSERT INTO roles (name, description) VALUES
('admin', 'Acesso total'),
('alimentador', 'Cria e edita, não apaga'),
('visualizador', 'Somente leitura');

INSERT INTO permissions (name, description) VALUES
('admin.manage', 'Gerenciar usuários/roles/permissoes'),
('patients.view', 'Visualizar pacientes'),
('patients.create', 'Criar pacientes'),
('patients.update', 'Editar pacientes'),
('patients.delete', 'Apagar pacientes (soft delete)'),
('patients.restore', 'Restaurar pacientes');

-- Admin: tudo
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.name='admin';

-- Alimentador: view/create/update (sem delete/restore/admin.manage)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name IN ('patients.view','patients.create','patients.update')
WHERE r.name='alimentador';

-- Visualizador: view
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r
JOIN permissions p ON p.name IN ('patients.view')
WHERE r.name='visualizador';

INSERT INTO users (name, email, password_hash) VALUES
('Administrador', 'admin@local', '<COLE_O_HASH_AQUI>');

INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u, roles r
WHERE u.email='admin@local' AND r.name='admin';


USE intranet_cadh;

-- =========================
-- PERMISSIONS (USERS + CARE PLANS)
-- =========================
INSERT IGNORE INTO permissions (name, description) VALUES
('users.view', 'Listar usuários'),
('users.create', 'Criar usuários'),
('users.update', 'Editar usuários'),
('users.activate', 'Ativar/desativar usuários'),
('users.reset_password', 'Resetar senha'),
('users.delete', 'Apagar usuário (soft delete)'),
('users.restore', 'Restaurar usuário'),
('careplans.view', 'Visualizar planos de cuidado'),
('careplans.create', 'Criar planos de cuidado'),
('careplans.update', 'Editar planos de cuidado'),
('careplans.delete', 'Apagar plano de cuidado (soft delete)'),
('careplans.restore', 'Restaurar plano de cuidado');

-- Garanta que admin tenha tudo (inclusive as novas permissões)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.name='admin';

-- =========================
-- CARE PLANS TABLES
-- =========================
CREATE TABLE IF NOT EXISTS care_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  interventions TEXT NULL,
  created_by_user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS care_plan_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  care_plan_id INT NOT NULL,
  item_type VARCHAR(40) NOT NULL, -- 'alerta','meta','dificuldade','recomendacao'
  title VARCHAR(180) NULL,
  situation TEXT NULL,
  recommendation TEXT NULL,
  difficulty TEXT NULL,
  goal TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (care_plan_id) REFERENCES care_plans(id)
);