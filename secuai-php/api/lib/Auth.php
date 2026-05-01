<?php
class Auth {
  public static array $cfg;
  public static ?array $user = null;

  public static function load(array $cfg): void { self::$cfg = $cfg; }

  public static function require(): array {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$hdr && function_exists('apache_request_headers')) {
      $h = apache_request_headers();
      $hdr = $h['Authorization'] ?? $h['authorization'] ?? '';
    }
    if (!preg_match('/Bearer\s+(.+)/i', $hdr, $m)) Resp::error(401,'missing token');
    $payload = Jwt::decode($m[1], self::$cfg['jwt_secret']);
    if (!$payload || empty($payload['sub'])) Resp::error(401,'invalid token');
    self::$user = $payload;
    return $payload;
  }

  public static function userId(): string { return self::$user['sub']; }

  public static function tenantRole(string $tenantId): ?string {
    $stmt = Db::$pdo->prepare("SELECT role FROM tenant_members WHERE tenant_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$tenantId, self::userId()]);
    $r = $stmt->fetch();
    return $r ? $r['role'] : null;
  }

  public static function isMember(string $tenantId): bool {
    return self::tenantRole($tenantId) !== null;
  }

  public static function hasTenantRole(string $tenantId, array $roles): bool {
    $r = self::tenantRole($tenantId);
    return $r !== null && in_array($r, $roles, true);
  }

  public static function requireMember(string $tenantId): void {
    if (!self::isMember($tenantId)) Resp::error(403,'not a tenant member');
  }
  public static function requireRole(string $tenantId, array $roles): void {
    if (!self::hasTenantRole($tenantId, $roles)) Resp::error(403,'insufficient role');
  }
}
