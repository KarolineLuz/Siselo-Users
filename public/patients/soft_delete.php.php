<?php
declare(strict_types=1);
require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';
require __DIR__ . '/../../app/services/Audit.php';

require_auth();
require_permission($pdo, 'patients.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM patients WHERE id=:id AND deleted_at IS NULL");
$stmt->execute([':id'=>$id]);
$before = $stmt->fetch();
if (!$before) redirect('/patients/list.php');

$upd = $pdo->prepare("UPDATE patients SET deleted_at=NOW() WHERE id=:id");
$upd->execute([':id'=>$id]);

Audit::log($pdo, current_user_id(), 'delete', 'patients', $id, $before, ['deleted_at' => date('c')]);

redirect('/patients/list.php');