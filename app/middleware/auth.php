<?php
declare(strict_types=1);

function require_auth(): void {
  if (!current_user_id()) {
    redirect('/public/login.php');
  }
}