<?php
declare(strict_types=1);

function redirect(string $path): never {
  header("Location: {$path}");
  exit;
}

function current_user_id(): ?int {
  return $_SESSION['user_id'] ?? null;
}

function h(?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}