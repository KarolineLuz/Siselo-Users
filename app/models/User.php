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
      'roles' => Role::namesForUser($pdo, $userId),
      'permissions' => array_values(array_keys(user_permissions($pdo, $userId))),
    ];
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
    $roleIds = array_values(array_map('intval', (array)($payload['role_ids'] ?? [])));

    if ($name === '' || $email === '') {
      throw new RuntimeException('Nome e email sao obrigatorios.');
    }

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
      throw new RuntimeException('Email invalido.');
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
          SET name = :name, email = :email, is_active = :is_active, updated_at = NOW()
          WHERE id = :id
        ');
        $stmt->execute([
          ':name' => $name,
          ':email' => $email,
          ':is_active' => $isActive,
          ':id' => $id,
        ]);

        Role::syncUserRoles($pdo, $id, $roleIds);

        Audit::log($pdo, $actorUserId, 'update', 'users', $id, $before, [
          'name' => $name,
          'email' => $email,
          'is_active' => $isActive,
          'role_ids' => $roleIds,
        ]);

        $userId = $id;
      } else {
        $tempPassword = (string)($payload['temp_password'] ?? '');
        if ($tempPassword === '') {
          throw new RuntimeException('Senha temporaria obrigatoria.');
        }

        $stmt = $pdo->prepare('
          INSERT INTO users (name, email, password_hash, is_active, must_change_password)
          VALUES (:name, :email, :password_hash, :is_active, 1)
        ');
        $stmt->execute([
          ':name' => $name,
          ':email' => $email,
          ':password_hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
          ':is_active' => $isActive,
        ]);

        $userId = (int)$pdo->lastInsertId();
        Role::syncUserRoles($pdo, $userId, $roleIds);

        Audit::log($pdo, $actorUserId, 'create', 'users', $userId, null, [
          'name' => $name,
          'email' => $email,
          'is_active' => $isActive,
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
      SET password_hash = :password_hash, must_change_password = 1, updated_at = NOW()
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
