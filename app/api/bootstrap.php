<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../middleware/rbac.php';
require_once __DIR__ . '/request.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/security.php';

$apiConfig = require __DIR__ . '/../config/api.php';

api_apply_cors($apiConfig);

if (api_request_method() === 'OPTIONS') {
  api_no_content();
}
