<?php
declare(strict_types=1);

final class Audit {
  public static function log(PDO $pdo, ?int $userId, string $action, string $entity, ?int $entityId, $before, $after): void {
    $stmt = $pdo->prepare("
      INSERT INTO audit_log (user_id, action, entity, entity_id, ip_address, user_agent, before_json, after_json)
      VALUES (:user_id, :action, :entity, :entity_id, :ip, :ua, :before_json, :after_json)
    ");

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt->execute([
      ':user_id' => $userId,
      ':action' => $action,
      ':entity' => $entity,
      ':entity_id' => $entityId,
      ':ip' => $ip,
      ':ua' => $ua,
      ':before_json' => $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
      ':after_json' => $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
    ]);
  }
}