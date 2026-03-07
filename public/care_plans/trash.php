<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'careplans.restore'); // admin only

$q = trim((string)($_GET['q'] ?? ''));

$sql = "
  SELECT cp.*, p.full_name
  FROM care_plans cp
  JOIN patients p ON p.id = cp.patient_id
  WHERE cp.deleted_at IS NOT NULL
";
$params = [];
if ($q !== '') {
  $sql .= " AND (p.full_name LIKE :q OR p.cpf LIKE :q OR p.ses LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY cp.deleted_at DESC LIMIT 300";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<?php
$pageTitle = 'Pacientes';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Lixeira - Planos de Cuidado</title></head>
<body>
  <h1>Lixeira: Planos de Cuidado</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar paciente (nome/CPF/SES)">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <a href="/care_plans/list.php">Voltar</a> |
    <a href="/index.php">Home</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>ID</th><th>Paciente</th><th>Início</th><th>Fim</th><th>Apagado em</th><th>Ações</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['full_name']) ?></td>
        <td><?= h($r['start_date']) ?></td>
        <td><?= h($r['end_date']) ?></td>
        <td><?= h($r['deleted_at']) ?></td>
        <td>
          <form method="post" action="/care_plans/restore.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit">Restaurar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>