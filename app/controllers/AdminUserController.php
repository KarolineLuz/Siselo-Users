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
    $currentUser = api_current_user($pdo);
    $currentUserId = (int)$currentUser['id'];
    $id = (int)(api_query_param('id', '0') ?? '0');
    $editing = $id > 0;
    $canManageUsers = can($pdo, 'admin.manage');
    if (!$canManageUsers && !$editing) {
      $id = $currentUserId;
      $editing = true;
    }

    $isSelfProfile = $editing && $id === $currentUserId;

    if (!$canManageUsers && !$isSelfProfile) {
      api_error('Sem permissao: admin.manage', 403);
    }

    if (api_request_method() === 'GET') {
      $context = User::formContext($pdo, $editing ? $id : null);
      if ($context['error'] !== null) {
        api_error((string)$context['error'], 404);
      }

      $context['can_manage_users'] = $canManageUsers;
      $context['self_profile'] = !$canManageUsers && $isSelfProfile;

      api_success($context);
    }

    api_verify_csrf();

    try {
      $context = $canManageUsers
        ? User::save($pdo, $editing ? $id : null, api_request_input(), $currentUserId)
        : User::saveSelfProfile($pdo, $currentUserId, api_request_input());

      $context['can_manage_users'] = $canManageUsers;
      $context['self_profile'] = !$canManageUsers;

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

  public static function approve(PDO $pdo): never {
    api_require_permission($pdo, 'admin.manage');
    api_verify_csrf();

    $input = api_request_input();
    $id = (int)($input['id'] ?? 0);
    $user = User::approve($pdo, $id, api_require_user_id());

    if ($user === null) {
      api_error('Usuario nao encontrado.', 404);
    }

    api_success(['user' => $user]);
  }

  public static function softDelete(PDO $pdo): never {
    api_require_permission($pdo, 'admin.manage');
    api_verify_csrf();

    $input = api_request_input();
    $id = (int)($input['id'] ?? 0);

    try {
      $result = User::softDelete($pdo, $id, api_require_user_id());
    } catch (Throwable $error) {
      api_error($error->getMessage(), 422);
    }

    if ($result === null) {
      api_error('Usuario nao encontrado.', 404);
    }

    api_success($result);
  }
}
