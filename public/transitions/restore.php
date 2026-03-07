<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';
require __DIR__ . '/../../app/services/Audit.php';

require_auth();
require_permission($pdo, 'transitions.restore');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

$st = $pdo->prepare("SELECT * FROM transitions WHERE id=:id AND deleted_at IS NOT NULL");
$st->execute([':id'=>$id]);
$before = $st->fetch();
if (!$before) redirect('/transitions/trash.php');

$up = $pdo->prepare("UPDATE transitions SET deleted_at=NULL, updated_at=NOW() WHERE id=:id");
$up->execute([':id'=>$id]);

Audit::log($pdo, current_user_id(), 'restore', 'transitions', $id, $before, ['deleted_at'=>null]);

redirect('/transitions/trash.php');