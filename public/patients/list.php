<?php
declare(strict_types=1);
require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'patients.view');

$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT * FROM patients WHERE deleted_at IS NULL";
$params = [];

if ($q !== '') {
  $sql .= " AND (full_name LIKE :q OR cpf LIKE :q OR ses LIKE :q)";
  $params[':q'] = "%{$q}%";
}
$sql .= " ORDER BY full_name ASC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<?php
$pageTitle = 'Pacientes';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<html>
<head><meta charset="utf-8"><title>Pacientes</title></head>
<body>
  <h1>Pacientes</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar por nome/CPF/SES">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <?php if (can($pdo, 'patients.create')): ?>
      <a href="/patients/form.php">+ Novo paciente</a>
    <?php endif; ?>
	<?php if (can($pdo, 'patients.restore')): ?>
  | 		<a href="/patients/trash.php">Lixeira</a>
	<?php endif; ?>
    | <a href="/index.php">Voltar</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>Nome</th><th>CPF</th><th>SES</th><th>Telefone</th><th>Ações</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['full_name']) ?></td>
        <td><?= h($r['cpf']) ?></td>
        <td><?= h($r['ses']) ?></td>
        <td><?= h($r['phone']) ?></td>
        <td>
	<a href="/patients/show.php?id=<?= (int)$r['id'] ?>">Ver</a>
          <?php if (can($pdo, 'patients.update')): ?>
            <a href="/patients/form.php?id=<?= (int)$r['id'] ?>">Editar</a>
          <?php endif; ?>

          <?php if (can($pdo, 'patients.delete')): ?>
            <form method="post" action="/patients/soft_delete.php" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button type="submit" onclick="return confirm('Confirma apagar? (soft delete)')">Apagar</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
</body>
</html>