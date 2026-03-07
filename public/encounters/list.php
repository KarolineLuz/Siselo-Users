<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'encounters.view');

$q = trim((string)($_GET['q'] ?? ''));

$sql = "
  SELECT e.*, p.full_name, p.cpf, p.ses
  FROM encounters e
  JOIN patients p ON p.id = e.patient_id
  WHERE e.deleted_at IS NULL AND p.deleted_at IS NULL
";
$params = [];
if ($q !== '') {
  $sql .= " AND (p.full_name LIKE :q OR p.cpf LIKE :q OR p.ses LIKE :q OR e.specialty LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY e.encounter_date DESC, e.id DESC LIMIT 300";

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
<head><meta charset="utf-8"><title>Atendimentos</title></head>
<body>
  <h1>Atendimentos</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar paciente/CPF/SES/especialidade">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <?php if (can($pdo, 'encounters.create')): ?>
      <a href="/encounters/form.php">+ Novo atendimento</a>
    <?php endif; ?>
    <?php if (can($pdo, 'encounters.restore')): ?>
      | <a href="/encounters/trash.php">Lixeira</a>
    <?php endif; ?>
    | <a href="/index.php">Home</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>Data</th><th>Paciente</th><th>Especialidade</th><th>Resumo</th><th>Ações</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['encounter_date']) ?></td>
        <td>
          <?= h($r['full_name']) ?><br>
          <small>CPF: <?= h($r['cpf']) ?> | SES: <?= h($r['ses']) ?></small>
        </td>
        <td><?= h($r['specialty']) ?></td>
        <td><?= h($r['summary']) ?></td>
        <td>
          <a href="/patients/show.php?id=<?= (int)$r['patient_id'] ?>&tab=atendimentos">Paciente 360</a>
          <?php if (can($pdo, 'encounters.update')): ?>
            | <a href="/encounters/form.php?id=<?= (int)$r['id'] ?>">Editar</a>
          <?php endif; ?>
          <?php if (can($pdo, 'encounters.delete')): ?>
            <form method="post" action="/encounters/soft_delete.php" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" onclick="return confirm('Apagar atendimento? (soft delete)')">Apagar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>