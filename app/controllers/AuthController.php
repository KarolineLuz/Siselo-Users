<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/Audit.php';

final class AuthController {
  public static function login(PDO $pdo): never {
    $input = api_request_input();
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($email === '' || $password === '') {
      api_error('Email e senha sao obrigatorios.', 422);
    }

    $user = User::findByEmail($pdo, $email);
    if ($user === null || (int)$user['is_active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
      api_error('Login invalido.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    Audit::log($pdo, (int)$user['id'], 'login', 'users', (int)$user['id'], null, ['email' => $email]);

    api_success([
      'user' => User::apiPayload($pdo, $user),
      'csrf' => csrf_token(),
    ]);
  }

  public static function register(PDO $pdo): never {
    $input = api_request_input();

    try {
      $user = User::registerPublic($pdo, $input);
    } catch (Throwable $error) {
      api_error($error->getMessage(), 422);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    Audit::log($pdo, (int)$user['id'], 'login', 'users', (int)$user['id'], null, [
      'email' => (string)$user['email'],
      'after_register' => true,
    ]);

    api_success([
      'user' => User::apiPayload($pdo, $user),
      'csrf' => csrf_token(),
    ], 201);
  }

  public static function me(PDO $pdo): never {
    $user = api_current_user($pdo);

    api_success([
      'user' => User::apiPayload($pdo, $user),
      'csrf' => csrf_token(),
    ]);
  }

  public static function logout(PDO $pdo): never {
    $userId = current_user_id();

    if ($userId !== null) {
      api_verify_csrf();
      Audit::log($pdo, (int)$userId, 'logout', 'users', (int)$userId, null, null);
    }

    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
      if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
      }

      session_destroy();
    }

    api_success(['logged_out' => true]);
  }

  public static function changePassword(PDO $pdo): never {
    $user = api_current_user($pdo);
    api_verify_csrf();

    $input = api_request_input();
    $password = (string)($input['password'] ?? '');
    $passwordConfirm = (string)($input['password_confirm'] ?? '');

    if (strlen($password) < 8) {
      api_error('A senha deve ter pelo menos 8 caracteres.', 422);
    }

    if ($password !== $passwordConfirm) {
      api_error('As senhas nao conferem.', 422);
    }

    $stmt = $pdo->prepare('
      UPDATE users
      SET password_hash = :password_hash, must_change_password = 0, updated_at = NOW()
      WHERE id = :id
    ');
    $stmt->execute([
      ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
      ':id' => (int)$user['id'],
    ]);

    Audit::log($pdo, (int)$user['id'], 'update', 'users', (int)$user['id'], ['must_change_password' => 1], ['must_change_password' => 0]);

    $freshUser = User::findById($pdo, (int)$user['id']);
    api_success([
      'user' => User::apiPayload($pdo, $freshUser ?? $user),
      'csrf' => csrf_token(),
    ]);
  }
}
