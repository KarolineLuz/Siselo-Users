<?php
declare(strict_types=1);

function user_permissions(PDO $pdo, int $userId): array {
  static $cache = [];
  if (isset($cache[$userId])) return $cache[$userId];

  $stmt = $pdo->prepare("
    SELECT p.name
    FROM permissions p
    JOIN role_permissions rp ON rp.permission_id = p.id
    JOIN user_roles ur ON ur.role_id = rp.role_id
    WHERE ur.user_id = :uid
  ");
  $stmt->execute([':uid' => $userId]);
  $perms = array_column($stmt->fetchAll(), 'name');

  $cache[$userId] = array_fill_keys($perms, true);
  return $cache[$userId];
}

function can(PDO $pdo, string $permission): bool {
  $uid = current_user_id();
  if (!$uid) return false;
  $perms = user_permissions($pdo, $uid);
  return isset($perms[$permission]) || isset($perms['admin.manage']); // admin.manage como “superpoder” opcional
}

function require_permission(PDO $pdo, string $permission): void {
  if (!can($pdo, $permission)) {
    http_response_code(403);
    echo "Sem permissão: ".h($permission);
    exit;
  }
}