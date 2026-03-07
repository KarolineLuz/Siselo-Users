<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/core/bootstrap.php';
require __DIR__ . '/../../../app/middleware/auth.php';
require __DIR__ . '/../../../app/middleware/rbac.php';
require __DIR__ . '/../../../app/services/Audit.php';

require_auth();
require_permission($pdo, 'admin.manage');

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editing = $id !== null;

$roles = $pdo->query("SELECT * FROM roles ORDER BY name ASC")->fetchAll();

$user = [
  'name' => '',
  'email' => '',
  'is_active' => 1,
];

$userRoleIds = [];

if ($editing) {
  $st = $pdo->prepare("SELECT * FROM users WHERE id=:id AND deleted_at IS NULL");
  $st->execute([':id' => $id]);
  $u = $st->fetch();
  if (!$u) { echo "Usuário não encontrado."; exit; }
  $user = $u;

  $st2 = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id=:id");
  $st2->execute([':id' => $id]);
  $userRoleIds = array_map('intval', array_column($st2->fetchAll(), 'role_id'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $isActive = isset($_POST['is_active']) ? 1 : 0;
  $roleIds = array_map('intval', (array)($_POST['role_ids'] ?? []));

  if ($name === '' || $email === '') {
    $error = "Nome e email são obrigatórios.";
  } else {
    $pdo->beginTransaction();
    try {
      if ($editing) {
        $before = $user;

        $st = $pdo->prepare("UPDATE users SET name=:n, email=:e, is_active=:a, updated_at=NOW() WHERE id=:id");
        $st->execute([':n'=>$name, ':e'=>$email, ':a'=>$isActive, ':id'=>$id]);

        $pdo->prepare("DELETE FROM user_roles WHERE user_id=:id")->execute([':id'=>$id]);
        if (count($roleIds)) {
          $ins = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
          foreach ($roleIds as $rid) $ins->execute([':uid'=>$id, ':rid'=>$rid]);
        }

        Audit::log($pdo, current_user_id(), 'update', 'users', $id, $before, [
          'name'=>$name, 'email'=>$email, 'is_active'=>$isActive, 'role_ids'=>$roleIds
        ]);
      } else {
        // senha temporária obrigatória no create
        $tempPass = (string)($_POST['temp_password'] ?? '');
        if ($tempPass === '') throw new RuntimeException("Senha temporária obrigatória.");

        $hash = password_hash($tempPass, PASSWORD_DEFAULT);

       $st = $pdo->prepare("
  			INSERT INTO users (name, email, password_hash, is_active, must_change_password)
  			VALUES (:n, :e, :h, :a, 1)
				");
	$st->execute([':n'=>$name, ':e'=>$email, ':h'=>$hash, ':a'=>$isActive]);
        $newId = (int)$pdo->lastInsertId();

        if (count($roleIds)) {
          $ins = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
          foreach ($roleIds as $rid) $ins->execute([':uid'=>$newId, ':rid'=>$rid]);
        }

        Audit::log($pdo, current_user_id(), 'create', 'users', $newId, null, [
          'name'=>$name, 'email'=>$email, 'is_active'=>$isActive, 'role_ids'=>$roleIds
        ]);
      }

      $pdo->commit();
      redirect('/public/admin/users/list.php');
    } catch (Throwable $e) {
      $pdo->rollBack();
      $error = "Erro ao salvar: " . $e->getMessage();
    }
  }
}
?>
<?php
$pageTitle = 'Pacientes';
require __DIR__ . '/../../../app/views/layout/header.php';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title><?= $editing ? 'Editar' : 'Novo' ?> Usuário</title></head>
<body>
  <h1><?= $editing ? 'Editar' : 'Novo' ?> Usuário</h1>
  <?php if (!empty($error)) echo "<p style='color:red'>".h($error)."</p>"; ?>

  <form method="post">
    <?= csrf_field() ?>
    <label>Nome</label><br>
    <input name="name" value="<?= h($user['name']) ?>" required style="width:360px"><br><br>

    <label>Email</label><br>
    <input name="email" type="email" value="<?= h($user['email']) ?>" required style="width:360px"><br><br>

    <label>
      <input type="checkbox" name="is_active" <?= ((int)$user['is_active']===1) ? 'checked' : '' ?>>
      Ativo
    </label>
    <br><br>

    <?php if (!$editing): ?>
      <label>Senha temporária (o usuário troca depois)</label><br>
      <input name="temp_password" type="text" placeholder="Ex: Temporaria@123" required style="width:260px"><br><br>
    <?php endif; ?>

    <fieldset>
      <legend>Roles</legend>
      <?php foreach ($roles as $r): ?>
        <?php $rid = (int)$r['id']; ?>
        <label style="display:block">
          <input type="checkbox" name="role_ids[]" value="<?= $rid ?>" <?= in_array($rid, $userRoleIds, true) ? 'checked' : '' ?>>
          <?= h($r['name']) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <br>
    <button type="submit">Salvar</button>
    <a href="/../admin/users/list.php">Cancelar</a>
  </form>
</body>
</html>