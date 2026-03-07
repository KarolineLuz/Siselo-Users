<?php
declare(strict_types=1);

require __DIR__ . '/../../app/core/bootstrap.php';
require __DIR__ . '/../../app/middleware/auth.php';
require __DIR__ . '/../../app/middleware/rbac.php';

require_auth();
require_permission($pdo, 'careplans.view');

require __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("
  SELECT cp.*, p.full_name, p.cpf, p.ses, p.birth_date, p.phone, p.ubs_ref, p.team_ref
  FROM care_plans cp
  JOIN patients p ON p.id = cp.patient_id
  WHERE cp.id=:id AND cp.deleted_at IS NULL AND p.deleted_at IS NULL
");
$st->execute([':id' => $id]);
$plan = $st->fetch();
if (!$plan) { http_response_code(404); echo "Plano não encontrado."; exit; }

$it = $pdo->prepare("
  SELECT * FROM care_plan_items
  WHERE care_plan_id=:id
  ORDER BY sort_order ASC, id ASC
");
$it->execute([':id' => $id]);
$items = $it->fetchAll();

function esc(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$groups = [
  'alerta' => [],
  'meta' => [],
  'dificuldade' => [],
  'recomendacao' => [],
];

foreach ($items as $item) {
  $t = $item['item_type'] ?? '';
  if (!isset($groups[$t])) $groups[$t] = [];
  $groups[$t][] = $item;
}

$html = '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body{ font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
  .title{ font-size: 18px; font-weight: bold; margin-bottom: 6px; }
  .subtitle{ color:#444; margin-bottom: 12px; }
  .box{ border:1px solid #ddd; padding:10px; margin:10px 0; border-radius:6px; }
  .h2{ font-size: 14px; font-weight:bold; margin: 0 0 6px 0; }
  .muted{ color:#666; font-size: 11px; }
  table{ width:100%; border-collapse: collapse; }
  th,td{ border:1px solid #ddd; padding:6px; vertical-align: top; }
  th{ background:#f3f3f3; text-align:left; }
</style>
</head>
<body>
  <div class="title">Plano de Cuidado</div>
  <div class="subtitle">
    Paciente: <b>'.esc((string)$plan['full_name']).'</b> • CPF: '.esc((string)$plan['cpf']).' • SES: '.esc((string)$plan['ses']).'<br>
    Início: <b>'.esc((string)$plan['start_date']).'</b> • Fim: <b>'.esc((string)$plan['end_date']).'</b><br>
    Tel: '.esc((string)$plan['phone']).' • UBS: '.esc((string)$plan['ubs_ref']).' • Equipe: '.esc((string)$plan['team_ref']).'
  </div>

  <div class="box">
    <div class="h2">Intervenções medicamentosas e não medicamentosas</div>
    <div>'.nl2br(esc((string)$plan['interventions'])).'</div>
  </div>
';

$labels = [
  'alerta' => 'Sinais de alerta',
  'meta' => 'Metas',
  'dificuldade' => 'Dificuldades',
  'recomendacao' => 'Recomendações',
];

foreach ($labels as $key => $label) {
  $html .= '<div class="box"><div class="h2">'.esc($label).'</div>';

  if (empty($groups[$key])) {
    $html .= '<div class="muted">Sem registros.</div></div>';
    continue;
  }

  $html .= '<table><tr><th>Título</th><th>Conteúdo</th></tr>';

  foreach ($groups[$key] as $item) {
    $contentParts = [];
    if (!empty($item['situation'])) $contentParts[] = '<b>Situação:</b> '.nl2br(esc((string)$item['situation']));
    if (!empty($item['recommendation'])) $contentParts[] = '<b>Recomendação:</b> '.nl2br(esc((string)$item['recommendation']));
    if (!empty($item['difficulty'])) $contentParts[] = '<b>Dificuldade:</b> '.nl2br(esc((string)$item['difficulty']));
    if (!empty($item['goal'])) $contentParts[] = '<b>Meta:</b> '.nl2br(esc((string)$item['goal']));

    $html .= '<tr>
      <td style="width:28%;">'.esc((string)$item['title']).'</td>
      <td>'.implode('<br><br>', $contentParts).'</td>
    </tr>';
  }

  $html .= '</table></div>';
}

$html .= '
  <div class="muted">Gerado em '.date('d/m/Y H:i').'</div>
</body>
</html>
';

$dompdf = new Dompdf(['isRemoteEnabled' => false]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Plano_de_Cuidado_'.$id.'.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$filename.'"');
echo $dompdf->output();