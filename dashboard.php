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

// Chart Data: Monthly masuk vs keluar for last 6 months
$chart_labels = [];
$chart_masuk = [];
$chart_keluar = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('m', strtotime("-$i months"));
    $year = date('Y', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    $chart_labels[] = $monthName;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as total FROM barang_masuk WHERE MONTH(tanggal_masuk) = ? AND YEAR(tanggal_masuk) = ?");
    $stmt->execute([$month, $year]);
    $chart_masuk[] = (int)$stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as total FROM barang_keluar WHERE MONTH(tanggal_keluar) = ? AND YEAR(tanggal_keluar) = ?");
    $stmt->execute([$month, $year]);
    $chart_keluar[] = (int)$stmt->fetch()['total'];
}

// Chart Data: Stock distribution per kategori
$kategori_chart = $pdo->query("
    SELECT k.nama_kategori, COALESCE(SUM(b.stok), 0) as total_stok
    FROM kategori k
    LEFT JOIN barang b ON k.id = b.kategori_id AND b.aktif = 1
    GROUP BY k.id
    HAVING total_stok > 0
    ORDER BY total_stok DESC
")->fetchAll();

$kategori_labels = array_column($kategori_chart, 'nama_kategori');
$kategori_stok = array_column($kategori_chart, 'total_stok');

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

    <!-- Charts -->
    <div class="chart-grid animate-in">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line text-success"></i> Transaksi Masuk vs Keluar (6 Bulan)</h3>
            </div>
            <div class="card-body">
                <canvas id="transaksiChart" height="250"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie text-info"></i> Distribusi Stok per Kategori</h3>
            </div>
            <div class="card-body">
                <canvas id="kategoriChart" height="250"></canvas>
            </div>
        </div>
    </div>

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

<!-- Chart.js initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Masuk vs Keluar - Line Chart
    const transaksiCtx = document.getElementById('transaksiChart').getContext('2d');
    new Chart(transaksiCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Barang Masuk',
                    data: <?= json_encode($chart_masuk) ?>,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#059669',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                },
                {
                    label: 'Barang Keluar',
                    data: <?= json_encode($chart_keluar) ?>,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#dc2626',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: { family: "'Open Sans', sans-serif", size: 13, weight: '600' }
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { family: "'Poppins', sans-serif", size: 13 },
                    bodyFont: { family: "'Open Sans', sans-serif", size: 12 },
                    padding: 12,
                    cornerRadius: 10,
                    displayColors: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { family: "'Open Sans', sans-serif", size: 12 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: "'Open Sans', sans-serif", size: 12 } }
                }
            }
        }
    });

    // Stock Distribution per Kategori - Doughnut Chart
    const kategoriCtx = document.getElementById('kategoriChart').getContext('2d');
    const kategoriColors = [
        '#059669', '#0284c7', '#d97706', '#dc2626', '#7c3aed',
        '#0891b2', '#ea580c', '#4f46e5', '#16a34a', '#be185d'
    ];
    new Chart(kategoriCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($kategori_labels) ?>,
            datasets: [{
                data: <?= json_encode($kategori_stok) ?>,
                backgroundColor: kategoriColors.slice(0, <?= count($kategori_labels) ?>),
                borderColor: '#fff',
                borderWidth: 3,
                hoverBorderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        padding: 14,
                        font: { family: "'Open Sans', sans-serif", size: 12, weight: '500' },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => ({
                                    text: label + ' (' + data.datasets[0].data[i] + ')',
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: '#fff',
                                    lineWidth: 2,
                                    pointStyle: 'circle',
                                    hidden: false,
                                    index: i
                                }));
                            }
                            return [];
                        }
                    }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { family: "'Poppins', sans-serif", size: 13 },
                    bodyFont: { family: "'Open Sans', sans-serif", size: 12 },
                    padding: 12,
                    cornerRadius: 10,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' unit (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
});
</script>

</body>
</html>
