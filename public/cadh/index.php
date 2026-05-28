<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'patients.view');

$pageTitle = 'CADH';

require __DIR__ . '/../../app/views/layout/header.php';

function calcular_idade($data) {
    if (!$data) return '';
    try {
        $nasc = new DateTime($data);
        return $nasc->diff(new DateTime())->y . ' anos';
    } catch (Exception $e) {
        return '';
    }
}

$paciente = null;

if (!empty($_GET['q'])) {
    $q = trim($_GET['q']);

    $stmt = $pdo->prepare("
        SELECT * FROM patients 
        WHERE (full_name LIKE :q_name OR cpf LIKE :q_cpf OR team_ref LIKE :q_team_ref)
        AND deleted_at IS NULL 
        LIMIT 1
    ");

    $stmt->execute([
        'q_name' => '%' . $q . '%',
        'q_cpf' => '%' . $q . '%',
        'q_team_ref' => '%' . $q . '%',
    ]);
    $paciente = $stmt->fetch();
}
?>

<style>
.layout {
  display: flex;
  gap: 20px;
}

.sidebar {
  width: 220px; 
  background: #e5e7eb;
  padding: 15px;
  border-radius: 10px;
}

.sidebar input {
  width: 100%;
  padding: 6px; 
  margin-bottom: 5px;
  font-size: 12px;
}

.sidebar button {
  padding: 6px 10px;
  border: none;
  background: #1f7a73;
  color: white;
  border-radius: 6px;
  cursor: pointer;
}

.user-info {
  margin-top: 15px;
}

.user-info div {
  background: #cbd5e1;
  padding: 8px;
  border-radius: 8px;
  margin-bottom: 8px;
}

.content {
  flex: 1;
}

.grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.card-item {
  background: #e5e7eb;
  padding: 20px;
  text-align: center;
  border-radius: 10px;
  cursor: pointer;
  text-decoration: none;
  color: black;
}

.card-item:hover {
  background: #cbd5e1;
}
</style>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <form method="GET">
      <label>Buscar por nome, CPF ou equipe</label>
      <input type="text" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Digite nome, CPF ou equipe">      <button type="submit">Buscar</button>
    </form>

    <?php if ($paciente): ?>
    <div class="user-info">
        <div><b>Usuário:</b> <?= h($paciente['full_name']) ?></div>
        <div>Idade: <?= calcular_idade($paciente['birth_date'] ?? null) ?></div>
        <div>CPF: <?= h($paciente['cpf']) ?></div>
        <div>Equipe: <?= h($paciente['team_ref'] ?? '-') ?></div>
        <div>Telefone: <?= h($paciente['phone'] ?? '-') ?></div>
        <div>Email: <?= h($paciente['email'] ?? '-') ?></div>
    </div>

    <?php elseif (!empty($_GET['q'])): ?>
        <div class="user-info">
            <div>Usuário não encontrado</div>
        </div>
    <?php endif; ?>

  </div>

  <!-- CONTEÚDO -->
  <div class="content">

    <div class="grid">
      <div class="card-item">Cadastro</div>
      <div class="card-item">Mapa do Usuário</div>

      <?php if ($paciente): ?>
            <a href="/care_plans/list.php?patient_id=<?= (int)$paciente['id'] ?>" class="card-item">
                Plano de Cuidado
            </a>
        <?php else: ?>
            <div class="card-item" style="opacity:0.5; cursor:not-allowed;">
                Plano de Cuidado
            </div>
        <?php endif; ?>

    </div>

    <h3 style="margin-top:20px;">Especialidades e Corpo Clínico</h3>

    <div class="grid">
      <div class="card-item">Gestor do Cuidado</div>
      <div class="card-item">Técnico de Enfermagem</div>
      <div class="card-item">Psicólogo</div>
      <div class="card-item">Enfermeiro</div>
      <div class="card-item">Nutricionista</div>
      <div class="card-item">Endocrinologista</div>
      <div class="card-item">Cardiologista</div>
      <div class="card-item">Fisioterapeuta</div>
      <div class="card-item">Farmacêutico</div>
      <div class="card-item">Serviço Social</div>
      <div class="card-item">Oftamologista</div>
      <div class="card-item">Nefrologista</div>
    </div>

  </div>
</div>

<?php
require __DIR__ . '/../../app/views/layout/footer.php';
?>
