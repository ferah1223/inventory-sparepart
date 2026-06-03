<?php
// laporan.php - Laporan Inventaris
require_once 'config/database.php';
requireLogin();

$page_title = 'Laporan — Inventaris Bengkel Jaya';

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$view = $_GET['view'] ?? 'ringkasan'; // ringkasan, masuk, keluar

// Ringkasan
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM barang WHERE aktif = 1");
$stats['total_barang'] = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COALESCE(SUM(stok), 0) as total FROM barang WHERE aktif = 1");
$stats['total_stok'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(stok * harga_beli), 0) as total FROM barang WHERE aktif = 1");
$stats['nilai_stok'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM barang WHERE stok <= stok_minimum AND aktif = 1");
$stats['stok_menipis'] = $stmt->fetch()['cnt'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah), 0) as qty, COALESCE(SUM(total_harga), 0) as nilai FROM barang_masuk WHERE MONTH(tanggal_masuk) = ? AND YEAR(tanggal_masuk) = ?");
$stmt->execute([$bulan, $tahun]);
$masuk_bulan = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah), 0) as qty, COALESCE(SUM(total_harga), 0) as nilai FROM barang_keluar WHERE MONTH(tanggal_keluar) = ? AND YEAR(tanggal_keluar) = ?");
$stmt->execute([$bulan, $tahun]);
$keluar_bulan = $stmt->fetch();

// Per kategori
$per_kategori = $pdo->query("
    SELECT k.nama_kategori, COUNT(b.id) as jumlah_item, COALESCE(SUM(b.stok), 0) as total_stok, COALESCE(SUM(b.stok * b.harga_beli), 0) as nilai_stok
    FROM kategori k 
    LEFT JOIN barang b ON k.id = b.kategori_id AND b.aktif = 1
    GROUP BY k.id 
    ORDER BY k.nama_kategori
")->fetchAll();

// Stok menipis
$stok_menipis = $pdo->query("
    SELECT b.*, k.nama_kategori 
    FROM barang b 
    LEFT JOIN kategori k ON b.kategori_id = k.id 
    WHERE b.stok <= b.stok_minimum AND b.aktif = 1
    ORDER BY b.stok ASC
")->fetchAll();

// Barang paling sering keluar
$populer = $pdo->prepare("
    SELECT b.kode_barang, b.nama_barang, SUM(bk.jumlah) as total_keluar
    FROM barang_keluar bk 
    JOIN barang b ON bk.barang_id = b.id 
    WHERE MONTH(bk.tanggal_keluar) = ? AND YEAR(bk.tanggal_keluar) = ?
    GROUP BY bk.barang_id 
    ORDER BY total_keluar DESC 
    LIMIT 5
");
$populer->execute([$bulan, $tahun]);
$barang_populer = $populer->fetchAll();

// Detail transaksi masuk
$detail_masuk = $pdo->prepare("
    SELECT bm.*, b.kode_barang, b.nama_barang, k.nama_kategori, u.nama_lengkap
    FROM barang_masuk bm
    JOIN barang b ON bm.barang_id = b.id
    LEFT JOIN kategori k ON b.kategori_id = k.id
    LEFT JOIN users u ON bm.user_id = u.id
    WHERE MONTH(bm.tanggal_masuk) = ? AND YEAR(bm.tanggal_masuk) = ?
    ORDER BY bm.tanggal_masuk DESC, bm.id DESC
");
$detail_masuk->execute([$bulan, $tahun]);
$detail_masuk = $detail_masuk->fetchAll();

// Detail transaksi keluar
$detail_keluar = $pdo->prepare("
    SELECT bk.*, b.kode_barang, b.nama_barang, k.nama_kategori, u.nama_lengkap
    FROM barang_keluar bk
    JOIN barang b ON bk.barang_id = b.id
    LEFT JOIN kategori k ON b.kategori_id = k.id
    LEFT JOIN users u ON bk.user_id = u.id
    WHERE MONTH(bk.tanggal_keluar) = ? AND YEAR(bk.tanggal_keluar) = ?
    ORDER BY bk.tanggal_keluar DESC, bk.id DESC
");
$detail_keluar->execute([$bulan, $tahun]);
$detail_keluar = $detail_keluar->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Laporan Inventaris</h1>
            <div class="page-header-sub">Ringkasan & detail data persediaan barang gudang</div>
        </div>
        <div class="page-header-actions">
            <form method="GET" class="d-flex gap-1">
                <?php if (isset($_GET['view'])): ?>
                    <input type="hidden" name="view" value="<?= sanitize($_GET['view']) ?>">
                <?php endif; ?>
                <select name="bulan" class="form-select" style="width:auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $bulan == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="tahun" class="form-select" style="width:auto;">
                    <?php for ($y = 2024; $y <= 2030; $y++): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>
    </div>

    <!-- TAB NAVIGASI -->
    <div class="card animate-in" style="margin-bottom:var(--space-lg);">
        <div style="display:flex;gap:0;border-bottom:2px solid var(--gray-200);">
            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&view=ringkasan" 
               class="tab-link <?= $view === 'ringkasan' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> Ringkasan
            </a>
            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&view=masuk" 
               class="tab-link <?= $view === 'masuk' ? 'active' : '' ?>">
                <i class="fas fa-arrow-down text-success"></i> Detail Masuk (<?= count($detail_masuk) ?>)
            </a>
            <a href="?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&view=keluar" 
               class="tab-link <?= $view === 'keluar' ? 'active' : '' ?>">
                <i class="fas fa-arrow-up text-danger"></i> Detail Keluar (<?= count($detail_keluar) ?>)
            </a>
        </div>
    </div>

    <!-- EXPORT BUTTONS -->
    <div class="export-toolbar animate-in">
        <div class="export-toolbar-label"><i class="fas fa-download"></i> Export Data:</div>
        <?php if ($view === 'ringkasan'): ?>
            <a href="export.php?type=laporan&format=csv&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn-export btn-csv">
                <i class="fas fa-file-csv"></i> Excel/CSV
            </a>
            <a href="export.php?type=laporan&format=pdf&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-export btn-pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="export.php?type=laporan&format=print&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-export btn-print">
                <i class="fas fa-print"></i> Print
            </a>
        <?php elseif ($view === 'masuk'): ?>
            <a href="export.php?type=masuk&format=csv&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn-export btn-csv">
                <i class="fas fa-file-csv"></i> Excel/CSV
            </a>
            <a href="export.php?type=masuk&format=pdf&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-export btn-pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="export.php?type=masuk&format=print&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-export btn-print">
                <i class="fas fa-print"></i> Print
            </a>
        <?php elseif ($view === 'keluar'): ?>
            <a href="export.php?type=keluar&format=csv&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn-export btn-csv">
                <i class="fas fa-file-csv"></i> Excel/CSV
            </a>
            <a href="export.php?type=keluar&format=pdf&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-export btn-pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="export.php?type=keluar&format=print&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" target="_blank" class="btn-export btn-print">
                <i class="fas fa-print"></i> Print
            </a>
        <?php endif; ?>
        <a href="export.php?type=stok&format=csv" class="btn-export btn-csv" style="margin-left:auto;">
            <i class="fas fa-database"></i> Export Semua Stok
        </a>
    </div>

    <?php if ($view === 'ringkasan'): ?>
    <!-- ========================================== -->
    <!-- VIEW: RINGKASAN                            -->
    <!-- ========================================== -->

    <!-- Ringkasan Stok -->
    <div class="stat-grid animate-in">
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Total Jenis Barang</span>
                <div class="stat-card-icon blue"><i class="fas fa-boxes-stacked"></i></div>
            </div>
            <div class="stat-card-value"><?= $stats['total_barang'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Total Stok</span>
                <div class="stat-card-icon green"><i class="fas fa-warehouse"></i></div>
            </div>
            <div class="stat-card-value"><?= number_format($stats['total_stok']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Nilai Stok (Modal)</span>
                <div class="stat-card-icon blue"><i class="fas fa-coins"></i></div>
            </div>
            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($stats['nilai_stok']) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Stok Menipis</span>
                <div class="stat-card-icon <?= $stats['stok_menipis'] > 0 ? 'red' : 'green' ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-card-value <?= $stats['stok_menipis'] > 0 ? 'text-danger' : '' ?>"><?= $stats['stok_menipis'] ?></div>
        </div>
    </div>

    <!-- Transaksi Bulan Ini -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);margin-bottom:var(--space-lg);" class="animate-in">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-arrow-down text-success"></i> Barang Masuk — <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-md);text-align:center;">
                    <div>
                        <div class="text-muted" style="font-size:0.8125rem;">Transaksi</div>
                        <div class="fw-700" style="font-size:1.25rem;"><?= $masuk_bulan['cnt'] ?></div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.8125rem;">Jumlah Item</div>
                        <div class="fw-700" style="font-size:1.25rem;"><?= number_format($masuk_bulan['qty']) ?></div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.8125rem;">Nilai</div>
                        <div class="fw-700 text-success" style="font-size:1rem;"><?= rupiah($masuk_bulan['nilai']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-arrow-up text-danger"></i> Barang Keluar — <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-md);text-align:center;">
                    <div>
                        <div class="text-muted" style="font-size:0.8125rem;">Transaksi</div>
                        <div class="fw-700" style="font-size:1.25rem;"><?= $keluar_bulan['cnt'] ?></div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.8125rem;">Jumlah Item</div>
                        <div class="fw-700" style="font-size:1.25rem;"><?= number_format($keluar_bulan['qty']) ?></div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:0.8125rem;">Nilai</div>
                        <div class="fw-700 text-danger" style="font-size:1rem;"><?= rupiah($keluar_bulan['nilai']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);" class="animate-in">
        <!-- Per Kategori -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Stok per Kategori</h3>
            </div>
            <div class="card-body no-pad">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jenis</th>
                            <th>Stok</th>
                            <th>Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($per_kategori as $pk): ?>
                        <tr>
                            <td class="fw-600"><?= sanitize($pk['nama_kategori']) ?></td>
                            <td><?= $pk['jumlah_item'] ?></td>
                            <td><?= number_format($pk['total_stok']) ?></td>
                            <td class="text-muted"><?= rupiah($pk['nilai_stok']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Barang Populer -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-fire text-warning"></i> Paling Sering Keluar</h3>
            </div>
            <div class="card-body no-pad">
                <?php if (!empty($barang_populer)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Total Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barang_populer as $bp): ?>
                        <tr>
                            <td>
                                <div class="fw-600"><?= sanitize($bp['nama_barang']) ?></div>
                                <small class="text-muted"><?= sanitize($bp['kode_barang']) ?></small>
                            </td>
                            <td><span class="badge badge-danger"><?= $bp['total_keluar'] ?> item</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state" style="padding:32px;">
                    <p class="text-muted">Belum ada data transaksi keluar bulan ini</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stok Menipis -->
    <?php if (!empty($stok_menipis)): ?>
    <div class="card animate-in" style="margin-top:var(--space-lg);">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-circle text-danger"></i> Barang Stok Menipis (<?= count($stok_menipis) ?>)</h3>
        </div>
        <div class="card-body no-pad">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stok_menipis as $s): ?>
                    <tr>
                        <td><code class="text-mono"><?= sanitize($s['kode_barang']) ?></code></td>
                        <td class="fw-600"><?= sanitize($s['nama_barang']) ?></td>
                        <td class="text-muted"><?= sanitize($s['nama_kategori'] ?? '-') ?></td>
                        <td><strong class="text-danger"><?= $s['stok'] ?></strong></td>
                        <td><?= $s['stok_minimum'] ?></td>
                        <td>
                            <?php if ($s['stok'] == 0): ?>
                                <span class="badge badge-danger">Habis</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Menipis</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($view === 'masuk'): ?>
    <!-- ========================================== -->
    <!-- VIEW: DETAIL BARANG MASUK                  -->
    <!-- ========================================== -->
    <div class="card animate-in">
        <div class="card-header">
            <h3><i class="fas fa-arrow-down text-success"></i> Detail Barang Masuk — <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
            <span class="badge badge-success"><?= count($detail_masuk) ?> transaksi</span>
        </div>
        <div class="card-body no-pad">
            <?php if (!empty($detail_masuk)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Jumlah</th>
                            <th>Harga Satuan</th>
                            <th>Total</th>
                            <th>Supplier</th>
                            <th>Keterangan</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_masuk as $i => $dm): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><code><?= sanitize($dm['no_transaksi']) ?></code></td>
                            <td><?= tglPendek($dm['tanggal_masuk']) ?></td>
                            <td><code><?= sanitize($dm['kode_barang']) ?></code></td>
                            <td class="fw-600"><?= sanitize($dm['nama_barang']) ?></td>
                            <td class="text-muted"><?= sanitize($dm['nama_kategori'] ?? '-') ?></td>
                            <td class="fw-600"><?= $dm['jumlah'] ?></td>
                            <td><?= rupiah($dm['harga_satuan']) ?></td>
                            <td class="fw-700 text-success"><?= rupiah($dm['total_harga']) ?></td>
                            <td><?= sanitize($dm['supplier'] ?: '-') ?></td>
                            <td class="text-muted"><?= sanitize($dm['keterangan'] ?: '-') ?></td>
                            <td class="text-muted"><?= sanitize($dm['nama_lengkap']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f0fdf4;font-weight:700;">
                            <td colspan="6" class="text-right">Total</td>
                            <td><?= number_format(array_sum(array_column($detail_masuk, 'jumlah'))) ?></td>
                            <td></td>
                            <td class="text-success"><?= rupiah(array_sum(array_column($detail_masuk, 'total_harga'))) ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:48px;">
                <i class="fas fa-inbox" style="font-size:2.5rem;color:var(--gray-300);margin-bottom:12px;"></i>
                <p class="text-muted">Tidak ada transaksi barang masuk bulan ini</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($view === 'keluar'): ?>
    <!-- ========================================== -->
    <!-- VIEW: DETAIL BARANG KELUAR                 -->
    <!-- ========================================== -->
    <div class="card animate-in">
        <div class="card-header">
            <h3><i class="fas fa-arrow-up text-danger"></i> Detail Barang Keluar — <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
            <span class="badge badge-danger"><?= count($detail_keluar) ?> transaksi</span>
        </div>
        <div class="card-body no-pad">
            <?php if (!empty($detail_keluar)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Jumlah</th>
                            <th>Harga Satuan</th>
                            <th>Total</th>
                            <th>Tujuan</th>
                            <th>Penerima</th>
                            <th>Keterangan</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_keluar as $i => $dk): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td><code><?= sanitize($dk['no_transaksi']) ?></code></td>
                            <td><?= tglPendek($dk['tanggal_keluar']) ?></td>
                            <td><code><?= sanitize($dk['kode_barang']) ?></code></td>
                            <td class="fw-600"><?= sanitize($dk['nama_barang']) ?></td>
                            <td class="text-muted"><?= sanitize($dk['nama_kategori'] ?? '-') ?></td>
                            <td class="fw-600"><?= $dk['jumlah'] ?></td>
                            <td><?= rupiah($dk['harga_satuan']) ?></td>
                            <td class="fw-700 text-danger"><?= rupiah($dk['total_harga']) ?></td>
                            <td><?= sanitize($dk['tujuan'] ?: '-') ?></td>
                            <td><?= sanitize($dk['penerima'] ?: '-') ?></td>
                            <td class="text-muted"><?= sanitize($dk['keterangan'] ?: '-') ?></td>
                            <td class="text-muted"><?= sanitize($dk['nama_lengkap']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#fef2f2;font-weight:700;">
                            <td colspan="6" class="text-right">Total</td>
                            <td><?= number_format(array_sum(array_column($detail_keluar, 'jumlah'))) ?></td>
                            <td></td>
                            <td class="text-danger"><?= rupiah(array_sum(array_column($detail_keluar, 'total_harga'))) ?></td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:48px;">
                <i class="fas fa-inbox" style="font-size:2.5rem;color:var(--gray-300);margin-bottom:12px;"></i>
                <p class="text-muted">Tidak ada transaksi barang keluar bulan ini</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<style>
    /* Tab Links */
    .tab-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 12px 20px;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--gray-500);
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
    }
    .tab-link:hover {
        color: var(--primary);
        background: var(--gray-50);
    }
    .tab-link.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    /* Export Toolbar */
    .export-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-lg);
        margin-bottom: var(--space-lg);
        flex-wrap: wrap;
    }
    .export-toolbar-label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--gray-600);
    }
    .btn-export {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        font-size: 0.8125rem;
        font-weight: 600;
        border-radius: var(--radius-md);
        text-decoration: none;
        transition: all 0.2s;
        cursor: pointer;
        border: 1px solid transparent;
    }
    .btn-csv {
        background: #f0fdf4;
        color: #15803d;
        border-color: #bbf7d0;
    }
    .btn-csv:hover { background: #dcfce7; }
    .btn-pdf {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }
    .btn-pdf:hover { background: #fee2e2; }
    .btn-print {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }
    .btn-print:hover { background: #dbeafe; }

    /* Table responsive */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    tfoot td {
        border-top: 2px solid var(--gray-300);
        font-size: 0.875rem;
        padding: 10px;
    }
</style>

</body>
</html>
