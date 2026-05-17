<?php
declare(strict_types=1);

require_once __DIR__ . '/Role.php';
require_once __DIR__ . '/../services/Audit.php';

final class User {
  public static function findByEmail(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    return $row ?: null;
  }

  public static function findById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
  }

  public static function apiPayload(PDO $pdo, array $user): array {
    $userId = (int)$user['id'];

    return [
      'id' => $userId,
      'name' => (string)$user['name'],
      'email' => (string)$user['email'],
      'is_active' => (int)$user['is_active'],
      'must_change_password' => (int)$user['must_change_password'],
      'user_type' => isset($user['user_type']) ? (string)$user['user_type'] : null,
      'specialty' => isset($user['specialty']) ? (string)$user['specialty'] : null,
      'roles' => Role::namesForUser($pdo, $userId),
      'permissions' => array_values(array_keys(user_permissions($pdo, $userId))),
    ];
  }

  public static function authSpecialties(): array {
    return [
      'Endocrinologia',
      'Cardiologia',
      'Psicologia',
      'Enfermagem',
      'Nutrição',
      'Fisioterapia',
      'Farmácia Clínica',
      'Serviço Social',
      'Oftalmologia',
      'Nefrologia',
      'Técnico de Enfermagem',
      'Gestão do Cuidado',
    ];
  }

  public static function registerPublic(PDO $pdo, array $payload): array {
    $name = trim((string)($payload['name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    $userType = strtoupper(trim((string)($payload['user_type'] ?? '')));
    $specialty = trim((string)($payload['specialty'] ?? ''));

    if ($name === '' || $email === '' || $password === '' || $userType === '') {
      throw new RuntimeException('Nome, email, senha e tipo de usuario sao obrigatorios.');
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      throw new RuntimeException('Email invalido.');
    }

    if (strlen($name) > 120) {
      throw new RuntimeException('Nome deve ter no maximo 120 caracteres.');
    }

    if (strlen($password) < 8) {
      throw new RuntimeException('A senha deve ter pelo menos 8 caracteres.');
    }

    if (!in_array($userType, ['CADH', 'UBS'], true)) {
      throw new RuntimeException('Tipo de usuario invalido.');
    }

    if ($userType === 'CADH' && !in_array($specialty, self::authSpecialties(), true)) {
      throw new RuntimeException('Especialidade obrigatoria para usuarios CADH.');
    }

    if ($userType === 'UBS') {
      $specialty = '';
    }

    $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $emailStmt->execute([':email' => $email]);
    if ($emailStmt->fetchColumn() !== false) {
      throw new RuntimeException('Ja existe uma conta com este email.');
    }

    $roleId = Role::idByName($pdo, 'alimentador');
    if ($roleId === null) {
      throw new RuntimeException('Perfil alimentador nao encontrado.');
    }

    $pdo->beginTransaction();

    try {
      $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, is_active, must_change_password, user_type, specialty)
        VALUES (:name, :email, :password_hash, 1, 0, :user_type, :specialty)
      ');
      $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':user_type' => $userType,
        ':specialty' => $specialty !== '' ? $specialty : null,
      ]);

      $userId = (int)$pdo->lastInsertId();
      Role::syncUserRoles($pdo, $userId, [$roleId]);

      Audit::log($pdo, $userId, 'create', 'users', $userId, null, [
        'name' => $name,
        'email' => $email,
        'is_active' => 1,
        'must_change_password' => 0,
        'user_type' => $userType,
        'specialty' => $specialty !== '' ? $specialty : null,
        'role' => 'alimentador',
        'self_registered' => true,
      ]);

      $pdo->commit();

      $user = self::findById($pdo, $userId);
      if ($user === null) {
        throw new RuntimeException('Usuario criado, mas nao foi possivel carregar a conta.');
      }

      return $user;
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      throw $error;
    }
  }

  public static function listForAdmin(PDO $pdo, string $query = ''): array {
    $sql = 'SELECT u.* FROM users u WHERE u.deleted_at IS NULL';
    $params = [];

    if ($query !== '') {
      $sql .= ' AND (u.name LIKE :q_name OR u.email LIKE :q_email)';
      $params[':q_name'] = '%' . $query . '%';
      $params[':q_email'] = '%' . $query . '%';
    }

    $sql .= ' ORDER BY u.id DESC LIMIT 300';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return array_map(
      static function (array $row) use ($pdo): array {
        $row['id'] = (int)$row['id'];
        $row['is_active'] = (int)$row['is_active'];
        $row['must_change_password'] = (int)$row['must_change_password'];
        $row['user_type'] = isset($row['user_type']) ? (string)$row['user_type'] : '';
        $row['specialty'] = isset($row['specialty']) ? (string)$row['specialty'] : '';
        $row['roles'] = Role::namesForUser($pdo, (int)$row['id']);
        $row['role_ids'] = Role::idsForUser($pdo, (int)$row['id']);
        return $row;
      },
      $rows
    );
  }

  public static function formContext(PDO $pdo, ?int $id = null): array {
    $user = [
      'name' => '',
      'email' => '',
      'is_active' => 1,
      'user_type' => '',
      'specialty' => '',
      'role_ids' => [],
    ];
    $editing = $id !== null;

    if ($editing) {
      $row = self::findById($pdo, $id);
      if ($row === null) {
        return [
          'editing' => true,
          'user' => null,
          'roles' => Role::all($pdo),
          'error' => 'Usuario nao encontrado.',
        ];
      }

      $user = [
        'id' => (int)$row['id'],
        'name' => (string)$row['name'],
        'email' => (string)$row['email'],
        'is_active' => (int)$row['is_active'],
        'user_type' => isset($row['user_type']) ? (string)$row['user_type'] : '',
        'specialty' => isset($row['specialty']) ? (string)$row['specialty'] : '',
        'role_ids' => Role::idsForUser($pdo, (int)$row['id']),
      ];
    }

    return [
      'editing' => $editing,
      'user' => $user,
      'roles' => Role::all($pdo),
      'error' => null,
    ];
  }

  public static function save(PDO $pdo, ?int $id, array $payload, int $actorUserId): array {
    $editing = $id !== null;
    $name = trim((string)($payload['name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $isActive = !empty($payload['is_active']) ? 1 : 0;
    $userType = strtoupper(trim((string)($payload['user_type'] ?? '')));
    $specialty = trim((string)($payload['specialty'] ?? ''));
    $roleIds = array_values(array_map('intval', (array)($payload['role_ids'] ?? [])));

    if ($name === '' || $email === '') {
      throw new RuntimeException('Nome e email sao obrigatorios.');
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      throw new RuntimeException('Email invalido.');
    }

    if ($userType !== '' && !in_array($userType, ['CADH', 'UBS'], true)) {
      throw new RuntimeException('Tipo de usuario invalido.');
    }

    if ($userType === 'CADH' && !in_array($specialty, self::authSpecialties(), true)) {
      throw new RuntimeException('Especialidade obrigatoria para usuarios CADH.');
    }

    if ($userType !== 'CADH') {
      $specialty = '';
    }

    $pdo->beginTransaction();

    try {
      if ($editing) {
        $before = self::findById($pdo, $id);
        if ($before === null) {
          throw new RuntimeException('Usuario nao encontrado.');
        }

        $stmt = $pdo->prepare('
          UPDATE users
          SET
            name = :name,
            email = :email,
            is_active = :is_active,
            user_type = :user_type,
            specialty = :specialty,
            updated_at = NOW()
          WHERE id = :id
        ');
        $stmt->execute([
          ':name' => $name,
          ':email' => $email,
          ':is_active' => $isActive,
          ':user_type' => $userType !== '' ? $userType : null,
          ':specialty' => $specialty !== '' ? $specialty : null,
          ':id' => $id,
        ]);

        Role::syncUserRoles($pdo, $id, $roleIds);

        Audit::log($pdo, $actorUserId, 'update', 'users', $id, $before, [
          'name' => $name,
          'email' => $email,
          'is_active' => $isActive,
          'user_type' => $userType !== '' ? $userType : null,
          'specialty' => $specialty !== '' ? $specialty : null,
          'role_ids' => $roleIds,
        ]);

        $userId = $id;
      } else {
        $tempPassword = (string)($payload['temp_password'] ?? '');
        if ($tempPassword === '') {
          throw new RuntimeException('Senha temporaria obrigatoria.');
        }

        $stmt = $pdo->prepare('
          INSERT INTO users (name, email, password_hash, is_active, must_change_password, user_type, specialty)
          VALUES (:name, :email, :password_hash, :is_active, 0, :user_type, :specialty)
        ');
        $stmt->execute([
          ':name' => $name,
          ':email' => $email,
          ':password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
          ':is_active' => $isActive,
          ':user_type' => $userType !== '' ? $userType : null,
          ':specialty' => $specialty !== '' ? $specialty : null,
        ]);

        $userId = (int)$pdo->lastInsertId();
        Role::syncUserRoles($pdo, $userId, $roleIds);

        Audit::log($pdo, $actorUserId, 'create', 'users', $userId, null, [
          'name' => $name,
          'email' => $email,
          'is_active' => $isActive,
          'user_type' => $userType !== '' ? $userType : null,
          'specialty' => $specialty !== '' ? $specialty : null,
          'role_ids' => $roleIds,
        ]);
      }

      $pdo->commit();
      return self::formContext($pdo, $userId);
    } catch (Throwable $error) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      throw $error;
    }
  }

  public static function saveSelfProfile(PDO $pdo, int $id, array $payload): array {
    $before = self::findById($pdo, $id);
    if ($before === null) {
      throw new RuntimeException('Usuario nao encontrado.');
    }

    $name = trim((string)($payload['name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $userType = strtoupper(trim((string)($payload['user_type'] ?? '')));
    $specialty = trim((string)($payload['specialty'] ?? ''));

    if ($name === '' || $email === '') {
      throw new RuntimeException('Nome e email sao obrigatorios.');
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      throw new RuntimeException('Email invalido.');
    }

    if ($userType !== '' && !in_array($userType, ['CADH', 'UBS'], true)) {
      throw new RuntimeException('Tipo de usuario invalido.');
    }

    if ($userType === 'CADH' && !in_array($specialty, self::authSpecialties(), true)) {
      throw new RuntimeException('Especialidade obrigatoria para usuarios CADH.');
    }

    if ($userType !== 'CADH') {
      $specialty = '';
    }

    $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $emailStmt->execute([
      ':email' => $email,
      ':id' => $id,
    ]);
    if ($emailStmt->fetchColumn() !== false) {
      throw new RuntimeException('Ja existe uma conta com este email.');
    }

    $stmt = $pdo->prepare('
      UPDATE users
      SET
        name = :name,
        email = :email,
        user_type = :user_type,
        specialty = :specialty,
        updated_at = NOW()
      WHERE id = :id
    ');
    $stmt->execute([
      ':name' => $name,
      ':email' => $email,
      ':user_type' => $userType !== '' ? $userType : null,
      ':specialty' => $specialty !== '' ? $specialty : null,
      ':id' => $id,
    ]);

    Audit::log($pdo, $id, 'update', 'users', $id, $before, [
      'name' => $name,
      'email' => $email,
      'user_type' => $userType !== '' ? $userType : null,
      'specialty' => $specialty !== '' ? $specialty : null,
      'self_profile' => true,
    ]);

    return self::formContext($pdo, $id);
  }

  public static function toggleActive(PDO $pdo, int $id, int $actorUserId): ?array {
    $before = self::findById($pdo, $id);
    if ($before === null) {
      return null;
    }

    $newValue = ((int)$before['is_active'] === 1) ? 0 : 1;
    $stmt = $pdo->prepare('UPDATE users SET is_active = :is_active, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
      ':is_active' => $newValue,
      ':id' => $id,
    ]);

    Audit::log($pdo, $actorUserId, 'update', 'users', $id, $before, ['is_active' => $newValue]);

    $after = self::findById($pdo, $id);
    return $after !== null ? self::apiPayload($pdo, $after) : null;
  }

  public static function resetPassword(PDO $pdo, int $id, int $actorUserId): ?array {
    $before = self::findById($pdo, $id);
    if ($before === null) {
      return null;
    }

    $stmt = $pdo->prepare('
      UPDATE users
      SET password_hash = :password_hash, must_change_password = 0, updated_at = NOW()
      WHERE id = :id
    ');
    $stmt->execute([
      ':password_hash' => password_hash('Temporaria@123', PASSWORD_DEFAULT),
      ':id' => $id,
    ]);

    Audit::log($pdo, $actorUserId, 'update', 'users', $id, $before, ['password_reset' => true]);

    $after = self::findById($pdo, $id);
    return $after !== null ? self::apiPayload($pdo, $after) : null;
  }
}
