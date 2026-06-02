<?php
// laporan.php - Laporan Inventaris
require_once 'config/database.php';
requireLogin();

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

// Ringkasan
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM barang WHERE aktif = 1");
$stats['total_barang'] = $stmt->fetch()['cnt'];

$stmt = $pdo->query("SELECT COALESCE(SUM(stok), 0) as total FROM barang WHERE aktif = 1");
$stats['total_stok'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(stok * harga_beli), 0) as total FROM barang WHERE aktif = 1");
$stats['nilai_stok'] = $stmt->fetch()['total'];

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

include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Laporan Inventaris</h1>
            <div class="page-header-sub">Ringkasan data persediaan barang gudang</div>
        </div>
        <div class="page-header-actions">
            <form method="GET" class="d-flex gap-1">
                <select name="bulan" class="form-select" style="width:auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $bulan == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="tahun" class="form-select" style="width:auto;">
                    <?php for ($y = 2025; $y <= 2027; $y++): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>
    </div>

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
                <div class="stat-card-icon <?= count($stok_menipis) > 0 ? 'red' : 'green' ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-card-value <?= count($stok_menipis) > 0 ? 'text-danger' : '' ?>"><?= count($stok_menipis) ?></div>
        </div>
    </div>

    <!-- Transaksi Bulan Ini -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);margin-bottom:var(--space-lg);" class="animate-in">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-arrow-down text-success"></i> Barang Masuk - <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
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
                <h3><i class="fas fa-arrow-up text-danger"></i> Barang Keluar - <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
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
</div>
