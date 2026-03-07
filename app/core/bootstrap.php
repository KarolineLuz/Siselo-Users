<?php
declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../config/db.php';

$dsn = "mysql:host={$config['host']};dbname={$config['db']};charset={$config['charset']}";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
} catch (Throwable $e) {
  http_response_code(500);
  echo "Erro de conexão com o banco.";
  exit;
}

require __DIR__ . '/functions.php';
require __DIR__ . '/csrf.php';