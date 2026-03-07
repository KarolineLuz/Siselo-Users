<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'patients.view');

$id = (int)($_GET['id'] ?? 0);
$tab = (string)($_GET['tab'] ?? 'planos');

$st = $pdo->prepare("SELECT * FROM patients WHERE id=:id AND deleted_at IS NULL");
$st->execute([':id'=>$id]);
$patient = $st->fetch();
if (!$patient) { echo "Paciente não encontrado."; exit; }

$plans = [];
$encs = [];
$trans = [];

if (can($pdo, 'careplans.view')) {
  $stp = $pdo->prepare("SELECT * FROM care_plans WHERE patient_id=:id AND deleted_at IS NULL ORDER BY id DESC");
  $stp->execute([':id'=>$id]);
  $plans = $stp->fetchAll();
}

if (can($pdo, 'encounters.view')) {
  $ste = $pdo->prepare("SELECT * FROM encounters WHERE patient_id=:id AND deleted_at IS NULL ORDER BY encounter_date DESC, id DESC");
  $ste->execute([':id'=>$id]);
  $encs = $ste->fetchAll();
}

if (can($pdo, 'transitions.view')) {
  $stt = $pdo->prepare("SELECT * FROM transitions WHERE patient_id=:id AND deleted_at IS NULL ORDER BY transition_date DESC, id DESC");
  $stt->execute([':id'=>$id]);
  $trans = $stt->fetchAll();
}

function tabLink(int $id, string $tab, string $label, string $current): string {
  $active = ($tab === $current) ? 'style="font-weight:bold"' : '';
  return '<a '.$active.' href="/patients/show.php?id='.$id.'&tab='.$tab.'">'.h($label).'</a>';
}
?>
<?php
$pageTitle = 'Pacientes';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Paciente 360</title>
</head>
<body>
  <h1>Paciente 360</h1>

  <p>
    <b><?= h($patient['full_name']) ?></b><br>
    CPF: <?= h($patient['cpf']) ?> | SES: <?= h($patient['ses']) ?><br>
    Tel: <?= h($patient['phone']) ?> | UBS: <?= h($patient['ubs_ref']) ?>
  </p>

  <p>
    <?= tabLink($id,'planos','Planos de Cuidado',$tab) ?> |
    <?= tabLink($id,'atendimentos','Atendimentos',$tab) ?> |
    <?= tabLink($id,'transicoes','Transições',$tab) ?>
  </p>

  <hr>

  <?php if ($tab === 'planos'): ?>
    <h2>Planos de Cuidado</h2>
	<?php if (can($pdo, 'transitions.create')): ?>
  		<p><a href="/transitions/form.php?patient_id=<?= $id ?>">+ Nova transição para este 		paciente</a></p>
	<?php endif; ?>
    <?php if (can($pdo, 'careplans.create')): ?>
      <p><a href="/care_plans/form.php?patient_id=<?= $id ?>">+ Novo plano para este paciente</a></p>
    <?php endif; ?>
<?php if (can($pdo, 'encounters.create')): ?>
  <p><a href="/encounters/form.php?patient_id=<?= $id ?>">+ Novo atendimento para este paciente</a></p>
<?php endif; ?>
    <table border="1" cellpadding="6">
      <tr><th>ID</th><th>Início</th><th>Fim</th><th>Ações</th></tr>
      <?php foreach ($plans as $p): ?>
        <tr>
          <td><?= (int)$p['id'] ?></td>
          <td><?= h($p['start_date']) ?></td>
          <td><?= h($p['end_date']) ?></td>
          <td>
	| <a href="/care_plans/pdf.php?id=<?= (int)$p['id'] ?>" target="_blank">PDF</a> 
            <?php if (can($pdo, 'careplans.update')): ?>
              <a href="/care_plans/form.php?id=<?= (int)$p['id'] ?>">Editar</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php elseif ($tab === 'atendimentos'): ?>
    <h2>Atendimentos</h2>
    <p>(MVP) Aqui entraremos com o CRUD de atendimentos por especialidade.</p>

    <table border="1" cellpadding="6">
      <tr><th>Data</th><th>Especialidade</th><th>Resumo</th></tr>
      <?php foreach ($encs as $e): ?>
        <tr>
          <td><?= h($e['encounter_date']) ?></td>
          <td><?= h($e['specialty']) ?></td>
          <td><?= h($e['summary']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php elseif ($tab === 'transicoes'): ?>
    <h2>Transições</h2>
    <p>(MVP) Aqui entraremos com o CRUD de transições do cuidado.</p>

    <table border="1" cellpadding="6">
      <tr><th>Data</th><th>De</th><th>Para</th><th>Status</th><th>Notas</th></tr>
      <?php foreach ($trans as $t): ?>
        <tr>
          <td><?= h($t['transition_date']) ?></td>
          <td><?= h($t['from_service']) ?></td>
          <td><?= h($t['to_service']) ?></td>
          <td><?= h($t['status']) ?></td>
          <td><?= h($t['notes']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>

  <?php endif; ?>

  <hr>
  <p>
    <a href="/patients/list.php">Voltar</a> |
    <a href="/index.php">Home</a>
  </p>
  <?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
</body>
</html>