<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'patients.view');

function patient_list_age_label(?string $birthDate): string {
  if ($birthDate === null || $birthDate === '') {
    return '';
  }

  try {
    $birth = new DateTimeImmutable($birthDate);
    return $birth->diff(new DateTimeImmutable('today'))->y . ' anos';
  } catch (Throwable $e) {
    return '';
  }
}

$genderLabels = [
  'masculino' => 'M',
  'feminino' => 'F',
  'outro' => 'O',
];

$q = trim((string)($_GET['q'] ?? ''));

$sql = 'SELECT * FROM patients WHERE deleted_at IS NULL';
$params = [];

if ($q !== '') {
  $sql .= ' AND (full_name LIKE :q_name OR cpf LIKE :q_cpf OR ses LIKE :q_ses OR phone LIKE :q_phone OR email LIKE :q_email)';
  $searchTerm = "%{$q}%";
  $params['q_name'] = $searchTerm;
  $params['q_cpf'] = $searchTerm;
  $params['q_ses'] = $searchTerm;
  $params['q_phone'] = $searchTerm;
  $params['q_email'] = $searchTerm;
}

$sql .= ' ORDER BY full_name ASC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<?php
$pageTitle = 'Usuários';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<html>
<head><meta charset="utf-8"><title>Pacientes</title></head>
<body>
  <h1>Usuários</h1>

  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="Buscar por nome/CPF/SES/telefone/email">
    <button type="submit">Buscar</button>
  </form>

  <p>
    <?php if (can($pdo, 'patients.create')): ?>
      <a href="/patients/form.php">+ Novo Usuário</a>
    <?php endif; ?>
    <?php if (can($pdo, 'patients.restore')): ?>
      | <a href="/patients/trash.php">Lixeira</a>
    <?php endif; ?>
    | <a href="/index.php">Voltar</a>
  </p>

  <table border="1" cellpadding="6">
    <tr>
      <th>Usuário</th>
      <th>CPF</th>
      <th>SES</th>
      <th>Contato</th>
      <th>Sangue</th>
      <th>Status</th>
      <th>Acoes</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <?php
        $gender = strtolower((string)($r['sex'] ?? ''));
        $genderLabel = $genderLabels[$gender] ?? '';
        $ageLabel = patient_list_age_label($r['birth_date'] ?? null);
        $statusLabel = ((string)($r['status'] ?? 'ativo') === 'ativo') ? 'Ativo' : 'Inativo';
      ?>
      <tr>
        <td>
          <b><?= h($r['full_name']) ?></b><br>
          <small>
            <?php if ($ageLabel !== ''): ?><?= h($ageLabel) ?><?php endif; ?>
            <?php if ($ageLabel !== '' && $genderLabel !== ''): ?> | <?php endif; ?>
            <?php if ($genderLabel !== ''): ?><?= h($genderLabel) ?><?php endif; ?>
          </small>
        </td>
        <td><?= h($r['cpf']) ?></td>
        <td><?= h($r['ses']) ?></td>
        <td>
          <?= h($r['phone'] ?? '') ?><br>
          <small><?= h($r['email'] ?? '') ?></small>
        </td>
        <td><?= h($r['blood_type'] ?? '') ?></td>
        <td><?= h($statusLabel) ?></td>
        <td>
          <a href="/patients/show.php?id=<?= (int)$r['id'] ?>">Usuário 360</a>
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
