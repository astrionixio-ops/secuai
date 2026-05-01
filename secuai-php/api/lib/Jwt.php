<?php
class Jwt {
  public static function b64(string $s): string { return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
  public static function unb64(string $s): string { return base64_decode(strtr($s,'-_','+/')); }
  public static function encode(array $payload, string $secret): string {
    $h = self::b64(json_encode(['typ'=>'JWT','alg'=>'HS256']));
    $p = self::b64(json_encode($payload));
    $sig = self::b64(hash_hmac('sha256', "$h.$p", $secret, true));
    return "$h.$p.$sig";
  }
  public static function decode(string $token, string $secret): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h,$p,$s] = $parts;
    $expected = self::b64(hash_hmac('sha256',"$h.$p",$secret,true));
    if (!hash_equals($expected,$s)) return null;
    $data = json_decode(self::unb64($p), true);
    if (!is_array($data)) return null;
    if (isset($data['exp']) && $data['exp'] < time()) return null;
    return $data;
  }
}
