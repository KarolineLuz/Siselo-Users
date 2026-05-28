<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';

function require_auth(): void {
  $userId = current_user_id();
  if (!$userId) {
    redirect('/public/login.php');
  }

  global $pdo;
  if (!isset($pdo) || !$pdo instanceof PDO) {
    return;
  }

  $user = User::findById($pdo, (int)$userId);
  if ($user === null || User::accessBlockMessage($user) !== null) {
    $_SESSION = [];
    redirect('/public/login.php');
  }
}
