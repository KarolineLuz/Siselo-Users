<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/services/Audit.php';

require_auth();

$uid = current_user_id();

$st = $pdo->prepare("SELECT id, email, must_change_password FROM users WHERE id=:id AND deleted_at IS NULL");
$st->execute([':id'=>$uid]);
$user = $st->fetch();
if (!$user) { redirect('/logout.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $p1 = (string)($_POST['password'] ?? '');
  $p2 = (string)($_POST['password_confirm'] ?? '');

  if (strlen($p1) < 8) {
    $error = "A senha deve ter pelo menos 8 caracteres.";
  } elseif ($p1 !== $p2) {
    $error = "As senhas não conferem.";
  } else {
    $hash = password_hash($p1, PASSWORD_DEFAULT);
    $up = $pdo->prepare("UPDATE users SET password_hash=:h, must_change_password=0, updated_at=NOW() WHERE id=:id");
    $up->execute([':h'=>$hash, ':id'=>$uid]);

    Audit::log($pdo, $uid, 'update', 'users', $uid, ['must_change_password'=>1], ['must_change_password'=>0]);
    redirect('/index.php');
  }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Trocar senha</title></head>
<body>
  <h1>Trocar senha</h1>
  <p>Por segurança, você precisa definir uma nova senha para continuar.</p>

  <?php if (!empty($error)) echo "<p style='color:red'>".h($error)."</p>"; ?>

  <form method="post">
    <?= csrf_field() ?>
    <label>Nova senha</label><br>
    <input type="password" name="password" required><br><br>

    <label>Confirmar nova senha</label><br>
    <input type="password" name="password_confirm" required><br><br>

    <button type="submit">Salvar e continuar</button>
    <a href="/logout.php">Sair</a>
  </form>
</body>
</html>