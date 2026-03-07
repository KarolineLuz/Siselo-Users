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
$st->execute([':id'=>$id]);
$before = $st->fetch();
if (!$before) redirect('/public/admin/users/list.php');

$new = ((int)$before['is_active'] === 1) ? 0 : 1;

$up = $pdo->prepare("UPDATE users SET is_active=:a, updated_at=NOW() WHERE id=:id");
$up->execute([':a'=>$new, ':id'=>$id]);

Audit::log($pdo, current_user_id(), 'update', 'users', $id, $before, ['is_active'=>$new]);

redirect('/public/admin/users/list.php');