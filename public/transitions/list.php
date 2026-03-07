<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'transitions.view');

$q = trim((string)($_GET['q'] ?? ''));

$sql = "
  SELECT t.*, p.full_name, p.cpf, p.ses
  FROM transitions t
  JOIN patients p ON p.id = t.patient_id
  WHERE t.deleted_at IS NULL AND p.deleted_at IS NULL
";
$params = [];
if ($q !== '') {
  $sql .= " AND (p.full_name LIKE :q OR p.cpf LIKE :q OR p.ses LIKE :q OR t.status LIKE :q OR t.to_service LIKE :q OR t.from_service LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY t.transition_date DESC, t.id DESC LIMIT 300";

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
<head><meta charset="utf-8"><title>Transições</title></head>
<body>
  <h1>Transições do Cuidado</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar paciente/CPF/SES/status/serviço">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <?php if (can($pdo, 'transitions.create')): ?>
      <a href="/transitions/form.php">+ Nova transição</a>
    <?php endif; ?>
    <?php if (can($pdo, 'transitions.restore')): ?>
      | <a href="/transitions/trash.php">Lixeira</a>
    <?php endif; ?>
    | <a href="/index.php">Home</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>Data</th><th>Paciente</th><th>De</th><th>Para</th><th>Status</th><th>Ações</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['transition_date']) ?></td>
        <td>
          <?= h($r['full_name']) ?><br>
          <small>CPF: <?= h($r['cpf']) ?> | SES: <?= h($r['ses']) ?></small>
        </td>
        <td><?= h($r['from_service']) ?></td>
        <td><?= h($r['to_service']) ?></td>
        <td><?= h($r['status']) ?></td>
        <td>
          <a href="/patients/show.php?id=<?= (int)$r['patient_id'] ?>&tab=transicoes">Paciente 360</a>
          <?php if (can($pdo, 'transitions.update')): ?>
            | <a href="/transitions/form.php?id=<?= (int)$r['id'] ?>">Editar</a>
          <?php endif; ?>
          <?php if (can($pdo, 'transitions.delete')): ?>
            <form method="post" action="/transitions/soft_delete.php" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" onclick="return confirm('Apagar transição? (soft delete)')">Apagar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>