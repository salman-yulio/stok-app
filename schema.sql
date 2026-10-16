-- Skema database Aplikasi Stok Barang
-- Import: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS stok_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stok_db;

CREATE TABLE IF NOT EXISTS users (
    id_user       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nama_lengkap  VARCHAR(100) NOT NULL,
    role          ENUM('admin', 'owner', 'karyawan') NOT NULL DEFAULT 'karyawan'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS barang (
    id_barang   INT AUTO_INCREMENT PRIMARY KEY,
    nama_barang VARCHAR(100)  NOT NULL,
    kategori    VARCHAR(50)   NOT NULL,
    kode_barang VARCHAR(30)   NOT NULL UNIQUE,
    harga       DECIMAL(15,2) NOT NULL,
    satuan      VARCHAR(20)   NOT NULL,
    jumlah      INT           NOT NULL DEFAULT 0,
    aktif       TINYINT(1)    NOT NULL DEFAULT 1   -- 0 = sudah dihapus (soft delete)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mutasi (
    id_mutasi INT AUTO_INCREMENT PRIMARY KEY,
    id_barang INT NOT NULL,
    id_user   INT NOT NULL,
    in_qty    INT NOT NULL DEFAULT 0,
    out_qty   INT NOT NULL DEFAULT 0,
    tanggal   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- RESTRICT: barang yang punya riwayat mutasi tidak bisa dihapus permanen
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang) ON DELETE RESTRICT,
    FOREIGN KEY (id_user)   REFERENCES users(id_user)
) ENGINE=InnoDB;

-- Akun demo (password semua: password123). Ganti password sebelum dipakai sungguhan,
-- buat hash baru dengan: php hash_pass.php passwordBaru
INSERT IGNORE INTO users (username, password_hash, nama_lengkap, role) VALUES
('admin',    '$2y$10$2QUt9n/xyBRSC0jS7fB7TOYwcM15SgQdGW0oYbdw8v5RNKgtRxg9G', 'Administrator', 'admin'),
('owner',    '$2y$10$2QUt9n/xyBRSC0jS7fB7TOYwcM15SgQdGW0oYbdw8v5RNKgtRxg9G', 'Pemilik Toko',  'owner'),
('karyawan', '$2y$10$2QUt9n/xyBRSC0jS7fB7TOYwcM15SgQdGW0oYbdw8v5RNKgtRxg9G', 'Karyawan Gudang', 'karyawan');
