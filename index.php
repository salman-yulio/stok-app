<?php
// index.php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';

// Ambil role user
$session_id  = $_SESSION['id_user'];
$session_nama= $_SESSION['nama_lengkap'];
$session_role= $_SESSION['role'];

// Hak akses (dicek di server, bukan hanya disembunyikan di tampilan)
$boleh_ubah  = $session_role != 'owner';                                  // tambah & edit
$boleh_hapus = $session_role != 'owner' && $session_role != 'karyawan';   // hapus
$ubah_ditolak  = (isset($_POST['add_barang']) || isset($_POST['edit_barang'])) && !$boleh_ubah;
$hapus_ditolak = isset($_GET['delete']) && !$boleh_hapus;
if ($ubah_ditolak || $hapus_ditolak) {
    $_SESSION['msg_error'] = "Anda tidak punya akses untuk aksi ini.";
    header("Location: index.php");
    exit;
}

$pesan_kode_duplikat = "Kode barang sudah dipakai (termasuk oleh barang yang sudah dihapus).";

// Proses Tambah Barang
if (isset($_POST['add_barang'])) {
    $nama      = trim($_POST['nama_barang']);
    $kategori  = trim($_POST['kategori']);
    $kode      = trim($_POST['kode_barang']);
    $harga     = floatval($_POST['harga']);
    $satuan    = trim($_POST['satuan']);
    $jumlah    = intval($_POST['jumlah']);

    // Validasi sederhana
    if ($nama == '' || $kategori == '' || $kode == '' || $harga <= 0 || $satuan == '' ) {
        $_SESSION['msg_error'] = "Semua field wajib diisi dan harga > 0.";
    } else {
        // Insert barang baru
        $stmt = $pdo->prepare("INSERT INTO barang (nama_barang, kategori, kode_barang, harga, satuan, jumlah)
                              VALUES (:nama, :kategori, :kode, :harga, :satuan, :jumlah)");
        try {
            $stmt->execute([
                'nama'     => $nama,
                'kategori' => $kategori,
                'kode'     => $kode,
                'harga'    => $harga,
                'satuan'   => $satuan,
                'jumlah'   => $jumlah
            ]);
        } catch (PDOException $e) {
            // 23000 = kode_barang sudah ada (UNIQUE)
            if ($e->getCode() != '23000') throw $e;
            $_SESSION['msg_error'] = $pesan_kode_duplikat;
            header("Location: index.php");
            exit;
        }
        // Ambil id_barang yang baru
        $lastId  = $pdo->lastInsertId();

        // Catat mutasi IN (jika jumlah > 0)
        if ($jumlah > 0) {
            $mstmt = $pdo->prepare("INSERT INTO mutasi (id_barang, id_user, in_qty, out_qty) 
                                    VALUES (:idb, :idu, :in, 0)");
            $mstmt->execute([
                'idb' => $lastId,
                'idu' => $session_id,
                'in'  => $jumlah
            ]);
        }
        $_SESSION['msg_success'] = "Barang berhasil ditambahkan.";
    }
    header("Location: index.php");
    exit;
}

// Proses Edit Barang
if (isset($_POST['edit_barang'])) {
    $id        = intval($_POST['id_barang']);
    $nama      = trim($_POST['nama_barang']);
    $kategori  = trim($_POST['kategori']);
    $kode      = trim($_POST['kode_barang']);
    $harga     = floatval($_POST['harga']);
    $satuan    = trim($_POST['satuan']);
    $jumlahBaru= intval($_POST['jumlah']);
    // Ambil data lama (untuk menghitung selisih). Barang yang sudah dihapus tidak bisa diedit.
    $oldStmt = $pdo->prepare("SELECT jumlah FROM barang WHERE id_barang = :id AND aktif = 1");
    $oldStmt->execute(['id' => $id]);
    $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
    $oldJumlah = $oldData ? intval($oldData['jumlah']) : 0;

    if (!$oldData) {
        $_SESSION['msg_error'] = "Barang tidak ditemukan.";
    } elseif ($nama == '' || $kategori == '' || $kode == '' || $harga <= 0 || $satuan == '') {
        $_SESSION['msg_error'] = "Semua field wajib diisi dan harga > 0.";
    } else {
        // Update data barang
        $ustmt = $pdo->prepare("UPDATE barang SET nama_barang = :nama, kategori = :kategori, 
                                    kode_barang = :kode, harga = :harga, satuan = :satuan, jumlah = :jumlah 
                                WHERE id_barang = :id AND aktif = 1");
        try {
            $ustmt->execute([
                'nama'     => $nama,
                'kategori' => $kategori,
                'kode'     => $kode,
                'harga'    => $harga,
                'satuan'   => $satuan,
                'jumlah'   => $jumlahBaru,
                'id'       => $id
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() != '23000') throw $e;
            $_SESSION['msg_error'] = $pesan_kode_duplikat;
            header("Location: index.php");
            exit;
        }

        // Hitung selisih
        $selisih = $jumlahBaru - $oldJumlah;
        if ($selisih > 0) {
            // Catat mutasi IN
            $mstmt = $pdo->prepare("INSERT INTO mutasi (id_barang, id_user, in_qty, out_qty) 
                                    VALUES (:idb, :idu, :in, 0)");
            $mstmt->execute([
                'idb' => $id,
                'idu' => $session_id,
                'in'  => $selisih
            ]);
        } elseif ($selisih < 0) {
            // Catat mutasi OUT
            $mstmt = $pdo->prepare("INSERT INTO mutasi (id_barang, id_user, in_qty, out_qty) 
                                    VALUES (:idb, :idu, 0, :out)");
            $mstmt->execute([
                'idb'  => $id,
                'idu'  => $session_id,
                'out'  => abs($selisih)
            ]);
        }
        $_SESSION['msg_success'] = "Data barang berhasil diperbarui.";
    }
    header("Location: index.php");
    exit;
}

// Proses Delete Barang
if (isset($_GET['delete'])) {
    $idDel = intval($_GET['delete']);
    // Soft delete: barang hanya disembunyikan (aktif = 0) supaya riwayat mutasi & laporan tetap utuh
    $dstmt = $pdo->prepare("UPDATE barang SET aktif = 0 WHERE id_barang = :id");
    $dstmt->execute(['id' => $idDel]);
    $_SESSION['msg_success'] = "Data barang berhasil dihapus.";
    header("Location: index.php");
    exit;
}

// Query data barang (dengan pencarian)
$key = '';
if (isset($_GET['keyword'])) {
    $key = trim($_GET['keyword']);
    $qstmt = $pdo->prepare("SELECT * FROM barang
                            WHERE aktif = 1 AND (nama_barang LIKE :kw OR kode_barang LIKE :kw)
                            ORDER BY nama_barang ASC");
    $qstmt->execute(['kw' => "%$key%"]);
} else {
    $qstmt = $pdo->query("SELECT * FROM barang WHERE aktif = 1 ORDER BY nama_barang ASC");
}
$daftar_barang = $qstmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Home - Manajemen Stok Barang</title>
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
        .sidebar a:hover, .sidebar a.active { background-color: #e9ecef; }
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
        <span class="navbar-text text-white mr-3"><?= htmlentities($session_nama) ?> (<?= htmlentities($session_role) ?>)</span>
        <a class="btn btn-outline-light btn-sm" href="logout.php" onclick="return confirm('Yakin ingin logout?')">Logout</a>
    </div>
</nav>

<!-- Sidebar -->
<div class="sidebar">
    <a href="index.php" class="active">Home</a>
    <a href="mutasi.php">Mutasi</a>
</div>

<!-- Konten Utama -->
<div class="content">
    <h4>Data Barang</h4>

    <!-- Pesan sukses / error -->
    <?php if (isset($_SESSION['msg_success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['msg_success']; unset($_SESSION['msg_success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['msg_error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['msg_error']; unset($_SESSION['msg_error']); ?></div>
    <?php endif; ?>

    <!-- Pencarian -->
    <form class="form-inline mb-3" method="GET" action="index.php">
        <input type="text" name="keyword" class="form-control mr-2" placeholder="Cari nama atau kode" value="<?= htmlentities($key) ?>">
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if ($_SESSION['role'] != 'owner'): ?>
            <button type="button" class="btn btn-success ml-auto" data-toggle="modal" data-target="#modalAdd">+ Tambah Data</button>
        <?php endif; ?>
    </form>

    <!-- Tabel Barang -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="thead-light">
                <tr>
                    <th>No.</th>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Kode Barang</th>
                    <th>Harga</th>
                    <th>Satuan</th>
                    <th>Jumlah</th>
                    <?php if ($_SESSION['role'] != 'owner'): ?>
                        <th width="130px">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (count($daftar_barang) > 0): ?>
                <?php $no = 1; foreach ($daftar_barang as $row): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlentities($row['nama_barang']); ?></td>
                        <td><?= htmlentities($row['kategori']); ?></td>
                        <td><?= htmlentities($row['kode_barang']); ?></td>
                        <td class="text-right">Rp <?= number_format($row['harga'],0,',','.'); ?></td>
                        <td><?= htmlentities($row['satuan']); ?></td>
                        <td class="text-center"><?= $row['jumlah']; ?></td>
                        <?php if ($_SESSION['role'] != 'owner'): ?>
                        <td class="text-center">
                            <button class="btn btn-warning btn-sm" data-toggle="modal"
                                    data-target="#modalEdit"
                                    data-id="<?= $row['id_barang']; ?>"
                                    data-nama="<?= htmlentities($row['nama_barang']); ?>"
                                    data-kategori="<?= htmlentities($row['kategori']); ?>"
                                    data-kode="<?= htmlentities($row['kode_barang']); ?>"
                                    data-harga="<?= $row['harga']; ?>"
                                    data-satuan="<?= htmlentities($row['satuan']); ?>"
                                    data-jumlah="<?= $row['jumlah']; ?>">
                                Edit
                            </button>
                        <?php if ($_SESSION['role'] != 'karyawan'): ?>
                            <a href="index.php?delete=<?= $row['id_barang']; ?>"
                               onclick="return confirm('Anda yakin ingin menghapus <?= htmlentities($row['nama_barang']); ?>?');"
                               class="btn btn-danger btn-sm">
                                Delete
                            </a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center">Data kosong.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Barang -->
<div class="modal fade" id="modalAdd" tabindex="-1" role="dialog" aria-labelledby="modalAddLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="index.php">
      <input type="hidden" name="add_barang" value="1">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalAddLabel">Tambah Barang Baru</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="nama_barang">Nama Barang</label>
            <input type="text" class="form-control" name="nama_barang" id="nama_barang" required>
          </div>
          <div class="form-group">
            <label for="kategori">Kategori</label>
            <input type="text" class="form-control" name="kategori" id="kategori" required>
          </div>
          <div class="form-group">
            <label for="kode_barang">Kode Barang</label>
            <input type="text" class="form-control" name="kode_barang" id="kode_barang" required>
          </div>
          <div class="form-group">
            <label for="harga">Harga (Rp)</label>
            <input type="number" step="0.01" class="form-control" name="harga" id="harga" required>
          </div>
          <div class="form-group">
            <label for="satuan">Satuan</label>
            <input type="text" class="form-control" name="satuan" id="satuan" required>
          </div>
          <div class="form-group">
            <label for="jumlah">Jumlah Stok</label>
            <input type="number" class="form-control" name="jumlah" id="jumlah" value="0" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Barang -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="modalEditLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <form method="POST" action="index.php">
      <input type="hidden" name="edit_barang" value="1">
      <input type="hidden" name="id_barang" id="edit_id">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditLabel">Edit Barang</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="edit_nama_barang">Nama Barang</label>
            <input type="text" class="form-control" name="nama_barang" id="edit_nama_barang" required>
          </div>
          <div class="form-group">
            <label for="edit_kategori">Kategori</label>
            <input type="text" class="form-control" name="kategori" id="edit_kategori" required>
          </div>
          <div class="form-group">
            <label for="edit_kode_barang">Kode Barang</label>
            <input type="text" class="form-control" name="kode_barang" id="edit_kode_barang" required>
          </div>
          <div class="form-group">
            <label for="edit_harga">Harga (Rp)</label>
            <input type="number" step="0.01" class="form-control" name="harga" id="edit_harga" required>
          </div>
          <div class="form-group">
            <label for="edit_satuan">Satuan</label>
            <input type="text" class="form-control" name="satuan" id="edit_satuan" required>
          </div>
          <div class="form-group">
            <label for="edit_jumlah">Jumlah Stok</label>
            <input type="number" class="form-control" name="jumlah" id="edit_jumlah" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-warning">Update</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Skrip JS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Ketika modal edit dibuka, isi field sesuai data yang diklik
$('#modalEdit').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    $('#edit_id').val(button.data('id'));
    $('#edit_nama_barang').val(button.data('nama'));
    $('#edit_kategori').val(button.data('kategori'));
    $('#edit_kode_barang').val(button.data('kode'));
    $('#edit_harga').val(button.data('harga'));
    $('#edit_satuan').val(button.data('satuan'));
    $('#edit_jumlah').val(button.data('jumlah'));
});
</script>
</body>
</html>
