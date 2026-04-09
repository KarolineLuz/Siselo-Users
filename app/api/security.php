<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';

function api_require_user_id(): int {
  $userId = current_user_id();
  if ($userId === null) {
    api_error('Nao autenticado.', 401);
  }

  return (int)$userId;
}

function api_current_user(PDO $pdo): array {
  $user = User::findById($pdo, api_require_user_id());
  if ($user === null) {
    api_error('Sessao invalida.', 401);
  }

  return $user;
}

function api_require_permission(PDO $pdo, string $permission): int {
  $userId = api_require_user_id();
  if (!can($pdo, $permission)) {
    api_error('Sem permissao: ' . $permission, 403);
  }

  return $userId;
}

function api_verify_csrf(): void {
  $token = api_request_header('X-CSRF-Token');
  if ($token === null) {
    $input = api_request_input();
    $token = isset($input['csrf']) ? (string)$input['csrf'] : '';
  }

  if ($token === '' || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
    api_error('CSRF invalido.', 403);
  }
}
