<?php
class Resp {
  public static function json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
  }
  public static function error(int $status, string $msg, array $extra = []): void {
    self::json(array_merge(['error' => $msg], $extra), $status);
  }
}

function input_json(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}
function param(string $key, $default=null) {
  return $_GET[$key] ?? $default;
}
