<?php
// dashboard.php - Halaman Dashboard
require_once 'config/database.php';
requireLogin();

$page_title = 'Dashboard — Inventaris Bengkel Jaya';

// Statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM barang WHERE aktif = 1");
$total_barang = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(stok), 0) as total FROM barang WHERE aktif = 1");
$total_stok = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM kategori");
$total_kategori = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM barang WHERE stok <= stok_minimum AND aktif = 1");
$stmt->execute();
$stok_menipis = $stmt->fetch()['total'];

// Barang masuk hari ini
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_harga), 0) as total FROM barang_masuk WHERE tanggal_masuk = CURDATE()");
$stmt->execute();
$masuk_today = $stmt->fetch();

// Barang keluar hari ini
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_harga), 0) as total FROM barang_keluar WHERE tanggal_keluar = CURDATE()");
$stmt->execute();
$keluar_today = $stmt->fetch();

// Transaksi terbaru
$masuk_terbaru = $pdo->query("
    SELECT bm.*, b.kode_barang, b.nama_barang, u.nama_lengkap 
    FROM barang_masuk bm 
    JOIN barang b ON bm.barang_id = b.id 
    LEFT JOIN users u ON bm.user_id = u.id 
    ORDER BY bm.created_at DESC LIMIT 5
")->fetchAll();

$keluar_terbaru = $pdo->query("
    SELECT bk.*, b.kode_barang, b.nama_barang, u.nama_lengkap 
    FROM barang_keluar bk 
    JOIN barang b ON bk.barang_id = b.id 
    LEFT JOIN users u ON bk.user_id = u.id 
    ORDER BY bk.created_at DESC LIMIT 5
")->fetchAll();

// Stok menipis
$stok_menipis_list = $pdo->query("
    SELECT b.*, k.nama_kategori 
    FROM barang b 
    LEFT JOIN kategori k ON b.kategori_id = k.id 
    WHERE b.stok <= b.stok_minimum AND b.aktif = 1
    ORDER BY b.stok ASC LIMIT 5
")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <div class="page-header-sub">Selamat datang, <?= sanitize($_SESSION['nama_lengkap']) ?> · <?= tglIndo(date('Y-m-d')) ?></div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid animate-in">
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Total Jenis Barang</span>
                <div class="stat-card-icon blue"><i class="fas fa-boxes-stacked"></i></div>
            </div>
            <div class="stat-card-value"><?= $total_barang ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Total Stok Gudang</span>
                <div class="stat-card-icon green"><i class="fas fa-warehouse"></i></div>
            </div>
            <div class="stat-card-value"><?= number_format($total_stok) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Jumlah Kategori</span>
                <div class="stat-card-icon blue"><i class="fas fa-tags"></i></div>
            </div>
            <div class="stat-card-value"><?= $total_kategori ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">Stok Menipis</span>
                <div class="stat-card-icon <?= $stok_menipis > 0 ? 'red' : 'green' ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-card-value <?= $stok_menipis > 0 ? 'text-danger' : '' ?>"><?= $stok_menipis ?></div>
        </div>
    </div>

    <!-- Alert Stok Menipis -->
    <?php if ($stok_menipis > 0): ?>
    <div class="alert alert-warning animate-in">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Perhatian!</strong> <?= $stok_menipis ?> barang sudah di batas minimum stok.
            <a href="barang.php?filter=menipis" style="font-weight:600;margin-left:8px;">Lihat detail →</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Transaksi Hari Ini -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
        <div class="card animate-in">
            <div class="card-header">
                <h3><i class="fas fa-arrow-down text-success"></i> Masuk Hari Ini</h3>
                <span class="badge badge-success"><?= $masuk_today['cnt'] ?> transaksi</span>
            </div>
            <div class="card-body">
                <div class="stat-card-value" style="font-size:1.5rem;"><?= rupiah($masuk_today['total']) ?></div>
            </div>
        </div>
        <div class="card animate-in">
            <div class="card-header">
                <h3><i class="fas fa-arrow-up text-danger"></i> Keluar Hari Ini</h3>
                <span class="badge badge-danger"><?= $keluar_today['cnt'] ?> transaksi</span>
            </div>
            <div class="card-body">
                <div class="stat-card-value" style="font-size:1.5rem;"><?= rupiah($keluar_today['total']) ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Transaksi Terbaru -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);margin-top:var(--space-lg);" class="animate-in">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-arrow-down text-success"></i> Barang Masuk Terakhir</h3>
                <a href="masuk.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
            </div>
            <div class="card-body no-pad">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Jumlah</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($masuk_terbaru as $m): ?>
                            <tr>
                                <td>
                                    <div class="fw-600"><?= sanitize($m['nama_barang']) ?></div>
                                    <small class="text-muted text-mono"><?= sanitize($m['kode_barang']) ?></small>
                                </td>
                                <td><span class="badge badge-success">+<?= $m['jumlah'] ?></span></td>
                                <td class="text-muted"><?= tglPendek($m['tanggal_masuk']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($masuk_terbaru)): ?>
                            <tr><td colspan="3" class="text-center text-muted" style="padding:32px;">Belum ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-arrow-up text-danger"></i> Barang Keluar Terakhir</h3>
                <a href="keluar.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
            </div>
            <div class="card-body no-pad">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Jumlah</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keluar_terbaru as $k): ?>
                            <tr>
                                <td>
                                    <div class="fw-600"><?= sanitize($k['nama_barang']) ?></div>
                                    <small class="text-muted text-mono"><?= sanitize($k['kode_barang']) ?></small>
                                </td>
                                <td><span class="badge badge-danger">-<?= $k['jumlah'] ?></span></td>
                                <td class="text-muted"><?= tglPendek($k['tanggal_keluar']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($keluar_terbaru)): ?>
                            <tr><td colspan="3" class="text-center text-muted" style="padding:32px;">Belum ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Stok Menipis -->
    <?php if (!empty($stok_menipis_list)): ?>
    <div class="card animate-in" style="margin-top:var(--space-lg);">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-circle text-warning"></i> Stok Menipis</h3>
            <a href="barang.php?filter=menipis" class="btn btn-ghost btn-sm">Lihat Semua</a>
        </div>
        <div class="card-body no-pad">
            <div class="table-container">
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
                        <?php foreach ($stok_menipis_list as $s): ?>
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
    </div>
    <?php endif; ?>
</div>

</body>
</html>
