<?php
// mutasi.php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

$session_nama = $_SESSION['nama_lengkap'];
$session_role = $_SESSION['role'];

// Ambil data mutasi dengan join ke barang & user
$stmt = $pdo->query("
    SELECT m.id_mutasi,
           b.kode_barang,
           b.nama_barang,
           m.in_qty,
           m.out_qty,
           m.tanggal,
           u.nama_lengkap AS petugas
    FROM mutasi m
    JOIN barang b ON m.id_barang = b.id_barang
    JOIN users u  ON m.id_user   = u.id_user
    ORDER BY m.tanggal DESC
");
$daftar_mutasi = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mutasi - Manajemen Stok Barang</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 70px; }
        .sidebar {
            min-width: 200px;
            max-width: 200px;
            background-color: #f8f9fa;
            position: fixed;
            top: 56px;
            bottom: 0;
            padding-top: 20px;
            border-right: 1px solid #dee2e6;
        }
        .sidebar a {
            display: block;
            padding: 10px 15px;
            color: #333;
        }
        .sidebar a:hover { background-color: #e9ecef; }
        .sidebar a.active { background-color: #e9ecef; }
        .content {
            margin-left: 210px;
            padding: 20px;
        }
    </style>
</head>
<body>
<!-- Navbar atas -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="#">Aplikasi Stok Barang</a>
    <div class="ml-auto">
        <span class="navbar-text text-white mr-3"><?= htmlentities($session_nama) ?> (<?= $session_role ?>)</span>
        <a class="btn btn-outline-light btn-sm" href="logout.php" onclick="return confirm('Yakin ingin logout?')">Logout</a>
    </div>
</nav>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php">Home</a>
    <a href="mutasi.php" class="active">Mutasi</a>
</div>

<!-- Konten Utama -->
<div class="content">
        <?php if ($_SESSION['role'] != 'karyawan'): ?>
      <div class="card mb-4">
    <div class="card-header">
    <form method="GET" class="form-inline mb-3">
        <h4>Generate Laporan</h4>
        </div>
    <div class="card-body">
        <label for="bulan" class="mr-2">Pilih Bulan</label>
        <input type="month" name="bulan" id="bulan" class="form-control mr-2" value="<?= isset($_GET['bulan']) ? $_GET['bulan'] : '' ?>" required>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <?php if (isset($_GET['bulan'])): ?>
            <a href="export_pdf.php?bulan=<?= $_GET['bulan'] ?>" class="btn btn-danger ml-2">Export PDF</a>
            <?php endif; ?>
        </form>
        </div>
        </div>
        <?php endif; ?>
            <h4>Data Mutasi Barang</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>No.</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>In</th>
                    <th>Out</th>
                    <th>Tanggal</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($daftar_mutasi) > 0): ?>
                <?php $no = 1; foreach ($daftar_mutasi as $m): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlentities($m['kode_barang']); ?></td>
                        <td><?= htmlentities($m['nama_barang']); ?></td>
                        <td class="text-center"><?= $m['in_qty']; ?></td>
                        <td class="text-center"><?= $m['out_qty']; ?></td>
                        <td><?= date('d-m-Y H:i', strtotime($m['tanggal'])); ?></td>
                        <td><?= htmlentities($m['petugas']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center">Belum ada data mutasi.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
