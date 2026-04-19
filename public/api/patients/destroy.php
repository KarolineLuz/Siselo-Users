<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api/bootstrap.php';
require_once __DIR__ . '/../../../app/controllers/PatientController.php';

if (api_request_method() !== 'POST') {
  api_error('Metodo nao permitido.', 405);
}

PatientController::destroy($pdo);
