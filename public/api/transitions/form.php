<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api/bootstrap.php';
require_once __DIR__ . '/../../../app/controllers/TransitionController.php';

if (!in_array(api_request_method(), ['GET', 'POST'], true)) {
  api_error('Metodo nao permitido.', 405);
}

TransitionController::form($pdo);
