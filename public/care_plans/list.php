<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'careplans.view');

$q = trim((string)($_GET['q'] ?? ''));

$sql = "
  SELECT cp.*, p.full_name
  FROM care_plans cp
  JOIN patients p ON p.id = cp.patient_id
  WHERE cp.deleted_at IS NULL AND p.deleted_at IS NULL
";
$params = [];
if ($q !== '') {
  $sql .= " AND (p.full_name LIKE :q OR p.cpf LIKE :q OR p.ses LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY cp.id DESC LIMIT 200";

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
<head><meta charset="utf-8"><title>Planos de Cuidado</title></head>
<body>
  <h1>Planos de Cuidado</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar paciente (nome/CPF/SES)">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <?php if (can($pdo, 'careplans.create')): ?>
      <a href="/care_plans/form.php">+ Novo plano</a>
    <?php endif; ?>
	<?php if (can($pdo, 'careplans.restore')): ?>
 		 | <a href="/care_plans/trash.php">Lixeira</a>
	<?php endif; ?>
    | <a href="/index.php">Home</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>ID</th><th>Paciente</th><th>Início</th><th>Fim</th><th>Ações</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['full_name']) ?></td>
        <td><?= h($r['start_date']) ?></td>
        <td><?= h($r['end_date']) ?></td>
        <td>
	| <a href="/care_plans/pdf.php?id=<?= (int)$r['id'] ?>" target="_blank">PDF</a>
          <?php if (can($pdo, 'careplans.update')): ?>
            <a href="/care_plans/form.php?id=<?= (int)$r['id'] ?>">Editar</a>
          <?php endif; ?>
          <?php if (can($pdo, 'careplans.delete')): ?>
            <form method="post" action="/care_plans/soft_delete.php" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" onclick="return confirm('Apagar plano? (soft delete)')">Apagar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>