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

if ($editing) {
  require_permission($pdo, 'careplans.update');
  $st = $pdo->prepare("SELECT * FROM care_plans WHERE id=:id AND deleted_at IS NULL");
  $st->execute([':id'=>$id]);
  $plan = $st->fetch();
  if (!$plan) { echo "Plano não encontrado."; exit; }

  $it = $pdo->prepare("SELECT * FROM care_plan_items WHERE care_plan_id=:id ORDER BY sort_order ASC, id ASC");
  $it->execute([':id'=>$id]);
  $items = $it->fetchAll();
} else {
  require_permission($pdo, 'careplans.create');
  $plan = ['patient_id'=> $prefPatientId ?: '', 'start_date'=>'', 'end_date'=>'', 'interventions'=>''];
  $items = [];
}

$patients = $pdo->query("SELECT id, full_name, cpf, ses FROM patients WHERE deleted_at IS NULL ORDER BY full_name ASC LIMIT 500")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  $patientId = (int)($_POST['patient_id'] ?? 0);
  $startDate = (string)($_POST['start_date'] ?? '');
  $endDate = $_POST['end_date'] ?: null;
  $interventions = trim((string)($_POST['interventions'] ?? ''));

  $item_type = (array)($_POST['item_type'] ?? []);
  $title = (array)($_POST['title'] ?? []);
  $situation = (array)($_POST['situation'] ?? []);
  $recommendation = (array)($_POST['recommendation'] ?? []);
  $difficulty = (array)($_POST['difficulty'] ?? []);
  $goal = (array)($_POST['goal'] ?? []);
  $sort_order = (array)($_POST['sort_order'] ?? []);

  if ($patientId <= 0 || $startDate === '') {
    $error = "Paciente e data de início são obrigatórios.";
  } else {
    $pdo->beginTransaction();
    try {
      if ($editing) {
        $before = $plan;

        $up = $pdo->prepare("UPDATE care_plans SET patient_id=:p, start_date=:s, end_date=:e, interventions=:i, updated_at=NOW() WHERE id=:id");
        $up->execute([':p'=>$patientId, ':s'=>$startDate, ':e'=>$endDate, ':i'=>$interventions, ':id'=>$id]);

        // estratégia simples: apaga itens e recria (MVP)
        $pdo->prepare("DELETE FROM care_plan_items WHERE care_plan_id=:id")->execute([':id'=>$id]);

        Audit::log($pdo, current_user_id(), 'update', 'care_plans', $id, $before, [
          'patient_id'=>$patientId, 'start_date'=>$startDate, 'end_date'=>$endDate
        ]);

        $planId = $id;
      } else {
        $ins = $pdo->prepare("INSERT INTO care_plans (patient_id, start_date, end_date, interventions, created_by_user_id) VALUES (:p,:s,:e,:i,:u)");
        $ins->execute([':p'=>$patientId, ':s'=>$startDate, ':e'=>$endDate, ':i'=>$interventions, ':u'=>current_user_id()]);
        $planId = (int)$pdo->lastInsertId();

        Audit::log($pdo, current_user_id(), 'create', 'care_plans', $planId, null, [
          'patient_id'=>$patientId, 'start_date'=>$startDate, 'end_date'=>$endDate
        ]);
      }

      $insItem = $pdo->prepare("
        INSERT INTO care_plan_items
        (care_plan_id, item_type, title, situation, recommendation, difficulty, goal, sort_order)
        VALUES
        (:cid, :t, :title, :sit, :rec, :dif, :goal, :ord)
      ");

      for ($i=0; $i<count($item_type); $i++) {
        $t = trim((string)($item_type[$i] ?? ''));
        $ttl = trim((string)($title[$i] ?? ''));
        $sit = trim((string)($situation[$i] ?? ''));
        $rec = trim((string)($recommendation[$i] ?? ''));
        $dif = trim((string)($difficulty[$i] ?? ''));
        $gol = trim((string)($goal[$i] ?? ''));
        $ord = (int)($sort_order[$i] ?? $i);

        if ($t === '') continue;
        if ($ttl === '' && $sit === '' && $rec === '' && $dif === '' && $gol === '') continue;

        $insItem->execute([
          ':cid'=>$planId, ':t'=>$t, ':title'=>$ttl,
          ':sit'=>$sit, ':rec'=>$rec, ':dif'=>$dif, ':goal'=>$gol, ':ord'=>$ord
        ]);
      }

      $pdo->commit();
      redirect('/care_plans/list.php');
    } catch (Throwable $e) {
      $pdo->rollBack();
      $error = "Erro ao salvar: ".$e->getMessage();
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
<head>
  <meta charset="utf-8">
  <title><?= $editing ? 'Editar' : 'Novo' ?> Plano de Cuidado</title>
  <style>
    .row{border:1px solid #ccc; padding:10px; margin:8px 0;}
    textarea{width:100%; height:60px;}
    input[type="text"]{width:100%;}
  </style>
</head>
<body>
  <h1><?= $editing ? 'Editar' : 'Novo' ?> Plano de Cuidado</h1>
  <?php if (!empty($error)) echo "<p style='color:red'>".h($error)."</p>"; ?>

  <form method="post">
    <?= csrf_field() ?>

    <label>Paciente *</label><br>
    <select name="patient_id" required>
      <option value="">-- selecione --</option>
      <?php foreach ($patients as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)$plan['patient_id']===(int)$p['id'])?'selected':'' ?>>
          <?= h($p['full_name']) ?> (CPF: <?= h($p['cpf']) ?> | SES: <?= h($p['ses']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <br><br>

    <label>Início *</label><br>
    <input type="date" name="start_date" value="<?= h($plan['start_date']) ?>" required>
    <br><br>

    <label>Fim</label><br>
    <input type="date" name="end_date" value="<?= h($plan['end_date']) ?>">
    <br><br>

    <label>Intervenções medicamentosas e não medicamentosas</label><br>
    <textarea name="interventions"><?= h($plan['interventions']) ?></textarea>

    <h3>Itens do Plano (dinâmicos)</h3>
    <p>
      <button type="button" onclick="addItem('alerta')">+ Alerta</button>
      <button type="button" onclick="addItem('meta')">+ Meta</button>
      <button type="button" onclick="addItem('dificuldade')">+ Dificuldade</button>
      <button type="button" onclick="addItem('recomendacao')">+ Recomendação</button>
    </p>

    <div id="items"></div>

    <br>
    <button type="submit">Salvar</button>
    <a href="/care_plans/list.php">Cancelar</a>
  </form>

  <template id="itemTpl">
    <div class="row">
      <div style="display:flex; gap:10px;">
        <div style="flex:2">
          <label>Tipo</label>
          <select name="item_type[]">
            <option value="alerta">alerta</option>
            <option value="meta">meta</option>
            <option value="dificuldade">dificuldade</option>
            <option value="recomendacao">recomendacao</option>
          </select>
        </div>
        <div style="flex:5">
          <label>Título</label>
          <input type="text" name="title[]" placeholder="Ex: Sinais de alerta / Meta / etc">
        </div>
        <div style="flex:1">
          <label>Ordem</label>
          <input type="text" name="sort_order[]" value="0">
        </div>
        <div style="flex:1; display:flex; align-items:end;">
          <button type="button" onclick="this.closest('.row').remove()">Remover</button>
        </div>
      </div>

      <label>Situação</label>
      <textarea name="situation[]"></textarea>

      <label>Recomendação</label>
      <textarea name="recommendation[]"></textarea>

      <label>Dificuldade</label>
      <textarea name="difficulty[]"></textarea>

      <label>Meta</label>
      <textarea name="goal[]"></textarea>
    </div>
  </template>

  <script>
    const itemsEl = document.getElementById('items');
    const tpl = document.getElementById('itemTpl');

    function addItem(type){
      const node = tpl.content.cloneNode(true);
      const box = node.querySelector('.row');
      const sel = box.querySelector('select[name="item_type[]"]');
      sel.value = type;

      const order = box.querySelector('input[name="sort_order[]"]');
      order.value = itemsEl.children.length + 1;

      itemsEl.appendChild(node);
    }

    // Carregar itens existentes (modo edição)
    const existing = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;
    if (existing && existing.length){
      existing.forEach((it, idx) => {
        addItem(it.item_type || 'meta');
        const row = itemsEl.children[itemsEl.children.length-1];

        row.querySelector('select[name="item_type[]"]').value = it.item_type || 'meta';
        row.querySelector('input[name="title[]"]').value = it.title || '';
        row.querySelector('input[name="sort_order[]"]').value = it.sort_order || (idx+1);

        row.querySelector('textarea[name="situation[]"]').value = it.situation || '';
        row.querySelector('textarea[name="recommendation[]"]').value = it.recommendation || '';
        row.querySelector('textarea[name="difficulty[]"]').value = it.difficulty || '';
        row.querySelector('textarea[name="goal[]"]').value = it.goal || '';
      });
    } else {
      // começa com 1 item por padrão (opcional)
      addItem('meta');
    }
  </script>
</body>
</html>