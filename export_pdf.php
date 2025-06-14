<?php
require 'db.php';
require 'vendor/autoload.php'; // jika pakai Composer

use Dompdf\Dompdf;

if (!isset($_GET['bulan'])) {
    die("Bulan belum dipilih.");
}

$bulan = $_GET['bulan'];

$stmt = $pdo->prepare("
    SELECT 
        b.kode_barang, 
        b.nama_barang,
        SUM(m.in_qty) AS total_in,
        SUM(m.out_qty) AS total_out
    FROM mutasi m
    JOIN barang b ON m.id_barang = b.id_barang
    WHERE DATE_FORMAT(m.tanggal, '%Y-%m') = :bulan
    GROUP BY b.id_barang
");
$stmt->execute(['bulan' => $bulan]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format HTML untuk PDF
$html = '<h3 style="text-align:center;">Laporan Mutasi Bulan ' . date('F Y', strtotime($bulan)) . '</h3>';
$html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th>Masuk</th>
            <th>Keluar</th>
        </tr>
    </thead>
    <tbody>';

foreach ($data as $row) {
    $html .= '<tr>
        <td>' . $row['kode_barang'] . '</td>
        <td>' . $row['nama_barang'] . '</td>
        <td align="center">' . $row['total_in'] . '</td>
        <td align="center">' . $row['total_out'] . '</td>
    </tr>';
}
$html .= '</tbody></table>';

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('laporan_mutasi_' . $bulan . '.pdf', ["Attachment" => 1]);
exit;
