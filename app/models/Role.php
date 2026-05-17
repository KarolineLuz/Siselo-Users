<?php
declare(strict_types=1);

final class Role {
  public static function all(PDO $pdo): array {
    $stmt = $pdo->query('SELECT * FROM roles ORDER BY name ASC');
    return array_map(
      static function (array $row): array {
        $row['id'] = (int)$row['id'];
        return $row;
      },
      $stmt->fetchAll()
    );
  }

  public static function idsForUser(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id ORDER BY role_id ASC');
    $stmt->execute([':user_id' => $userId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'role_id'));
  }

  public static function namesForUser(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('
      SELECT r.name
      FROM roles r
      JOIN user_roles ur ON ur.role_id = r.id
      WHERE ur.user_id = :user_id
      ORDER BY r.name ASC
    ');
    $stmt->execute([':user_id' => $userId]);
    return array_values(array_column($stmt->fetchAll(), 'name'));
  }

  public static function idByName(PDO $pdo, string $name): ?int {
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int)$id : null;
  }

  public static function syncUserRoles(PDO $pdo, int $userId, array $roleIds): void {
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute([
      ':user_id' => $userId,
    ]);

    if ($roleIds === []) {
      return;
    }

    $insert = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
    foreach ($roleIds as $roleId) {
      $insert->execute([
        ':user_id' => $userId,
        ':role_id' => (int)$roleId,
      ]);
    }
  }
}
