<?php
date_default_timezone_set('Asia/Jakarta');

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
