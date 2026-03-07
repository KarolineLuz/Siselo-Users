<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'patients.restore'); // só admin tem

$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT * FROM patients WHERE deleted_at IS NOT NULL";
$params = [];
if ($q !== '') {
  $sql .= " AND (full_name LIKE :q OR cpf LIKE :q OR ses LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY deleted_at DESC LIMIT 300";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Lixeira - Pacientes</title></head>
<body>
  <h1>Lixeira: Pacientes</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar por nome/CPF/SES">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <a href="/patients/list.php">Voltar para pacientes</a> |
    <a href="/index.php">Home</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>Nome</th><th>CPF</th><th>SES</th><th>Apagado em</th><th>Ações</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['full_name']) ?></td>
        <td><?= h($r['cpf']) ?></td>
        <td><?= h($r['ses']) ?></td>
        <td><?= h($r['deleted_at']) ?></td>
        <td>
          <form method="post" action="/patients/restore.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit">Restaurar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
</body>
</html>