<?php
class Db {
  public static ?PDO $pdo = null;
  public static function init(array $cfg): PDO {
    if (self::$pdo) return self::$pdo;
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";
    self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return self::$pdo;
  }
}

function uuidv4(): string {
  $d = random_bytes(16);
  $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
  $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
