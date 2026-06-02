     1|<?php
     2|// laporan.php - Laporan Inventaris
     3|require_once 'config/database.php';
     4|requireLogin();

$page_title = 'Laporan — Inventaris Bengkel Jaya';
     5|
     6|$bulan = $_GET['bulan'] ?? date('m');
     7|$tahun = $_GET['tahun'] ?? date('Y');
     8|
     9|// Ringkasan
    10|$stats = [];
    11|
    12|$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM barang WHERE aktif = 1");
    13|$stats['total_barang'] = $stmt->fetch()['cnt'];
    14|
    15|$stmt = $pdo->query("SELECT COALESCE(SUM(stok), 0) as total FROM barang WHERE aktif = 1");
    16|$stats['total_stok'] = $stmt->fetch()['total'];
    17|
    18|$stmt = $pdo->query("SELECT COALESCE(SUM(stok * harga_beli), 0) as total FROM barang WHERE aktif = 1");
    19|$stats['nilai_stok'] = $stmt->fetch()['total'];
    20|
    21|$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah), 0) as qty, COALESCE(SUM(total_harga), 0) as nilai FROM barang_masuk WHERE MONTH(tanggal_masuk) = ? AND YEAR(tanggal_masuk) = ?");
    22|$stmt->execute([$bulan, $tahun]);
    23|$masuk_bulan = $stmt->fetch();
    24|
    25|$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(jumlah), 0) as qty, COALESCE(SUM(total_harga), 0) as nilai FROM barang_keluar WHERE MONTH(tanggal_keluar) = ? AND YEAR(tanggal_keluar) = ?");
    26|$stmt->execute([$bulan, $tahun]);
    27|$keluar_bulan = $stmt->fetch();
    28|
    29|// Per kategori
    30|$per_kategori = $pdo->query("
    31|    SELECT k.nama_kategori, COUNT(b.id) as jumlah_item, COALESCE(SUM(b.stok), 0) as total_stok, COALESCE(SUM(b.stok * b.harga_beli), 0) as nilai_stok
    32|    FROM kategori k 
    33|    LEFT JOIN barang b ON k.id = b.kategori_id AND b.aktif = 1
    34|    GROUP BY k.id 
    35|    ORDER BY k.nama_kategori
    36|")->fetchAll();
    37|
    38|// Stok menipis
    39|$stok_menipis = $pdo->query("
    40|    SELECT b.*, k.nama_kategori 
    41|    FROM barang b 
    42|    LEFT JOIN kategori k ON b.kategori_id = k.id 
    43|    WHERE b.stok <= b.stok_minimum AND b.aktif = 1
    44|    ORDER BY b.stok ASC
    45|")->fetchAll();
    46|
    47|// Barang paling sering keluar
    48|$populer = $pdo->prepare("
    49|    SELECT b.kode_barang, b.nama_barang, SUM(bk.jumlah) as total_keluar
    50|    FROM barang_keluar bk 
    51|    JOIN barang b ON bk.barang_id = b.id 
    52|    WHERE MONTH(bk.tanggal_keluar) = ? AND YEAR(bk.tanggal_keluar) = ?
    53|    GROUP BY bk.barang_id 
    54|    ORDER BY total_keluar DESC 
    55|    LIMIT 5
    56|");
    57|$populer->execute([$bulan, $tahun]);
    58|$barang_populer = $populer->fetchAll();
    59|
    60|<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
    61|?>
    62|
    63|<div class="main-content">
    64|    <div class="page-header">
    65|        <div>
    66|            <h1>Laporan Inventaris</h1>
    67|            <div class="page-header-sub">Ringkasan data persediaan barang gudang</div>
    68|        </div>
    69|        <div class="page-header-actions">
    70|            <form method="GET" class="d-flex gap-1">
    71|                <select name="bulan" class="form-select" style="width:auto;">
    72|                    <?php for ($m = 1; $m <= 12; $m++): ?>
    73|                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $bulan == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
    74|                            <?= date('F', mktime(0, 0, 0, $m)) ?>
    75|                        </option>
    76|                    <?php endfor; ?>
    77|                </select>
    78|                <select name="tahun" class="form-select" style="width:auto;">
    79|                    <?php for ($y = 2025; $y <= 2027; $y++): ?>
    80|                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
    81|                    <?php endfor; ?>
    82|                </select>
    83|                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
    84|            </form>
    85|        </div>
    86|    </div>
    87|
    88|    <!-- Ringkasan Stok -->
    89|    <div class="stat-grid animate-in">
    90|        <div class="stat-card">
    91|            <div class="stat-card-header">
    92|                <span class="stat-card-label">Total Jenis Barang</span>
    93|                <div class="stat-card-icon blue"><i class="fas fa-boxes-stacked"></i></div>
    94|            </div>
    95|            <div class="stat-card-value"><?= $stats['total_barang'] ?></div>
    96|        </div>
    97|        <div class="stat-card">
    98|            <div class="stat-card-header">
    99|                <span class="stat-card-label">Total Stok</span>
   100|                <div class="stat-card-icon green"><i class="fas fa-warehouse"></i></div>
   101|            </div>
   102|            <div class="stat-card-value"><?= number_format($stats['total_stok']) ?></div>
   103|        </div>
   104|        <div class="stat-card">
   105|            <div class="stat-card-header">
   106|                <span class="stat-card-label">Nilai Stok (Modal)</span>
   107|                <div class="stat-card-icon blue"><i class="fas fa-coins"></i></div>
   108|            </div>
   109|            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($stats['nilai_stok']) ?></div>
   110|        </div>
   111|        <div class="stat-card">
   112|            <div class="stat-card-header">
   113|                <span class="stat-card-label">Stok Menipis</span>
   114|                <div class="stat-card-icon <?= count($stok_menipis) > 0 ? 'red' : 'green' ?>">
   115|                    <i class="fas fa-exclamation-triangle"></i>
   116|                </div>
   117|            </div>
   118|            <div class="stat-card-value <?= count($stok_menipis) > 0 ? 'text-danger' : '' ?>"><?= count($stok_menipis) ?></div>
   119|        </div>
   120|    </div>
   121|
   122|    <!-- Transaksi Bulan Ini -->
   123|    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);margin-bottom:var(--space-lg);" class="animate-in">
   124|        <div class="card">
   125|            <div class="card-header">
   126|                <h3><i class="fas fa-arrow-down text-success"></i> Barang Masuk - <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
   127|            </div>
   128|            <div class="card-body">
   129|                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-md);text-align:center;">
   130|                    <div>
   131|                        <div class="text-muted" style="font-size:0.8125rem;">Transaksi</div>
   132|                        <div class="fw-700" style="font-size:1.25rem;"><?= $masuk_bulan['cnt'] ?></div>
   133|                    </div>
   134|                    <div>
   135|                        <div class="text-muted" style="font-size:0.8125rem;">Jumlah Item</div>
   136|                        <div class="fw-700" style="font-size:1.25rem;"><?= number_format($masuk_bulan['qty']) ?></div>
   137|                    </div>
   138|                    <div>
   139|                        <div class="text-muted" style="font-size:0.8125rem;">Nilai</div>
   140|                        <div class="fw-700 text-success" style="font-size:1rem;"><?= rupiah($masuk_bulan['nilai']) ?></div>
   141|                    </div>
   142|                </div>
   143|            </div>
   144|        </div>
   145|        <div class="card">
   146|            <div class="card-header">
   147|                <h3><i class="fas fa-arrow-up text-danger"></i> Barang Keluar - <?= date('F Y', mktime(0,0,0,(int)$bulan,1,(int)$tahun)) ?></h3>
   148|            </div>
   149|            <div class="card-body">
   150|                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-md);text-align:center;">
   151|                    <div>
   152|                        <div class="text-muted" style="font-size:0.8125rem;">Transaksi</div>
   153|                        <div class="fw-700" style="font-size:1.25rem;"><?= $keluar_bulan['cnt'] ?></div>
   154|                    </div>
   155|                    <div>
   156|                        <div class="text-muted" style="font-size:0.8125rem;">Jumlah Item</div>
   157|                        <div class="fw-700" style="font-size:1.25rem;"><?= number_format($keluar_bulan['qty']) ?></div>
   158|                    </div>
   159|                    <div>
   160|                        <div class="text-muted" style="font-size:0.8125rem;">Nilai</div>
   161|                        <div class="fw-700 text-danger" style="font-size:1rem;"><?= rupiah($keluar_bulan['nilai']) ?></div>
   162|                    </div>
   163|                </div>
   164|            </div>
   165|        </div>
   166|    </div>
   167|
   168|    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);" class="animate-in">
   169|        <!-- Per Kategori -->
   170|        <div class="card">
   171|            <div class="card-header">
   172|                <h3><i class="fas fa-tags"></i> Stok per Kategori</h3>
   173|            </div>
   174|            <div class="card-body no-pad">
   175|                <table class="table">
   176|                    <thead>
   177|                        <tr>
   178|                            <th>Kategori</th>
   179|                            <th>Jenis</th>
   180|                            <th>Stok</th>
   181|                            <th>Nilai</th>
   182|                        </tr>
   183|                    </thead>
   184|                    <tbody>
   185|                        <?php foreach ($per_kategori as $pk): ?>
   186|                        <tr>
   187|                            <td class="fw-600"><?= sanitize($pk['nama_kategori']) ?></td>
   188|                            <td><?= $pk['jumlah_item'] ?></td>
   189|                            <td><?= number_format($pk['total_stok']) ?></td>
   190|                            <td class="text-muted"><?= rupiah($pk['nilai_stok']) ?></td>
   191|                        </tr>
   192|                        <?php endforeach; ?>
   193|                    </tbody>
   194|                </table>
   195|            </div>
   196|        </div>
   197|
   198|        <!-- Barang Populer -->
   199|        <div class="card">
   200|            <div class="card-header">
   201|                <h3><i class="fas fa-fire text-warning"></i> Paling Sering Keluar</h3>
   202|            </div>
   203|            <div class="card-body no-pad">
   204|                <?php if (!empty($barang_populer)): ?>
   205|                <table class="table">
   206|                    <thead>
   207|                        <tr>
   208|                            <th>Barang</th>
   209|                            <th>Total Keluar</th>
   210|                        </tr>
   211|                    </thead>
   212|                    <tbody>
   213|                        <?php foreach ($barang_populer as $bp): ?>
   214|                        <tr>
   215|                            <td>
   216|                                <div class="fw-600"><?= sanitize($bp['nama_barang']) ?></div>
   217|                                <small class="text-muted"><?= sanitize($bp['kode_barang']) ?></small>
   218|                            </td>
   219|                            <td><span class="badge badge-danger"><?= $bp['total_keluar'] ?> item</span></td>
   220|                        </tr>
   221|                        <?php endforeach; ?>
   222|                    </tbody>
   223|                </table>
   224|                <?php else: ?>
   225|                <div class="empty-state" style="padding:32px;">
   226|                    <p class="text-muted">Belum ada data transaksi keluar bulan ini</p>
   227|                </div>
   228|                <?php endif; ?>
   229|            </div>
   230|        </div>
   231|    </div>
   232|
   233|    <!-- Stok Menipis -->
   234|    <?php if (!empty($stok_menipis)): ?>
   235|    <div class="card animate-in" style="margin-top:var(--space-lg);">
   236|        <div class="card-header">
   237|            <h3><i class="fas fa-exclamation-circle text-danger"></i> Barang Stok Menipis (<?= count($stok_menipis) ?>)</h3>
   238|        </div>
   239|        <div class="card-body no-pad">
   240|            <table class="table">
   241|                <thead>
   242|                    <tr>
   243|                        <th>Kode</th>
   244|                        <th>Nama Barang</th>
   245|                        <th>Kategori</th>
   246|                        <th>Stok</th>
   247|                        <th>Minimum</th>
   248|                        <th>Status</th>
   249|                    </tr>
   250|                </thead>
   251|                <tbody>
   252|                    <?php foreach ($stok_menipis as $s): ?>
   253|                    <tr>
   254|                        <td><code class="text-mono"><?= sanitize($s['kode_barang']) ?></code></td>
   255|                        <td class="fw-600"><?= sanitize($s['nama_barang']) ?></td>
   256|                        <td class="text-muted"><?= sanitize($s['nama_kategori'] ?? '-') ?></td>
   257|                        <td><strong class="text-danger"><?= $s['stok'] ?></strong></td>
   258|                        <td><?= $s['stok_minimum'] ?></td>
   259|                        <td>
   260|                            <?php if ($s['stok'] == 0): ?>
   261|                                <span class="badge badge-danger">Habis</span>
   262|                            <?php else: ?>
   263|                                <span class="badge badge-warning">Menipis</span>
   264|                            <?php endif; ?>
   265|                        </td>
   266|                    </tr>
   267|                    <?php endforeach; ?>
   268|                </tbody>
   269|            </table>
   270|        </div>
   271|    </div>
   272|    <?php endif; ?>
   273|</div>
   274|
</body>
</html>
