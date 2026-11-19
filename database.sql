-- Database SafeRent 

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

-- Tabel pemesanan (dienkripsi RSA)
CREATE TABLE IF NOT EXISTS rentals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id TEXT NOT NULL COMMENT 'RSA-OAEP: ID kendaraan',
  tgl_mulai TEXT NOT NULL COMMENT 'RSA-OAEP: tanggal mulai',
  tgl_selesai TEXT NOT NULL COMMENT 'RSA-OAEP: tanggal selesai',
  total TEXT NOT NULL COMMENT 'RSA-OAEP: total biaya',
  data_enc TEXT NOT NULL COMMENT 'RSA-OAEP: nama, NIK, HP, no SIM',
  created_at TEXT NOT NULL COMMENT 'RSA-OAEP: waktu pemesanan'
);

-- Data awal kendaraan
INSERT INTO vehicles (nama, jenis, harga_per_hari) VALUES
('Toyota Avanza', 'Mobil', 350000),
('Honda Brio', 'Mobil', 300000),
('Honda Vario 160', 'Motor', 100000),
('Yamaha NMAX', 'Motor', 150000);