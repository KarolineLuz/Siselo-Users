<?php
declare(strict_types=1);
require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';
require __DIR__ . '/../../app/services/Audit.php';

require_auth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$editing = $id !== null;

if ($editing) {
  require_permission($pdo, 'patients.update');
  $stmt = $pdo->prepare("SELECT * FROM patients WHERE id=:id AND deleted_at IS NULL");
  $stmt->execute([':id' => $id]);
  $row = $stmt->fetch();
  if (!$row) { echo "Paciente não encontrado."; exit; }
} else {
  require_permission($pdo, 'patients.create');
  $row = [
    'first_cadh_date'=>null,'full_name'=>'','ses'=>'','cpf'=>'','birth_date'=>null,'sex'=>'','race'=>'',
    'responsible_name'=>'','phone'=>'','address'=>'','ubs_ref'=>'','team_ref'=>''
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $data = [
    'first_cadh_date' => $_POST['first_cadh_date'] ?: null,
    'full_name' => trim((string)($_POST['full_name'] ?? '')),
    'ses' => trim((string)($_POST['ses'] ?? '')),
    'cpf' => trim((string)($_POST['cpf'] ?? '')),
    'birth_date' => $_POST['birth_date'] ?: null,
    'sex' => trim((string)($_POST['sex'] ?? '')),
    'race' => trim((string)($_POST['race'] ?? '')),
    'responsible_name' => trim((string)($_POST['responsible_name'] ?? '')),
    'phone' => trim((string)($_POST['phone'] ?? '')),
    'address' => trim((string)($_POST['address'] ?? '')),
    'ubs_ref' => trim((string)($_POST['ubs_ref'] ?? '')),
    'team_ref' => trim((string)($_POST['team_ref'] ?? '')),
  ];

  if ($data['full_name'] === '') {
    $error = "Nome é obrigatório.";
  } else {
    if ($editing) {
      $before = $row;

      $stmt = $pdo->prepare("
        UPDATE patients SET
          first_cadh_date=:first_cadh_date,
          full_name=:full_name,
          ses=:ses,
          cpf=:cpf,
          birth_date=:birth_date,
          sex=:sex,
          race=:race,
          responsible_name=:responsible_name,
          phone=:phone,
          address=:address,
          ubs_ref=:ubs_ref,
          team_ref=:team_ref,
          updated_at=NOW()
        WHERE id=:id
      ");
      $stmt->execute($data + [':id'=>$id]);

      Audit::log($pdo, current_user_id(), 'update', 'patients', $id, $before, $data);
    } else {
      $stmt = $pdo->prepare("
        INSERT INTO patients
        (first_cadh_date, full_name, ses, cpf, birth_date, sex, race, responsible_name, phone, address, ubs_ref, team_ref)
        VALUES
        (:first_cadh_date, :full_name, :ses, :cpf, :birth_date, :sex, :race, :responsible_name, :phone, :address, :ubs_ref, :team_ref)
      ");
      $stmt->execute($data);
      $newId = (int)$pdo->lastInsertId();

      Audit::log($pdo, current_user_id(), 'create', 'patients', $newId, null, $data);
    }

    redirect('/public/patients/list.php');
  }
}
?>
<?php
$pageTitle = 'Pacientes';
require __DIR__ . '/../../app/views/layout/header.php';
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title><?= $editing ? 'Editar' : 'Novo' ?> Paciente</title></head>
<body>
  <h1><?= $editing ? 'Editar' : 'Novo' ?> Paciente</h1>
  <?php if (!empty($error)) echo "<p style='color:red'>".h($error)."</p>"; ?>

  <form method="post">
    <?= csrf_field() ?>
    <label>Data 1º atendimento CADH</label><br>
    <input type="date" name="first_cadh_date" value="<?= h($row['first_cadh_date']) ?>"><br><br>

    <label>Nome completo *</label><br>
    <input name="full_name" value="<?= h($row['full_name']) ?>" required style="width:420px"><br><br>

    <label>SES</label><br>
    <input name="ses" value="<?= h($row['ses']) ?>"><br><br>

    <label>CPF</label><br>
    <input name="cpf" value="<?= h($row['cpf']) ?>"><br><br>

    <label>Data nascimento</label><br>
    <input type="date" name="birth_date" value="<?= h($row['birth_date']) ?>"><br><br>

    <label>Sexo</label><br>
    <input name="sex" value="<?= h($row['sex']) ?>"><br><br>

    <label>Cor/Raça</label><br>
    <input name="race" value="<?= h($row['race']) ?>"><br><br>

    <label>Responsável</label><br>
    <input name="responsible_name" value="<?= h($row['responsible_name']) ?>" style="width:420px"><br><br>

    <label>Telefone</label><br>
    <input name="phone" value="<?= h($row['phone']) ?>"><br><br>

    <label>Endereço</label><br>
    <input name="address" value="<?= h($row['address']) ?>" style="width:520px"><br><br>

    <label>UBS referência</label><br>
    <input name="ubs_ref" value="<?= h($row['ubs_ref']) ?>"><br><br>

    <label>Equipe referência</label><br>
    <input name="team_ref" value="<?= h($row['team_ref']) ?>"><br><br>

    <button type="submit">Salvar</button>
    <a href="/public/patients/list.php">Cancelar</a>
  </form>
  <?php require __DIR__ . '/../../app/views/layout/footer.php'; ?>
</body>
</html>