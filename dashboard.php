     1|<?php
     2|// dashboard.php - Halaman Dashboard
     3|require_once 'config/database.php';
     4|requireLogin();

$page_title = 'Dashboard — Inventaris Bengkel Jaya';
     5|
     6|// Statistik
     7|$stmt = $pdo->query("SELECT COUNT(*) as total FROM barang WHERE aktif = 1");
     8|$total_barang = $stmt->fetch()['total'];
     9|
    10|$stmt = $pdo->query("SELECT COALESCE(SUM(stok), 0) as total FROM barang WHERE aktif = 1");
    11|$total_stok = $stmt->fetch()['total'];
    12|
    13|$stmt = $pdo->query("SELECT COUNT(*) as total FROM kategori");
    14|$total_kategori = $stmt->fetch()['total'];
    15|
    16|$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM barang WHERE stok <= stok_minimum AND aktif = 1");
    17|$stmt->execute();
    18|$stok_menipis = $stmt->fetch()['total'];
    19|
    20|// Barang masuk hari ini
    21|$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_harga), 0) as total FROM barang_masuk WHERE tanggal_masuk = CURDATE()");
    22|$stmt->execute();
    23|$masuk_today = $stmt->fetch();
    24|
    25|// Barang keluar hari ini
    26|$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_harga), 0) as total FROM barang_keluar WHERE tanggal_keluar = CURDATE()");
    27|$stmt->execute();
    28|$keluar_today = $stmt->fetch();
    29|
    30|// Transaksi terbaru
    31|$masuk_terbaru = $pdo->query("
    32|    SELECT bm.*, b.kode_barang, b.nama_barang, u.nama_lengkap 
    33|    FROM barang_masuk bm 
    34|    JOIN barang b ON bm.barang_id = b.id 
    35|    LEFT JOIN users u ON bm.user_id = u.id 
    36|    ORDER BY bm.created_at DESC LIMIT 5
    37|")->fetchAll();
    38|
    39|$keluar_terbaru = $pdo->query("
    40|    SELECT bk.*, b.kode_barang, b.nama_barang, u.nama_lengkap 
    41|    FROM barang_keluar bk 
    42|    JOIN barang b ON bk.barang_id = b.id 
    43|    LEFT JOIN users u ON bk.user_id = u.id 
    44|    ORDER BY bk.created_at DESC LIMIT 5
    45|")->fetchAll();
    46|
    47|// Stok menipis
    48|$stok_menipis_list = $pdo->query("
    49|    SELECT b.*, k.nama_kategori 
    50|    FROM barang b 
    51|    LEFT JOIN kategori k ON b.kategori_id = k.id 
    52|    WHERE b.stok <= b.stok_minimum AND b.aktif = 1
    53|    ORDER BY b.stok ASC LIMIT 5
    54|")->fetchAll();
    55|
    56|<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
    57|?>
    58|
    59|<div class="main-content">
    60|    <div class="page-header">
    61|        <div>
    62|            <h1>Dashboard</h1>
    63|            <div class="page-header-sub">Selamat datang, <?= sanitize($_SESSION['nama_lengkap']) ?> · <?= tglIndo(date('Y-m-d')) ?></div>
    64|        </div>
    65|    </div>
    66|
    67|    <!-- Stat Cards -->
    68|    <div class="stat-grid animate-in">
    69|        <div class="stat-card">
    70|            <div class="stat-card-header">
    71|                <span class="stat-card-label">Total Jenis Barang</span>
    72|                <div class="stat-card-icon blue"><i class="fas fa-boxes-stacked"></i></div>
    73|            </div>
    74|            <div class="stat-card-value"><?= $total_barang ?></div>
    75|        </div>
    76|        <div class="stat-card">
    77|            <div class="stat-card-header">
    78|                <span class="stat-card-label">Total Stok Gudang</span>
    79|                <div class="stat-card-icon green"><i class="fas fa-warehouse"></i></div>
    80|            </div>
    81|            <div class="stat-card-value"><?= number_format($total_stok) ?></div>
    82|        </div>
    83|        <div class="stat-card">
    84|            <div class="stat-card-header">
    85|                <span class="stat-card-label">Jumlah Kategori</span>
    86|                <div class="stat-card-icon blue"><i class="fas fa-tags"></i></div>
    87|            </div>
    88|            <div class="stat-card-value"><?= $total_kategori ?></div>
    89|        </div>
    90|        <div class="stat-card">
    91|            <div class="stat-card-header">
    92|                <span class="stat-card-label">Stok Menipis</span>
    93|                <div class="stat-card-icon <?= $stok_menipis > 0 ? 'red' : 'green' ?>">
    94|                    <i class="fas fa-exclamation-triangle"></i>
    95|                </div>
    96|            </div>
    97|            <div class="stat-card-value <?= $stok_menipis > 0 ? 'text-danger' : '' ?>"><?= $stok_menipis ?></div>
    98|        </div>
    99|    </div>
   100|
   101|    <!-- Alert Stok Menipis -->
   102|    <?php if ($stok_menipis > 0): ?>
   103|    <div class="alert alert-warning animate-in">
   104|        <i class="fas fa-exclamation-triangle"></i>
   105|        <div>
   106|            <strong>Perhatian!</strong> <?= $stok_menipis ?> barang sudah di batas minimum stok.
   107|            <a href="barang.php?filter=menipis" style="font-weight:600;margin-left:8px;">Lihat detail →</a>
   108|        </div>
   109|    </div>
   110|    <?php endif; ?>
   111|
   112|    <!-- Transaksi Hari Ini -->
   113|    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
   114|        <div class="card animate-in">
   115|            <div class="card-header">
   116|                <h3><i class="fas fa-arrow-down text-success"></i> Masuk Hari Ini</h3>
   117|                <span class="badge badge-success"><?= $masuk_today['cnt'] ?> transaksi</span>
   118|            </div>
   119|            <div class="card-body">
   120|                <div class="stat-card-value" style="font-size:1.5rem;"><?= rupiah($masuk_today['total']) ?></div>
   121|            </div>
   122|        </div>
   123|        <div class="card animate-in">
   124|            <div class="card-header">
   125|                <h3><i class="fas fa-arrow-up text-danger"></i> Keluar Hari Ini</h3>
   126|                <span class="badge badge-danger"><?= $keluar_today['cnt'] ?> transaksi</span>
   127|            </div>
   128|            <div class="card-body">
   129|                <div class="stat-card-value" style="font-size:1.5rem;"><?= rupiah($keluar_today['total']) ?></div>
   130|            </div>
   131|        </div>
   132|    </div>
   133|
   134|    <!-- Tabel Transaksi Terbaru -->
   135|    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);margin-top:var(--space-lg);" class="animate-in">
   136|        <div class="card">
   137|            <div class="card-header">
   138|                <h3><i class="fas fa-arrow-down text-success"></i> Barang Masuk Terakhir</h3>
   139|                <a href="masuk.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
   140|            </div>
   141|            <div class="card-body no-pad">
   142|                <div class="table-container">
   143|                    <table class="table">
   144|                        <thead>
   145|                            <tr>
   146|                                <th>Barang</th>
   147|                                <th>Jumlah</th>
   148|                                <th>Tanggal</th>
   149|                            </tr>
   150|                        </thead>
   151|                        <tbody>
   152|                            <?php foreach ($masuk_terbaru as $m): ?>
   153|                            <tr>
   154|                                <td>
   155|                                    <div class="fw-600"><?= sanitize($m['nama_barang']) ?></div>
   156|                                    <small class="text-muted text-mono"><?= sanitize($m['kode_barang']) ?></small>
   157|                                </td>
   158|                                <td><span class="badge badge-success">+<?= $m['jumlah'] ?></span></td>
   159|                                <td class="text-muted"><?= tglPendek($m['tanggal_masuk']) ?></td>
   160|                            </tr>
   161|                            <?php endforeach; ?>
   162|                            <?php if (empty($masuk_terbaru)): ?>
   163|                            <tr><td colspan="3" class="text-center text-muted" style="padding:32px;">Belum ada data</td></tr>
   164|                            <?php endif; ?>
   165|                        </tbody>
   166|                    </table>
   167|                </div>
   168|            </div>
   169|        </div>
   170|
   171|        <div class="card">
   172|            <div class="card-header">
   173|                <h3><i class="fas fa-arrow-up text-danger"></i> Barang Keluar Terakhir</h3>
   174|                <a href="keluar.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
   175|            </div>
   176|            <div class="card-body no-pad">
   177|                <div class="table-container">
   178|                    <table class="table">
   179|                        <thead>
   180|                            <tr>
   181|                                <th>Barang</th>
   182|                                <th>Jumlah</th>
   183|                                <th>Tanggal</th>
   184|                            </tr>
   185|                        </thead>
   186|                        <tbody>
   187|                            <?php foreach ($keluar_terbaru as $k): ?>
   188|                            <tr>
   189|                                <td>
   190|                                    <div class="fw-600"><?= sanitize($k['nama_barang']) ?></div>
   191|                                    <small class="text-muted text-mono"><?= sanitize($k['kode_barang']) ?></small>
   192|                                </td>
   193|                                <td><span class="badge badge-danger">-<?= $k['jumlah'] ?></span></td>
   194|                                <td class="text-muted"><?= tglPendek($k['tanggal_keluar']) ?></td>
   195|                            </tr>
   196|                            <?php endforeach; ?>
   197|                            <?php if (empty($keluar_terbaru)): ?>
   198|                            <tr><td colspan="3" class="text-center text-muted" style="padding:32px;">Belum ada data</td></tr>
   199|                            <?php endif; ?>
   200|                        </tbody>
   201|                    </table>
   202|                </div>
   203|            </div>
   204|        </div>
   205|    </div>
   206|
   207|    <!-- Stok Menipis -->
   208|    <?php if (!empty($stok_menipis_list)): ?>
   209|    <div class="card animate-in" style="margin-top:var(--space-lg);">
   210|        <div class="card-header">
   211|            <h3><i class="fas fa-exclamation-circle text-warning"></i> Stok Menipis</h3>
   212|            <a href="barang.php?filter=menipis" class="btn btn-ghost btn-sm">Lihat Semua</a>
   213|        </div>
   214|        <div class="card-body no-pad">
   215|            <div class="table-container">
   216|                <table class="table">
   217|                    <thead>
   218|                        <tr>
   219|                            <th>Kode</th>
   220|                            <th>Nama Barang</th>
   221|                            <th>Kategori</th>
   222|                            <th>Stok</th>
   223|                            <th>Minimum</th>
   224|                            <th>Status</th>
   225|                        </tr>
   226|                    </thead>
   227|                    <tbody>
   228|                        <?php foreach ($stok_menipis_list as $s): ?>
   229|                        <tr>
   230|                            <td><code class="text-mono"><?= sanitize($s['kode_barang']) ?></code></td>
   231|                            <td class="fw-600"><?= sanitize($s['nama_barang']) ?></td>
   232|                            <td class="text-muted"><?= sanitize($s['nama_kategori'] ?? '-') ?></td>
   233|                            <td><strong class="text-danger"><?= $s['stok'] ?></strong></td>
   234|                            <td><?= $s['stok_minimum'] ?></td>
   235|                            <td>
   236|                                <?php if ($s['stok'] == 0): ?>
   237|                                    <span class="badge badge-danger">Habis</span>
   238|                                <?php else: ?>
   239|                                    <span class="badge badge-warning">Menipis</span>
   240|                                <?php endif; ?>
   241|                            </td>
   242|                        </tr>
   243|                        <?php endforeach; ?>
   244|                    </tbody>
   245|                </table>
   246|            </div>
   247|        </div>
   248|    </div>
   249|    <?php endif; ?>
   250|</div>
   251|
</body>
</html>
