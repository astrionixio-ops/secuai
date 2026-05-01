<?php
return [
  'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'secuai',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
  ],
  'jwt_secret' => 'change-me-to-a-long-random-string',
  'jwt_ttl'    => 60 * 60 * 24 * 7, // 7 days
  'upload_dir' => __DIR__ . '/../uploads',
  'public_url' => 'http://localhost:8080/uploads',
  'cors_origin' => '*',
];
