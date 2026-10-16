# Aplikasi Manajemen Stok Barang

Aplikasi web untuk mengelola stok barang. Setiap perubahan jumlah stok otomatis tercatat sebagai mutasi masuk/keluar, lengkap dengan siapa yang mengubahnya. Laporan bulanan bisa diunduh dalam bentuk PDF.

<!-- Tambahkan screenshot di sini, misalnya: ![Halaman data barang](docs/screenshot-barang.png) -->

## Fitur

- **Login dengan 3 peran**, dicek di server:

  | Peran | Lihat barang & mutasi | Tambah / edit barang | Hapus barang | Laporan PDF |
  |---|:---:|:---:|:---:|:---:|
  | admin | ✓ | ✓ | ✓ | ✓ |
  | karyawan | ✓ | ✓ | – | – |
  | owner | ✓ | – | – | ✓ |

- **Data barang**: tambah, edit, dan hapus lewat form modal, cari berdasarkan nama atau kode, harga ditampilkan dalam format Rupiah.
- **Mutasi stok otomatis**: saat jumlah stok diubah, aplikasi menghitung selisih dengan stok lama dan mencatatnya sebagai barang masuk (IN) atau keluar (OUT), beserta user dan waktunya.
- **Riwayat mutasi**: daftar semua pergerakan stok, terbaru di atas.
- **Soft delete**: barang yang dihapus hanya disembunyikan, sehingga riwayat mutasi dan laporan bulan lalu tetap utuh untuk audit. Database juga menolak penghapusan permanen barang yang punya riwayat (`ON DELETE RESTRICT`).
- **Laporan bulanan PDF**: total barang masuk dan keluar per barang untuk bulan yang dipilih, dibuat dengan [Dompdf](https://github.com/dompdf/dompdf).

## Teknologi

- PHP (tanpa framework) dengan PDO dan prepared statement
- MySQL / MariaDB
- Bootstrap 4 dan jQuery
- Composer (Dompdf)

## Keamanan

- Password disimpan sebagai hash (`password_hash` / `password_verify`).
- Semua query yang menerima input user memakai prepared statement.
- Output di-escape untuk mencegah XSS.
- Hak akses tiap peran dicek di server, bukan hanya disembunyikan di tampilan.
- ID session dibuat ulang setelah login.
- Kredensial database disimpan di `config.php`, yang tidak ikut di-commit.

## Cara menjalankan

Kebutuhan: PHP 7.4+ (ekstensi `pdo_mysql`, `mbstring`, `dom`, `gd`), MySQL/MariaDB, Composer.

```bash
git clone https://github.com/salman-yulio/stok-app.git
cd stok-app
composer install
cp config.example.php config.php   # lalu isi kredensial MySQL Anda
mysql -u root -p < schema.sql
php -S localhost:8000
```

Buka http://localhost:8000/login.php dan masuk dengan salah satu akun demo. Password semua akun demo adalah `password123`.

| Username | Peran |
|---|---|
| `admin` | admin |
| `owner` | owner |
| `karyawan` | karyawan |

Untuk membuat hash password baru: `php hash_pass.php passwordBaru`.

## Struktur

| File | Fungsi |
|---|---|
| `login.php`, `logout.php` | Autentikasi |
| `index.php` | Data barang (CRUD dan pencarian) serta pencatatan mutasi |
| `mutasi.php` | Riwayat mutasi dan pilihan bulan laporan |
| `export_pdf.php` | Membuat laporan PDF bulanan |
| `db.php`, `config.example.php` | Koneksi database |
| `schema.sql` | Skema tabel dan akun demo |
