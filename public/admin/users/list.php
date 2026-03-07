<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/core/bootstrap.php';
require __DIR__ . '/../../../app/middleware/auth.php';
require __DIR__ . '/../../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'admin.manage');

$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT u.* FROM users u WHERE u.deleted_at IS NULL";
$params = [];
if ($q !== '') {
  $sql .= " AND (u.name LIKE :q OR u.email LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY u.id DESC LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

function roles_for(PDO $pdo, int $userId): string {
  $st = $pdo->prepare("
    SELECT r.name FROM roles r
    JOIN user_roles ur ON ur.role_id = r.id
    WHERE ur.user_id = :uid
    ORDER BY r.name
  ");
  $st->execute([':uid' => $userId]);
  $names = array_column($st->fetchAll(), 'name');
  return implode(', ', $names);
}
?>
<?php
$pageTitle = 'Pacientes';
require __DIR__ . '/../../../app/views/layout/header.php';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Admin - Usuários</title></head>
<body>
  <h1>Admin: Usuários</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar nome/email">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <a href="/../admin/users/form.php">+ Novo usuário</a> |
    <a href="/../index.php">Voltar</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>ID</th><th>Nome</th><th>Email</th><th>Roles</th><th>Status</th><th>Ações</th>
    </tr>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= h($u['name']) ?></td>
        <td><?= h($u['email']) ?></td>
        <td><?= h(roles_for($pdo, (int)$u['id'])) ?></td>
        <td><?= ((int)$u['is_active']===1) ? 'Ativo' : 'Inativo' ?></td>
        <td>
          <a href="/../admin/users/form.php?id=<?= (int)$u['id'] ?>">Editar</a>

          <form method="post" action="/../admin/users/toggle_active.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button type="submit"><?= ((int)$u['is_active']===1) ? 'Desativar' : 'Ativar' ?></button>
          </form>

          <form method="post" action="/../admin/users/reset_password.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button type="submit" onclick="return confirm('Resetar senha para Temporaria@123?')">Reset senha</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>