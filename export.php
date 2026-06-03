<?php
// export.php - Export Data ke CSV, PDF (via HTML), atau Print
require_once 'config/database.php';
requireLogin();

$type = $_GET['type'] ?? '';       // masuk, keluar, stok, kategori, laporan
$format = $_GET['format'] ?? '';   // csv, pdf, print
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$tgl_dari = $_GET['tgl_dari'] ?? '';
$tgl_sampai = $_GET['tgl_sampai'] ?? '';

if (!$type || !$format) {
    setFlash('error', 'Parameter export tidak lengkap.');
    redirect('laporan.php');
}

// Helper
function esc_csv($val) {
    $val = strip_tags($val);
    if (strpos($val, ',') !== false || strpos($val, '"') !== false || strpos($val, "\n") !== false) {
        $val = '"' . str_replace('"', '""', $val) . '"';
    }
    return $val;
}

function bulanIndo($m) {
    $arr = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $arr[(int)$m] ?? '';
}

// ==========================================
// DATA QUERIES
// ==========================================

if ($type === 'masuk') {
    $sql = "SELECT bm.no_transaksi, b.kode_barang, b.nama_barang, k.nama_kategori,
                   bm.jumlah, bm.harga_satuan, bm.total_harga, bm.tanggal_masuk,
                   bm.supplier, bm.keterangan, u.nama_lengkap
            FROM barang_masuk bm
            JOIN barang b ON bm.barang_id = b.id
            LEFT JOIN kategori k ON b.kategori_id = k.id
            LEFT JOIN users u ON bm.user_id = u.id
            WHERE MONTH(bm.tanggal_masuk) = ? AND YEAR(bm.tanggal_masuk) = ?";
    $params = [$bulan, $tahun];

    if ($tgl_dari && $tgl_sampai) {
        $sql = str_replace('WHERE', 'WHERE bm.tanggal_masuk BETWEEN ? AND ? AND', $sql);
        $params = [$tgl_dari, $tgl_sampai, $bulan, $tahun];
    }

    $sql .= " ORDER BY bm.tanggal_masuk DESC, bm.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $title = "Laporan Barang Masuk — " . bulanIndo($bulan) . " $tahun";
    $headers = ['No', 'No. Transaksi', 'Kode Barang', 'Nama Barang', 'Kategori', 'Jumlah', 'Harga Satuan', 'Total Harga', 'Tanggal', 'Supplier', 'Keterangan', 'User'];
    $map = function($i, $r) {
        return [
            $i,
            $r['no_transaksi'],
            $r['kode_barang'],
            $r['nama_barang'],
            $r['nama_kategori'] ?? '-',
            $r['jumlah'],
            'Rp ' . number_format($r['harga_satuan'], 0, ',', '.'),
            'Rp ' . number_format($r['total_harga'], 0, ',', '.'),
            tglPendek($r['tanggal_masuk']),
            $r['supplier'] ?: '-',
            $r['keterangan'] ?: '-',
            $r['nama_lengkap']
        ];
    };
    $summary_label = 'Total Nilai Masuk';
    $summary_value = array_sum(array_column($rows, 'total_harga'));

} elseif ($type === 'keluar') {
    $sql = "SELECT bk.no_transaksi, b.kode_barang, b.nama_barang, k.nama_kategori,
                   bk.jumlah, bk.harga_satuan, bk.total_harga, bk.tanggal_keluar,
                   bk.tujuan, bk.penerima, bk.keterangan, u.nama_lengkap
            FROM barang_keluar bk
            JOIN barang b ON bk.barang_id = b.id
            LEFT JOIN kategori k ON b.kategori_id = k.id
            LEFT JOIN users u ON bk.user_id = u.id
            WHERE MONTH(bk.tanggal_keluar) = ? AND YEAR(bk.tanggal_keluar) = ?";
    $params = [$bulan, $tahun];

    if ($tgl_dari && $tgl_sampai) {
        $sql = str_replace('WHERE', 'WHERE bk.tanggal_keluar BETWEEN ? AND ? AND', $sql);
        $params = [$tgl_dari, $tgl_sampai, $bulan, $tahun];
    }

    $sql .= " ORDER BY bk.tanggal_keluar DESC, bk.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $title = "Laporan Barang Keluar — " . bulanIndo($bulan) . " $tahun";
    $headers = ['No', 'No. Transaksi', 'Kode Barang', 'Nama Barang', 'Kategori', 'Jumlah', 'Harga Satuan', 'Total Harga', 'Tanggal', 'Tujuan', 'Penerima', 'Keterangan', 'User'];
    $map = function($i, $r) {
        return [
            $i,
            $r['no_transaksi'],
            $r['kode_barang'],
            $r['nama_barang'],
            $r['nama_kategori'] ?? '-',
            $r['jumlah'],
            'Rp ' . number_format($r['harga_satuan'], 0, ',', '.'),
            'Rp ' . number_format($r['total_harga'], 0, ',', '.'),
            tglPendek($r['tanggal_keluar']),
            $r['tujuan'] ?: '-',
            $r['penerima'] ?: '-',
            $r['keterangan'] ?: '-',
            $r['nama_lengkap']
        ];
    };
    $summary_label = 'Total Nilai Keluar';
    $summary_value = array_sum(array_column($rows, 'total_harga'));

} elseif ($type === 'stok') {
    $sql = "SELECT b.kode_barang, b.nama_barang, k.nama_kategori, b.satuan,
                   b.stok, b.stok_minimum, b.harga_beli, b.harga_jual, b.lokasi_rak,
                   (b.stok * b.harga_beli) as nilai_stok
            FROM barang b
            LEFT JOIN kategori k ON b.kategori_id = k.id
            WHERE b.aktif = 1
            ORDER BY b.nama_barang";
    $rows = $pdo->query($sql)->fetchAll();

    $title = "Laporan Data Stok — " . date('d F Y');
    $headers = ['No', 'Kode', 'Nama Barang', 'Kategori', 'Satuan', 'Stok', 'Minimum', 'Harga Beli', 'Harga Jual', 'Nilai Stok', 'Lokasi Rak'];
    $map = function($i, $r) {
        return [
            $i,
            $r['kode_barang'],
            $r['nama_barang'],
            $r['nama_kategori'] ?? '-',
            $r['satuan'],
            $r['stok'],
            $r['stok_minimum'],
            'Rp ' . number_format($r['harga_beli'], 0, ',', '.'),
            'Rp ' . number_format($r['harga_jual'], 0, ',', '.'),
            'Rp ' . number_format($r['nilai_stok'], 0, ',', '.'),
            $r['lokasi_rak'] ?: '-'
        ];
    };
    $summary_label = 'Total Nilai Stok';
    $summary_value = array_sum(array_column($rows, 'nilai_stok'));

} elseif ($type === 'laporan') {
    // Full summary report
    $stats = [];
    $stats['total_barang'] = $pdo->query("SELECT COUNT(*) FROM barang WHERE aktif = 1")->fetchColumn();
    $stats['total_stok'] = $pdo->query("SELECT COALESCE(SUM(stok),0) FROM barang WHERE aktif = 1")->fetchColumn();
    $stats['nilai_stok'] = $pdo->query("SELECT COALESCE(SUM(stok*harga_beli),0) FROM barang WHERE aktif = 1")->fetchColumn();
    $stats['stok_menipis'] = $pdo->query("SELECT COUNT(*) FROM barang WHERE stok <= stok_minimum AND aktif = 1")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah),0) as qty, COALESCE(SUM(total_harga),0) as nilai FROM barang_masuk WHERE MONTH(tanggal_masuk)=? AND YEAR(tanggal_masuk)=?");
    $stmt->execute([$bulan, $tahun]);
    $masuk = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah),0) as qty, COALESCE(SUM(total_harga),0) as nilai FROM barang_keluar WHERE MONTH(tanggal_keluar)=? AND YEAR(tanggal_keluar)=?");
    $stmt->execute([$bulan, $tahun]);
    $keluar = $stmt->fetch();

    $per_kategori = $pdo->query("SELECT k.nama_kategori, COUNT(b.id) as jumlah_item, COALESCE(SUM(b.stok),0) as total_stok, COALESCE(SUM(b.stok*b.harga_beli),0) as nilai_stok FROM kategori k LEFT JOIN barang b ON k.id=b.kategori_id AND b.aktif=1 GROUP BY k.id ORDER BY k.nama_kategori")->fetchAll();

    $stok_menipis = $pdo->query("SELECT b.kode_barang, b.nama_barang, k.nama_kategori, b.stok, b.stok_minimum FROM barang b LEFT JOIN kategori k ON b.kategori_id=k.id WHERE b.stok <= b.stok_minimum AND b.aktif=1 ORDER BY b.stok ASC")->fetchAll();

    $title = "Laporan Inventaris Lengkap — " . bulanIndo($bulan) . " $tahun";

    // For CSV: we'll combine all data into sections
    // For PDF/Print: generate HTML
    $headers = null;
    $rows = null;
} else {
    setFlash('error', 'Tipe export tidak dikenal.');
    redirect('laporan.php');
}

// ==========================================
// CSV EXPORT
// ==========================================
if ($format === 'csv') {
    $filename = "export_{$type}_{$bulan}_{$tahun}_" . date('YmdHis') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($type === 'laporan') {
        // Section 1: Ringkasan
        fputcsv($output, ['LAPORAN INVENTARIS LENGKAP'], ';');
        fputcsv($output, ['Periode: ' . bulanIndo($bulan) . ' ' . $tahun], ';');
        fputcsv($output, ['Dicetak: ' . date('d/m/Y H:i') . ' oleh ' . $_SESSION['nama_lengkap']], ';');
        fputcsv($output, []);

        fputcsv($output, ['RINGKASAN STOK'], ';');
        fputcsv($output, ['Total Jenis Barang', $stats['total_barang']]);
        fputcsv($output, ['Total Stok', $stats['total_stok']]);
        fputcsv($output, ['Nilai Stok (Modal)', 'Rp ' . number_format($stats['nilai_stok'], 0, ',', '.')]);
        fputcsv($output, ['Stok Menipis', $stats['stok_menipis']]);
        fputcsv($output, []);

        fputcsv($output, ['TRANSAKSI BULAN INI'], ';');
        fputcsv($output, ['', 'Barang Masuk', 'Barang Keluar']);
        fputcsv($output, ['Jumlah Transaksi', $masuk['cnt'], $keluar['cnt']]);
        fputcsv($output, ['Total Item', $masuk['qty'], $keluar['qty']]);
        fputcsv($output, ['Total Nilai', 'Rp ' . number_format($masuk['nilai'], 0, ',', '.'), 'Rp ' . number_format($keluar['nilai'], 0, ',', '.')]);
        fputcsv($output, []);

        fputcsv($output, ['STOK PER KATEGORI'], ';');
        fputcsv($output, ['Kategori', 'Jenis', 'Stok', 'Nilai']);
        foreach ($per_kategori as $pk) {
            fputcsv($output, [$pk['nama_kategori'], $pk['jumlah_item'], $pk['total_stok'], 'Rp ' . number_format($pk['nilai_stok'], 0, ',', '.')]);
        }
        fputcsv($output, []);

        fputcsv($output, ['BARANG STOK MENIPIS'], ';');
        fputcsv($output, ['Kode', 'Nama', 'Kategori', 'Stok', 'Minimum', 'Status']);
        foreach ($stok_menipis as $s) {
            fputcsv($output, [$s['kode_barang'], $s['nama_barang'], $s['nama_kategori'] ?? '-', $s['stok'], $s['stok_minimum'], $s['stok'] == 0 ? 'Habis' : 'Menipis']);
        }
    } else {
        fputcsv($output, [$title], ';');
        fputcsv($output, ['Dicetak: ' . date('d/m/Y H:i') . ' oleh ' . $_SESSION['nama_lengkap']], ';');
        fputcsv($output, [$summary_label . ': Rp ' . number_format($summary_value, 0, ',', '.')], ';');
        fputcsv($output, []);
        fputcsv($output, $headers);
        foreach ($rows as $i => $r) {
            fputcsv($output, $map($i + 1, $r));
        }
    }

    fclose($output);
    exit;
}

// ==========================================
// PDF & PRINT → Generate HTML
// ==========================================
if ($type === 'laporan') {
    // Full report HTML
    $data_sections = true;
} else {
    $data_sections = false;
}

// Build HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($title) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; padding: 24px; }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 3px solid #059669; }
        .header h1 { font-size: 18px; color: #0f172a; margin-bottom: 4px; }
        .header .sub { color: #64748b; font-size: 11px; }
        .header .brand { font-size: 22px; font-weight: 700; color: #059669; margin-bottom: 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 11px; color: #64748b; }
        .meta strong { color: #1e293b; }
        .section-title { font-size: 14px; font-weight: 700; color: #0f172a; margin: 24px 0 12px; padding: 6px 0; border-bottom: 2px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #059669; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; }
        td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
        tr:nth-child(even) { background: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
        .summary-item { background: #f1f5f9; padding: 12px; border-radius: 6px; border-left: 4px solid #059669; }
        .summary-item .label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-item .value { font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 2px; }
        .summary-item.red { border-left-color: #dc2626; }
        .summary-item.blue { border-left-color: #2563eb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .badge-danger { background: #fef2f2; color: #dc2626; }
        .badge-warning { background: #fffbeb; color: #d97706; }
        .footer { margin-top: 40px; padding-top: 12px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; }
        .totals { margin-top: 8px; font-size: 12px; font-weight: 700; background: #f0fdf4; padding: 10px 14px; border-radius: 6px; text-align: right; border: 1px solid #bbf7d0; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="brand">BENGKEL JAYA</div>
    <h1><?= sanitize($title) ?></h1>
    <div class="sub">Sistem Inventaris Spare Part Sepeda Motor</div>
</div>

<div class="meta">
    <div><strong>Dicetak oleh:</strong> <?= sanitize($_SESSION['nama_lengkap']) ?> (<?= $_SESSION['role'] ?>)</div>
    <div><strong>Tanggal:</strong> <?= date('d F Y, H:i') ?></div>
</div>

<?php if ($type === 'laporan'): ?>

<div class="summary-box">
    <div class="summary-item">
        <div class="label">Total Jenis Barang</div>
        <div class="value"><?= number_format($stats['total_barang']) ?></div>
    </div>
    <div class="summary-item">
        <div class="label">Total Stok</div>
        <div class="value"><?= number_format($stats['total_stok']) ?></div>
    </div>
    <div class="summary-item blue">
        <div class="label">Nilai Stok (Modal)</div>
        <div class="value"><?= rupiah($stats['nilai_stok']) ?></div>
    </div>
    <div class="summary-item red">
        <div class="label">Stok Menipis</div>
        <div class="value"><?= $stats['stok_menipis'] ?></div>
    </div>
</div>

<div class="section-title">Ringkasan Transaksi — <?= bulanIndo($bulan) ?> <?= $tahun ?></div>
<table>
    <thead>
        <tr>
            <th>Keterangan</th>
            <th class="text-right">Barang Masuk</th>
            <th class="text-right">Barang Keluar</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Jumlah Transaksi</td><td class="text-right"><?= $masuk['cnt'] ?></td><td class="text-right"><?= $keluar['cnt'] ?></td></tr>
        <tr><td>Total Item</td><td class="text-right"><?= number_format($masuk['qty']) ?></td><td class="text-right"><?= number_format($keluar['qty']) ?></td></tr>
        <tr><td>Total Nilai</td><td class="text-right"><?= rupiah($masuk['nilai']) ?></td><td class="text-right"><?= rupiah($keluar['nilai']) ?></td></tr>
    </tbody>
</table>

<div class="section-title">Stok per Kategori</div>
<table>
    <thead>
        <tr><th>Kategori</th><th class="text-center">Jenis</th><th class="text-right">Stok</th><th class="text-right">Nilai Stok</th></tr>
    </thead>
    <tbody>
        <?php foreach ($per_kategori as $pk): ?>
        <tr>
            <td><?= sanitize($pk['nama_kategori']) ?></td>
            <td class="text-center"><?= $pk['jumlah_item'] ?></td>
            <td class="text-right"><?= number_format($pk['total_stok']) ?></td>
            <td class="text-right"><?= rupiah($pk['nilai_stok']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if (!empty($stok_menipis)): ?>
<div class="section-title">Barang Stok Menipis (<?= count($stok_menipis) ?>)</div>
<table>
    <thead>
        <tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th class="text-center">Stok</th><th class="text-center">Minimum</th><th class="text-center">Status</th></tr>
    </thead>
    <tbody>
        <?php foreach ($stok_menipis as $s): ?>
        <tr>
            <td><?= sanitize($s['kode_barang']) ?></td>
            <td><?= sanitize($s['nama_barang']) ?></td>
            <td><?= sanitize($s['nama_kategori'] ?? '-') ?></td>
            <td class="text-center"><strong><?= $s['stok'] ?></strong></td>
            <td class="text-center"><?= $s['stok_minimum'] ?></td>
            <td class="text-center"><span class="badge <?= $s['stok'] == 0 ? 'badge-danger' : 'badge-warning' ?>"><?= $s['stok'] == 0 ? 'Habis' : 'Menipis' ?></span></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php else: ?>

<!-- Detail Transaksi -->
<table>
    <thead>
        <tr>
            <?php foreach ($headers as $h): ?>
            <th><?= $h ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($headers) ?>" class="text-center" style="padding:24px;color:#94a3b8;">Tidak ada data transaksi ditemukan</td></tr>
        <?php else: ?>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
            <?php $mapped = $map($i + 1, $r); foreach ($mapped as $j => $val): ?>
            <td class="<?= $j >= 5 && $j <= 7 ? 'text-right' : '' ?>"><?= sanitize($val) ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($rows)): ?>
<div class="totals"><?= sanitize($summary_label) ?>: <?= rupiah($summary_value) ?> (<?= count($rows) ?> transaksi)</div>
<?php endif; ?>

<?php endif; ?>

<div class="footer">
    <div>Bengkel Jaya — Sistem Inventaris Spare Part</div>
    <div>Halaman ini digenerate secara otomatis pada <?= date('d/m/Y H:i:s') ?></div>
</div>

<?php if ($format === 'print'): ?>
<div class="no-print" style="position:fixed;top:16px;right:16px;z-index:9999;">
    <button onclick="window.print()" style="padding:10px 24px;background:#059669;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;font-weight:600;">
        🖨️ Cetak Sekarang
    </button>
    <button onclick="window.close()" style="padding:10px 24px;background:#64748b;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;margin-left:8px;">
        ✕ Tutup
    </button>
</div>
<script>window.onload = function() { /* Auto print disabled — user clicks button */ };</script>
<?php elseif ($format === 'pdf'): ?>
<div class="no-print" style="position:fixed;top:16px;right:16px;z-index:9999;">
    <button onclick="window.print()" style="padding:10px 24px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;font-weight:600;">
        📄 Simpan sebagai PDF
    </button>
    <button onclick="window.close()" style="padding:10px 24px;background:#64748b;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;margin-left:8px;">
        ✕ Tutup
    </button>
</div>
<script>window.onload = function() { window.print(); };</script>
<?php endif; ?>

</body>
</html>
<?php
$html = ob_get_clean();

if ($format === 'pdf' || $format === 'print') {
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}
?>
