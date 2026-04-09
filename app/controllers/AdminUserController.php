<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';

final class AdminUserController {
  public static function list(PDO $pdo): never {
    api_require_permission($pdo, 'admin.manage');

    $query = trim((string)(api_query_param('q', '') ?? ''));
    api_success([
      'q' => $query,
      'rows' => User::listForAdmin($pdo, $query),
    ]);
  }

  public static function form(PDO $pdo): never {
    api_require_permission($pdo, 'admin.manage');

    $id = (int)(api_query_param('id', '0') ?? '0');
    $editing = $id > 0;

    if (api_request_method() === 'GET') {
      $context = User::formContext($pdo, $editing ? $id : null);
      if ($context['error'] !== null) {
        api_error((string)$context['error'], 404);
      }

      api_success($context);
    }

    api_verify_csrf();

    try {
      $context = User::save($pdo, $editing ? $id : null, api_request_input(), api_require_user_id());
      api_success($context);
    } catch (Throwable $error) {
      api_error($error->getMessage(), 422);
    }
  }

  public static function toggleActive(PDO $pdo): never {
    api_require_permission($pdo, 'admin.manage');
    api_verify_csrf();

    $input = api_request_input();
    $id = (int)($input['id'] ?? 0);
    $user = User::toggleActive($pdo, $id, api_require_user_id());

    if ($user === null) {
      api_error('Usuario nao encontrado.', 404);
    }

    api_success(['user' => $user]);
  }

  public static function resetPassword(PDO $pdo): never {
    api_require_permission($pdo, 'admin.manage');
    api_verify_csrf();

    $input = api_request_input();
    $id = (int)($input['id'] ?? 0);
    $user = User::resetPassword($pdo, $id, api_require_user_id());

    if ($user === null) {
      api_error('Usuario nao encontrado.', 404);
    }

    api_success([
      'user' => $user,
      'temporary_password' => 'Temporaria@123',
    ]);
  }
}
