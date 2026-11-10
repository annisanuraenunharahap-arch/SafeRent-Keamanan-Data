-- Database SafeRent
-- Sistem Informasi Rental Kendaraan dengan Enkripsi AES-256 + RSA

CREATE DATABASE IF NOT EXISTS saferent;
USE saferent;

-- Tabel kendaraan (data umum, tidak dienkripsi)
CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100),
  jenis VARCHAR(30),
  harga_per_hari INT,
  tersedia TINYINT DEFAULT 1
);

-- Tabel pemesanan
CREATE TABLE IF NOT EXISTS rentals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(64) NULL UNIQUE COMMENT 'hash SHA-256 dari kode pesanan (untuk cek status)',
  vehicle_id INT,
  tgl_mulai DATE,
  tgl_selesai DATE,
  total INT,
  data_enc TEXT NOT NULL COMMENT 'AES-256-GCM: kode, nama, NIK, HP, no SIM',
  key_enc TEXT NOT NULL COMMENT 'kunci AES dibungkus RSA public key',
  status ENUM('Menunggu','Disetujui','Selesai','Ditolak') DEFAULT 'Menunggu',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Data awal kendaraan
INSERT INTO vehicles (nama, jenis, harga_per_hari) VALUES
('Toyota Avanza', 'Mobil', 350000),
('Honda Brio', 'Mobil', 300000),
('Honda Vario 160', 'Motor', 100000),
('Yamaha NMAX', 'Motor', 150000);