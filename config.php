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

// ===== Kriptografi hybrid: AES-256-GCM (data) + RSA-2048 OAEP (pembungkus kunci) =====
function hybrid_encrypt(array $data): array {
  $k  = random_bytes(32);   // kunci AES-256 acak per data
  $iv = random_bytes(12);
  $ct = openssl_encrypt(json_encode($data), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
  openssl_public_encrypt($k, $ek, file_get_contents(PUB_KEY), OPENSSL_PKCS1_OAEP_PADDING);
  return [base64_encode($iv.$tag.$ct), base64_encode($ek)];
}

function hybrid_decrypt($enc, $ek, $privPem){
  if(!openssl_private_decrypt(base64_decode($ek), $k, $privPem, OPENSSL_PKCS1_OAEP_PADDING)) return null;
  $r = base64_decode($enc);
  $j = openssl_decrypt(substr($r,28), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, substr($r,0,12), substr($r,12,16));
  return $j === false ? null : json_decode($j, true);
}

$root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$dir  = str_replace('\\', '/', __DIR__);
define('CSS', '<link rel="stylesheet" href="'.substr($dir, strlen($root)).'/assets/style.css">');