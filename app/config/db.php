<?php
declare(strict_types=1);

return [
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'db'   => getenv('DB_NAME') ?: 'intranet_cadh',
  'user' => getenv('DB_USER') ?: 'root',
  'pass' => getenv('DB_PASSWORD') ?: '',
  'charset' => 'utf8mb4',
];