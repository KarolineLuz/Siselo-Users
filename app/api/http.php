<?php
declare(strict_types=1);

function api_apply_cors(array $config): void {
  $origin = api_request_header('Origin');
  $frontendOrigin = (string)($config['frontend_origin'] ?? '');

  if ($origin !== null && $frontendOrigin !== '' && $origin === $frontendOrigin) {
    header('Access-Control-Allow-Origin: ' . $frontendOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
  }
}

function api_json($payload, int $status = 200): never {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function api_success($data = null, int $status = 200): never {
  api_json([
    'success' => true,
    'data' => $data,
  ], $status);
}

function api_error(string $message, int $status = 400, array $errors = []): never {
  $payload = [
    'success' => false,
    'message' => $message,
  ];

  if ($errors !== []) {
    $payload['errors'] = $errors;
  }

  api_json($payload, $status);
}

function api_no_content(): never {
  http_response_code(204);
  exit;
}
