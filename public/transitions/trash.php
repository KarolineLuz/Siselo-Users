<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'transitions.restore'); // admin

$q = trim((string)($_GET['q'] ?? ''));

$sql = "
  SELECT t.*, p.full_name, p.cpf, p.ses
  FROM transitions t
  JOIN patients p ON p.id = t.patient_id
  WHERE t.deleted_at IS NOT NULL
";
$params = [];
if ($q !== '') {
  $sql .= " AND (p.full_name LIKE :q OR p.cpf LIKE :q OR p.ses LIKE :q OR t.status LIKE :q OR t.to_service LIKE :q OR t.from_service LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY t.deleted_at DESC LIMIT 300";

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
<head><meta charset="utf-8"><title>Lixeira - Transições</title></head>
<body>
  <h1>Lixeira: Transições</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar paciente/CPF/SES/status/serviço">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <a href="/transitions/list.php">Voltar</a> |
    <a href="/index.php">Home</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>Apagado em</th><th>Data</th><th>Paciente</th><th>De</th><th>Para</th><th>Status</th><th>Ações</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['deleted_at']) ?></td>
        <td><?= h($r['transition_date']) ?></td>
        <td><?= h($r['full_name']) ?><br><small>CPF: <?= h($r['cpf']) ?> | SES: <?= h($r['ses']) ?></small></td>
        <td><?= h($r['from_service']) ?></td>
        <td><?= h($r['to_service']) ?></td>
        <td><?= h($r['status']) ?></td>
        <td>
          <form method="post" action="/transitions/restore.php" style="display:inline">
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