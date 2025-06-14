<?php
// db.php
$host   = 'localhost';
$dbname = 'stok_db';
$user   = 'root';      // ganti sesuai user MySQL Anda
$pass   = '';          // ganti sesuai password MySQL Anda

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    // Set error mode ke Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
