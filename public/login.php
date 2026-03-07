<?php
declare(strict_types=1);
require __DIR__ . '/../app/core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email=:email AND deleted_at IS NULL LIMIT 1");
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch();

  if ($user && (int)$user['is_active'] === 1 && password_verify($pass, $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

	if ((int)$user['must_change_password'] === 1) {
 	 redirect('/account/change_password.php');
	}

    require __DIR__ . '/../app/services/Audit.php';
    Audit::log($pdo, (int)$user['id'], 'login', 'users', (int)$user['id'], null, ['email' => $email]);

    redirect('/index.php');
  }

  $error = "Login inválido.";
}
?>
<style>
    body{font-family: Arial, sans-serif; margin:0; background:#f6f7fb;}
    .topbar{background:#0f766e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center;}
    .topbar a{color:#fff; text-decoration:none; margin-right:12px;}
    .container{max-width:1100px; margin:18px auto; padding:0 14px;}
    .card{background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:14px; box-shadow:0 2px 10px rgba(0,0,0,.04);}
    .menu{display:flex; gap:10px; flex-wrap:wrap;}
    .menu a{background:rgba(255,255,255,.15); padding:6px 10px; border-radius:8px;}
    table{border-collapse:collapse; width:100%;}
    th,td{border:1px solid #e5e7eb; padding:8px; vertical-align:top;}
    th{background:#f3f4f6; text-align:left;}
    .muted{color:#6b7280; font-size:12px;}
    .btn{display:inline-block; padding:8px 10px; border-radius:8px; border:1px solid #d1d5db; background:#fff; text-decoration:none; color:#111827;}
    .btn-primary{background:#0f766e; color:#fff; border-color:#0f766e;}
    .btn-danger{background:#b91c1c; color:#fff; border-color:#b91c1c;}
    .actions form{display:inline;}
  </style>


<!doctype html>
<html>
<head><meta charset="utf-8"><title>SISElo</title></head>
<body>
  <div class="topbar">
  <div>
    <b>SisElo - UBS - CADH</b>
  </div>
  
</div>
<div style="max-width:1100px; margin:18px auto; padding:0 14px; text-align:center; margin:12px 0 30px 0; border:1px solid #000; margin-left:20%;" >
  <h1>Login - SISElo</h1>
  <?php if (!empty($error)) echo "<p style='color:red'>".h($error)."</p>"; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Email</label><br>
    <input name="email" type="email" required><br><br>
    <label>Senha</label><br>
    <input name="password" type="password" required><br><br>
    <button type="submit">Entrar</button>
  </form>
  </div>
  <p class="muted" style="text-align:center; margin:12px 0 30px 0;">
    <?= date('Y') ?> • Intranet CADH
  </p>
</div>
</body>
</html>