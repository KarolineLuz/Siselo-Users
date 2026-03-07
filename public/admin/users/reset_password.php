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
$newPass = 'Temporaria@123';
$newHash = password_hash($newPass, PASSWORD_DEFAULT);

$st = $pdo->prepare("SELECT * FROM users WHERE id=:id AND deleted_at IS NULL");
$st->execute([':id'=>$id]);
$before = $st->fetch();
if (!$before) redirect('/admin/users/list.php');

$up = $pdo->prepare("UPDATE users SET password_hash=:h, must_change_password=1, updated_at=NOW() WHERE id=:id");
$up->execute([':h'=>$newHash, ':id'=>$id]);

Audit::log($pdo, current_user_id(), 'update', 'users', $id, $before, ['password_reset' => true]);

redirect('/admin/users/list.php');