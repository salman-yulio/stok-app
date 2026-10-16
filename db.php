<?php
// db.php
// Kredensial dibaca dari config.php (tidak di-commit). Salin config.example.php menjadi config.php.
$config = file_exists(__DIR__ . '/config.php')
    ? require __DIR__ . '/config.php'
    : require __DIR__ . '/config.example.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass']
    );
    // Set error mode ke Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Koneksi database gagal: " . $e->getMessage());
    die("Koneksi database gagal. Periksa konfigurasi di config.php.");
}
