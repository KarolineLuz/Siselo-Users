<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api/bootstrap.php';
require_once __DIR__ . '/../../../app/controllers/EncounterController.php';

if (!in_array(api_request_method(), ['GET', 'POST'], true)) {
  api_error('Metodo nao permitido.', 405);
}

EncounterController::form($pdo);
