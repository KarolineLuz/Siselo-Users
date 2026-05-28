<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/core/bootstrap.php';
require __DIR__ . '/../../../app/middleware/auth.php';
require __DIR__ . '/../../../app/middleware/rbac.php';
require __DIR__ . '/../../../app/models/User.php';

require_auth();
require_permission($pdo, 'admin.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

try {
  $deleted = User::softDelete($pdo, $id, (int)current_user_id());
} catch (Throwable $error) {
  http_response_code(422);
  echo h($error->getMessage());
  exit;
}

if ($deleted === null) redirect('/public/admin/users/list.php');

redirect('/public/admin/users/list.php');
