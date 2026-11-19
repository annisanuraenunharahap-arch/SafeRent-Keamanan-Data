<?php
session_start();
const DB_HOST='localhost', DB_NAME='saferent', DB_USER='root', DB_PASS='';
const PUB_KEY=__DIR__.'/keys/public.pem';

function db(){
  static $p;
  return $p ??= new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function rp($n){ return 'Rp '.number_format($n,0,',','.'); }

// Enkripsi string tunggal (RSA-OAEP)
function rsa_enc_str(string $text): string {
  $pub = file_get_contents(PUB_KEY);
  openssl_public_encrypt(substr($text, 0, 190), $enc, $pub, OPENSSL_PKCS1_OAEP_PADDING);
  return base64_encode($enc);
}

// Dekripsi string tunggal (RSA-OAEP)
function rsa_dec_str(string $enc, string $privPem): ?string {
  if (!@openssl_private_decrypt(base64_decode($enc), $dec, $privPem, OPENSSL_PKCS1_OAEP_PADDING)) return null;
  return $dec;
}

// Enkripsi tampilan dashboard terkunci (RSA murni)
function dash_encrypt(string $text): string {
  return rsa_enc_str($text);
}

// Enkripsi data pemesanan array JSON (RSA murni dengan pemecahan blok 190 byte)
function rsa_encrypt(array $data): string {
  $pub = file_get_contents(PUB_KEY);
  $chunks = str_split(json_encode($data), 190);
  $out = [];
  foreach ($chunks as $c) {
    openssl_public_encrypt($c, $enc, $pub, OPENSSL_PKCS1_OAEP_PADDING);
    $out[] = base64_encode($enc);
  }
  return implode('.', $out);
}

// Dekripsi data pemesanan array JSON (RSA murni)
function rsa_decrypt(string $enc, string $privPem): ?array {
  $parts = explode('.', $enc);
  $plain = '';
  foreach ($parts as $p) {
    if (!@openssl_private_decrypt(base64_decode($p), $dec, $privPem, OPENSSL_PKCS1_OAEP_PADDING)) return null;
    $plain .= $dec;
  }
  return json_decode($plain, true);
}

$root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$dir  = str_replace('\\', '/', __DIR__);
define('CSS', '<link rel="stylesheet" href="'.substr($dir, strlen($root)).'/assets/style.css">');