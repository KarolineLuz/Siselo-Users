<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/core/bootstrap.php';
require __DIR__ . '/../../../app/middleware/auth.php';
require __DIR__ . '/../../../app/middleware/rbac.php';
require __DIR__ . '/../../../app/services/Audit.php';

require_auth();
require_permission($pdo, 'admin.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

$st = $pdo->prepare("SELECT * FROM users WHERE id=:id AND deleted_at IS NULL");
$st->execute([':id' => $id]);
$before = $st->fetch();
if (!$before) redirect('/public/admin/users/list.php');

$up = $pdo->prepare("
  UPDATE users
  SET
    is_approved = 1,
    approved_at = COALESCE(approved_at, NOW()),
    approved_by_user_id = :approved_by_user_id,
    updated_at = NOW()
  WHERE id = :id
");
$up->execute([
  ':approved_by_user_id' => current_user_id(),
  ':id' => $id,
]);

Audit::log($pdo, current_user_id(), 'approve', 'users', $id, $before, [
  'is_approved' => 1,
  'approved_by_user_id' => current_user_id(),
]);

redirect('/public/admin/users/list.php');
