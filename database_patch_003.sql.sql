USE intranet_cadh;

CREATE TABLE IF NOT EXISTS encounters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  encounter_date DATE NOT NULL,
  specialty VARCHAR(80) NOT NULL,
  professional_user_id INT NULL,
  summary TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (professional_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS transitions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  transition_date DATE NOT NULL,
  from_service VARCHAR(120) NULL,
  to_service VARCHAR(120) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pendente',
  notes TEXT NULL,
  created_by_user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  FOREIGN KEY (patient_id) REFERENCES patients(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

INSERT IGNORE INTO permissions (name, description) VALUES
('encounters.view', 'Visualizar atendimentos'),
('encounters.create', 'Criar atendimentos'),
('encounters.update', 'Editar atendimentos'),
('encounters.delete', 'Apagar atendimentos (soft delete)'),
('encounters.restore', 'Restaurar atendimentos'),
('transitions.view', 'Visualizar transições'),
('transitions.create', 'Criar transições'),
('transitions.update', 'Editar transições'),
('transitions.delete', 'Apagar transições (soft delete)'),
('transitions.restore', 'Restaurar transições');

-- admin: tudo
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.name='admin';

-- alimentador: view/create/update (sem delete/restore)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN (
  'encounters.view','encounters.create','encounters.update',
  'transitions.view','transitions.create','transitions.update',
  'careplans.view','careplans.create','careplans.update'
)
WHERE r.name='alimentador';

-- visualizador: view
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.name IN ('encounters.view','transitions.view','careplans.view')
WHERE r.name='visualizador';