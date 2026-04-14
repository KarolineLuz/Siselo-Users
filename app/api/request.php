<?php
declare(strict_types=1);

function api_request_method(): string {
  return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function api_request_header(string $name): ?string {
  $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
  $value = $_SERVER[$normalized] ?? null;
  if ($value === null || $value === '') {
    return null;
  }

  return (string)$value;
}

function api_request_input(): array {
  static $input = null;

  if (is_array($input)) {
    return $input;
  }

  $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
  if (str_contains($contentType, 'application/json')) {
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
      $input = [];
      return $input;
    }

    $decoded = json_decode($rawBody, true);
    $input = is_array($decoded) ? $decoded : [];
    return $input;
  }

  $input = $_POST;
  return $input;
}

function api_query_param(string $key, ?string $default = null): ?string {
  $value = $_GET[$key] ?? $default;
  if ($value === null) {
    return null;
  }

  return is_string($value) ? $value : (string)$value;
}
