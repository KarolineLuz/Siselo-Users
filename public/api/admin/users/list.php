<?php
declare(strict_types=1);

require __DIR__ . '/../../../../app/api/bootstrap.php';
require_once __DIR__ . '/../../../../app/controllers/AdminUserController.php';

if (api_request_method() !== 'GET') {
  api_error('Metodo nao permitido.', 405);
}

AdminUserController::list($pdo);
