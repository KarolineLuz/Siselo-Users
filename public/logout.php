<?php
declare(strict_types=1);
require __DIR__ . '/../app/core/bootstrap.php';

$uid = current_user_id();
$_SESSION = [];
session_destroy();

if ($uid) {
  require __DIR__ . '/../app/services/Audit.php';
  Audit::log($pdo, $uid, 'logout', 'users', $uid, null, null);
}

redirect('/login.php');