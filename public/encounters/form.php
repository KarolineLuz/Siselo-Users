<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';
require __DIR__ . '/../../app/services/Audit.php';

require_auth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editing = $id !== null;

$prefPatientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$patients = $pdo->query("SELECT id, full_name, cpf, ses FROM patients WHERE deleted_at IS NULL ORDER BY full_name ASC LIMIT 800")->fetchAll();

if ($editing) {
  require_permission($pdo, 'encounters.update');

  $st = $pdo->prepare("SELECT * FROM encounters WHERE id=:id AND deleted_at IS NULL");
  $st->execute([':id'=>$id]);
  $row = $st->fetch();
  if (!$row) { echo "Atendimento não encontrado."; exit; }
} else {
  require_permission($pdo, 'encounters.create');
  $row = [
    'patient_id' => $prefPatientId ?: '',
    'encounter_date' => date('Y-m-d'),
    'specialty' => '',
    'summary' => ''
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $patientId = (int)($_POST['patient_id'] ?? 0);
  $date = (string)($_POST['encounter_date'] ?? '');
  $specialty = trim((string)($_POST['specialty'] ?? ''));
  $summary = trim((string)($_POST['summary'] ?? ''));

  if ($patientId <= 0 || $date === '' || $specialty === '') {
    $error = "Paciente, data e especialidade são obrigatórios.";
  } else {
    if ($editing) {
      $before = $row;

      $up = $pdo->prepare("
        UPDATE encounters SET
          patient_id=:p,
          encounter_date=:d,
          specialty=:s,
          summary=:m,
          professional_user_id=:u,
          updated_at=NOW()
        WHERE id=:id
      ");
      $up->execute([
        ':p'=>$patientId, ':d'=>$date, ':s'=>$specialty, ':m'=>$summary,
        ':u'=>current_user_id(), ':id'=>$id
      ]);

      Audit::log($pdo, current_user_id(), 'update', 'encounters', $id, $before, [
        'patient_id'=>$patientId,'encounter_date'=>$date,'specialty'=>$specialty,'summary'=>$summary
      ]);

      redirect("/patients/show.php?id={$patientId}&tab=atendimentos");
    } else {
      $ins = $pdo->prepare("
        INSERT INTO encounters (patient_id, encounter_date, specialty, professional_user_id, summary)
        VALUES (:p, :d, :s, :u, :m)
      ");
      $ins->execute([
        ':p'=>$patientId, ':d'=>$date, ':s'=>$specialty, ':u'=>current_user_id(), ':m'=>$summary
      ]);
      $newId = (int)$pdo->lastInsertId();

      Audit::log($pdo, current_user_id(), 'create', 'encounters', $newId, null, [
        'patient_id'=>$patientId,'encounter_date'=>$date,'specialty'=>$specialty,'summary'=>$summary
      ]);

      redirect("/patients/show.php?id={$patientId}&tab=atendimentos");
    }
  }
}
?>
<?php
$pageTitle = 'Pacientes';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title><?= $editing ? 'Editar' : 'Novo' ?> Atendimento</title></head>
<body>
  <h1><?= $editing ? 'Editar' : 'Novo' ?> Atendimento</h1>
  <?php if (!empty($error)) echo "<p style='color:red'>".h($error)."</p>"; ?>

  <form method="post">
    <?= csrf_field() ?>

    <label>Paciente *</label><br>
    <select name="patient_id" required>
      <option value="">-- selecione --</option>
      <?php foreach ($patients as $p): ?>
        <option value="<?= (int)$p['id'] ?>"
          <?= ((int)$row['patient_id']===(int)$p['id']) ? 'selected' : '' ?>>
          <?= h($p['full_name']) ?> (CPF: <?= h($p['cpf']) ?> | SES: <?= h($p['ses']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <br><br>

    <label>Data *</label><br>
    <input type="date" name="encounter_date" value="<?= h($row['encounter_date']) ?>" required>
    <br><br>

    <label>Especialidade *</label><br>
    <input name="specialty" value="<?= h($row['specialty']) ?>" placeholder="Ex: Enfermagem, Nutrição..." required style="width:360px">
    <br><br>

    <label>Resumo</label><br>
    <textarea name="summary" style="width:520px; height:120px;"><?= h($row['summary']) ?></textarea>
    <br><br>

    <button type="submit">Salvar</button>
    <a href="/encounters/list.php">Cancelar</a>
  </form>
</body>
</html>