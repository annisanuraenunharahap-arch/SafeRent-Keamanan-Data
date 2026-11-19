<?php require 'config.php';

// ===== ROUTER SEDERHANA: home | login | admin | logout =====
$page = $_GET['page'] ?? 'home';
if(!in_array($page, ['home','login','admin','logout'])) $page = 'home';

// ---------- LOGOUT ----------
if($page==='logout'){
  session_destroy();
  header('Location: index.php?page=login'); exit;
}

// ---------- LOGIN ADMIN (proses) ----------
if($page==='login' && $_SERVER['REQUEST_METHOD']==='POST'){
  $pem = !empty($_FILES['key']['tmp_name']) ? file_get_contents($_FILES['key']['tmp_name']) : trim($_POST['pem'] ?? '');
  $valid = '';
  $pk = $pem !== '' ? @openssl_pkey_get_private($pem) : false;
  if($pk){
    // Cek apakah private key cocok dengan public key server (challenge-response RSA)
    $ch = random_bytes(32);
    openssl_public_encrypt($ch, $x, file_get_contents(PUB_KEY), OPENSSL_PKCS1_OAEP_PADDING);
    if(@openssl_private_decrypt($x, $d, $pk, OPENSSL_PKCS1_OAEP_PADDING) && hash_equals($ch, $d)){
      $valid = $pem;
    }
  }
  session_regenerate_id(true);
  $_SESSION['login'] = true;   // tetap boleh masuk ke halaman admin
  $_SESSION['pk']    = $valid; // kosong kalau key salah / tidak diisi -> data tidak terdekripsi
  header('Location: index.php?page=admin'); exit;
}

// ---------- HALAMAN ADMIN (proses) ----------
if($page==='admin'){
  if(empty($_SESSION['login'])){ header('Location: index.php?page=login'); exit; }
  $pk = $_SESSION['pk'] ?? '';
  $locked = ($pk === '');
  $pdo = db();

  if(!$locked){
    if(isset($_POST['nama_k']))
      $pdo->prepare('INSERT INTO vehicles(nama,jenis,harga_per_hari) VALUES(?,?,?)')
          ->execute([$_POST['nama_k'], $_POST['jenis'], (int)$_POST['harga']]);
  }

  $vMap = $pdo->query('SELECT id, nama FROM vehicles')->fetchAll(PDO::FETCH_KEY_PAIR);
  $rows = $pdo->query('SELECT * FROM rentals ORDER BY id DESC')->fetchAll();
}

// ---------- LANDING PAGE (proses) ----------
$msg='';
if($page==='home'){
  if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
      $v = db()->prepare('SELECT * FROM vehicles WHERE id=? AND tersedia=1');
      $v->execute([$_POST['vehicle']]);
      $v = $v->fetch();
      $a = new DateTime($_POST['mulai']);
      $b = new DateTime($_POST['selesai']);
      if(!$v || $b < $a) throw new Exception('Data tidak valid.');
      if(!preg_match('/^\d{16}$/', $_POST['nik'])) throw new Exception('NIK harus 16 digit.');

      $days = max(1, $a->diff($b)->days);
      $totalHarga = $days * $v['harga_per_hari'];

      // Seluruh data pesanan dienkripsi RSA sebelum masuk database
      $enc = rsa_encrypt([
        'nama'=>$_POST['nama'], 'nik'=>$_POST['nik'],
        'hp'=>$_POST['hp'], 'sim'=>$_POST['sim']
      ]);
      db()->prepare('INSERT INTO rentals(vehicle_id,tgl_mulai,tgl_selesai,total,data_enc,created_at) VALUES(?,?,?,?,?,?)')
        ->execute([
          rsa_enc_str((string)$v['id']),
          rsa_enc_str($_POST['mulai']),
          rsa_enc_str($_POST['selesai']),
          rsa_enc_str((string)$totalHarga),
          $enc,
          rsa_enc_str(date('Y-m-d H:i:s'))
        ]);

      $msg='<div class="ok">Pemesanan berhasil! Total '.rp($totalHarga).' ('.$days.' hari).</div>';
    }catch(Exception $x){
      $msg='<div class="err">'.e($x->getMessage()).'</div>';
    }
  }
  $vs = db()->query('SELECT * FROM vehicles WHERE tersedia=1')->fetchAll();
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafeRent</title>
<style>
@import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap");

:root{--bg:#f5f7fb;--card:#ffffff;--line:#e2e8f0;--txt:#1e293b;--mut:#64748b;--main:#2563eb;--main-dark:#1d4ed8;--soft:#eff4ff}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:var(--txt);min-height:100vh;background:var(--bg)}

/* Header & footer */
header{position:sticky;top:0;z-index:10;padding:14px 6%;background:#fff;border-bottom:1px solid var(--line);font-size:18px}
header b{color:var(--main);font-weight:800}
header a{color:var(--mut);margin-left:22px;text-decoration:none;font-size:15px;font-weight:600;transition:.2s}
header a:hover{color:var(--main)}
footer{text-align:center;padding:24px 5%;color:var(--mut);font-size:14px;border-top:1px solid var(--line);margin-top:40px;background:#fff}

/* Hero landing page */
.hero{max-width:1050px;margin:28px auto 0;padding:56px 32px;text-align:center;color:#fff;background:var(--main);border-radius:16px}
.hero h1{font-size:clamp(28px,5vw,42px);font-weight:800;margin:0 0 10px;line-height:1.2}
.hero p{font-size:16px;max-width:520px;margin:0 auto 24px;opacity:.9}

/* Layout & kartu */
.wrap{max-width:1050px;margin:30px auto;padding:0 18px}
h2{font-size:24px;font-weight:800;margin:36px 0 16px;color:var(--txt)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px;transition:.2s}
.grid .card:hover{transform:translateY(-3px);border-color:var(--main);box-shadow:0 8px 24px rgba(37,99,235,.12)}
.card h3{margin:0 0 4px;font-size:18px}
.card p{color:var(--mut);margin:0 0 10px}
.card b{color:var(--main);font-size:17px}

/* Form */
input,select,textarea{width:100%;padding:11px 14px;margin:6px 0 14px;color:var(--txt);font:inherit;background:#fff;border:1px solid var(--line);border-radius:10px;outline:none;transition:.2s}
input:focus,select:focus,textarea:focus{border-color:var(--main);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
input::placeholder,textarea::placeholder{color:#a0aabb}
input[type=file]{padding:9px}
input[type=file]::file-selector-button{background:var(--main);color:#fff;border:0;padding:7px 14px;border-radius:8px;margin-right:10px;cursor:pointer}
textarea{font-family:Consolas,monospace;font-size:12px}

/* Tombol */
button,.btn{display:inline-block;width:auto;padding:11px 24px;border:0;border-radius:10px;cursor:pointer;font:inherit;font-weight:700;color:#fff;text-decoration:none;background:var(--main);transition:.2s}
button:hover,.btn:hover{background:var(--main-dark)}
.hero .btn{background:#fff!important;color:var(--main)!important}
.hero .btn:hover{background:var(--soft)!important}

/* Tabel admin */
table{width:100%;border-collapse:separate;border-spacing:0;font-size:14px;background:#fff;border:1px solid var(--line);border-radius:12px;overflow:hidden}
th{padding:13px 12px;text-align:left;color:var(--main);font-weight:700;background:var(--soft);white-space:nowrap}
td{padding:12px;border-top:1px solid var(--line);vertical-align:top}
tr:hover td{background:#f8fafc}
code{font-family:Consolas,monospace;font-size:11px;word-break:break-all;color:#be185d}

/* Notifikasi */
.ok,.err{padding:13px 18px;border-radius:12px;margin:0 0 18px;font-size:15px}
.ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#047857}
.err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
</style>
</head>
<body>

<?php if($page==='login'): ?>
<!-- ===================== HALAMAN LOGIN ADMIN ===================== -->
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px">
  <form method="post" enctype="multipart/form-data" class="card" style="width:100%;max-width:460px">
    <h2 style="text-align:center;font-size:34px;margin:6px 0 26px">🔐 Login Admin</h2>
    <input type="file" name="key" accept=".pem">
    <p style="text-align:center">atau tempel isi key:</p>
    <textarea name="pem" rows="6"></textarea>
    <div style="text-align:center"><button>Masuk</button></div>
  </form>
</div>

<?php elseif($page==='admin'): ?>
<!-- ===================== HALAMAN ADMIN ===================== -->
<header>
  <b><?= $locked ? e(substr(dash_encrypt('Dashboard Admin SafeRent'), 0, 36)) : 'Dashboard Admin SafeRent' ?></b>
  <span style="float:right"><a href="index.php?page=logout">Logout</a></span>
</header>

<div class="wrap">
  <?php if($locked): ?>
    <h2 style="margin-top:0"><code><?= e(substr(dash_encrypt('Data Pemesanan'), 0, 44)) ?></code></h2>
    <div style="overflow-x:auto">
      <table>
        <tr>
          <?php foreach(['Penyewa','NIK','HP','SIM','Kendaraan','Periode','Total'] as $col): ?>
            <th><code><?= e(substr(dash_encrypt($col), 0, 18)) ?></code></th>
          <?php endforeach; ?>
        </tr>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><code><?= e(substr($r['data_enc'], 0, 20)) ?></code></td>
          <td><code><?= e(substr($r['data_enc'], 20, 20)) ?></code></td>
          <td><code><?= e(substr($r['data_enc'], 40, 20)) ?></code></td>
          <td><code><?= e(substr($r['data_enc'], 60, 20)) ?></code></td>
          <td><code><?= e(substr($r['vehicle_id'], 0, 20)) ?></code></td>
          <td><code><?= e(substr($r['tgl_mulai'], 0, 22)) ?></code></td>
          <td><code><?= e(substr($r['total'], 0, 18)) ?></code></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <h2><code><?= e(substr(dash_encrypt('Tambah Kendaraan'), 0, 44)) ?></code></h2>
    <div class="card">
      <p><code><?= e(dash_encrypt('Form Tambah Kendaraan Terenkripsi')) ?></code></p>
      <p><code><?= e(dash_encrypt('Nama Kendaraan | Jenis | Harga Per Hari')) ?></code></p>
    </div>

  <?php else: ?>
    <h2 style="margin-top:0">Data Pemesanan</h2>
    <div style="overflow-x:auto">
    <table>
      <tr><th>Penyewa</th><th>NIK</th><th>HP</th><th>SIM</th><th>Kendaraan</th><th>Periode</th><th>Total</th></tr>
      <?php foreach($rows as $r):
        $d = @rsa_decrypt($r['data_enc'], $pk) ?: [];
        $vid = @rsa_dec_str($r['vehicle_id'], $pk);
        $kendaraan = $vMap[$vid] ?? '-';
        $mulai = @rsa_dec_str($r['tgl_mulai'], $pk) ?? '-';
        $selesai = @rsa_dec_str($r['tgl_selesai'], $pk) ?? '-';
        $total = (int)(@rsa_dec_str($r['total'], $pk) ?? 0);
      ?>
      <tr>
        <td><?=e($d['nama'] ?? '-')?></td>
        <td><?=e($d['nik'] ?? '-')?></td>
        <td><?=e($d['hp'] ?? '-')?></td>
        <td><?=e($d['sim'] ?? '-')?></td>
        <td><?=e($kendaraan)?></td>
        <td><?=e($mulai)?> s/d <?=e($selesai)?></td>
        <td><?=rp($total)?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>

    <h2>Tambah Kendaraan</h2>
    <form method="post" class="card">
      <input name="nama_k" placeholder="Nama kendaraan" required>
      <select name="jenis"><option>Mobil</option><option>Motor</option></select>
      <input type="number" name="harga" placeholder="Harga per hari" required>
      <button>Tambah</button>
    </form>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ===================== LANDING PAGE ===================== -->
<header>
  <b>SafeRent 🚗</b>
  <span style="float:right">
    <a href="#armada">Armada</a><a href="#pesan">Pesan</a><a href="index.php?page=login">Admin</a>
  </span>
</header>

<div class="hero">
  <h1>SafeRent 🚗</h1>
  <p>Rental Kendaraan Aman dan Terjangkau</p>
  <a class="btn" href="#pesan">Pesan Sekarang</a>
</div>

<div class="wrap" id="armada">
  <h2>Armada Kami</h2>
  <div class="grid">
    <?php foreach($vs as $v): ?>
      <div class="card">
        <h3><?=e($v['nama'])?></h3>
        <p><?=e($v['jenis'])?></p>
        <b><?=rp($v['harga_per_hari'])?></b>/hari
      </div>
    <?php endforeach; ?>
  </div>

  <h2 id="pesan">Form Pemesanan</h2>
  <?=$msg?>
  <form method="post" class="card">
    <select name="vehicle" required>
      <?php foreach($vs as $v): ?>
        <option value="<?=$v['id']?>"><?=e($v['nama'])?> - <?=rp($v['harga_per_hari'])?></option>
      <?php endforeach; ?>
    </select>
    <input name="nama" placeholder="Nama lengkap" required>
    <input name="nik" placeholder="NIK (16 digit)" required maxlength="16">
    <input name="hp" placeholder="No. HP" required>
    <input name="sim" placeholder="No. SIM" required>
    Tanggal mulai<input type="date" name="mulai" required>
    Tanggal selesai<input type="date" name="selesai" required>
    <button>Kirim Pemesanan</button>
  </form>
</div>

<footer>© 2026 SafeRent - UTS Keamanan Data</footer>
<?php endif; ?>

</body>
</html>