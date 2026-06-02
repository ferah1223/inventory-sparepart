     1|<?php
     2|// masuk.php - Barang Masuk
     3|require_once 'config/database.php';
     4|requireLogin();

$page_title = 'Barang Masuk — Inventaris Bengkel Jaya';
     5|
     6|if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     7|    $action = $_POST['action'] ?? '';
     8|    
     9|    if ($action === 'tambah') {
    10|        $no_transaksi = generateNoTransaksi('BM', $pdo, 'barang_masuk');
    11|        $jumlah = (int)$_POST['jumlah'];
    12|        $harga = (int)$_POST['harga_satuan'];
    13|        $total = $jumlah * $harga;
    14|        
    15|        $stmt = $pdo->prepare("INSERT INTO barang_masuk (no_transaksi, barang_id, jumlah, harga_satuan, total_harga, tanggal_masuk, supplier, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    16|        $stmt->execute([
    17|            $no_transaksi, $_POST['barang_id'], $jumlah, $harga, $total,
    18|            $_POST['tanggal_masuk'], $_POST['supplier'], $_POST['keterangan'], $_SESSION['user_id']
    19|        ]);
    20|        
    21|        // Update stok barang
    22|        $stmt = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
    23|        $stmt->execute([$jumlah, $_POST['barang_id']]);
    24|        
    25|        setFlash('success', "Barang masuk berhasil dicatat. No: $no_transaksi");
    26|        redirect('masuk.php');
    27|    }
    28|    
    29|    if ($action === 'hapus') {
    30|        // Kurangi stok dulu
    31|        $stmt = $pdo->prepare("SELECT barang_id, jumlah FROM barang_masuk WHERE id = ?");
    32|        $stmt->execute([$_POST['id']]);
    33|        $bm = $stmt->fetch();
    34|        
    35|        if ($bm) {
    36|            $stmt = $pdo->prepare("UPDATE barang SET stok = GREATEST(stok - ?, 0) WHERE id = ?");
    37|            $stmt->execute([$bm['jumlah'], $bm['barang_id']]);
    38|        }
    39|        
    40|        $stmt = $pdo->prepare("DELETE FROM barang_masuk WHERE id=?");
    41|        $stmt->execute([$_POST['id']]);
    42|        setFlash('success', 'Data barang masuk berhasil dihapus.');
    43|        redirect('masuk.php');
    44|    }
    45|}
    46|
    47|$search = $_GET['q'] ?? '';
    48|$tanggal_dari = $_GET['dari'] ?? '';
    49|$tanggal_sampai = $_GET['sampai'] ?? '';
    50|
    51|$where = ["1=1"];
    52|$params = [];
    53|
    54|if ($search) {
    55|    $where[] = "(bm.no_transaksi LIKE ? OR b.nama_barang LIKE ? OR bm.supplier LIKE ?)";
    56|    $params[] = "%$search%";
    57|    $params[] = "%$search%";
    58|    $params[] = "%$search%";
    59|}
    60|if ($tanggal_dari) {
    61|    $where[] = "bm.tanggal_masuk >= ?";
    62|    $params[] = $tanggal_dari;
    63|}
    64|if ($tanggal_sampai) {
    65|    $where[] = "bm.tanggal_masuk <= ?";
    66|    $params[] = $tanggal_sampai;
    67|}
    68|
    69|$where_sql = implode(' AND ', $where);
    70|
    71|$page = max(1, (int)($_GET['page'] ?? 1));
    72|$per_page = 15;
    73|$offset = ($page - 1) * $per_page;
    74|
    75|$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang_masuk bm JOIN barang b ON bm.barang_id = b.id WHERE $where_sql");
    76|$stmt->execute($params);
    77|$total = $stmt->fetch()['cnt'];
    78|$total_pages = ceil($total / $per_page);
    79|
    80|$stmt = $pdo->prepare("
    81|    SELECT bm.*, b.kode_barang, b.nama_barang, b.satuan, u.nama_lengkap 
    82|    FROM barang_masuk bm 
    83|    JOIN barang b ON bm.barang_id = b.id 
    84|    LEFT JOIN users u ON bm.user_id = u.id 
    85|    WHERE $where_sql 
    86|    ORDER BY bm.created_at DESC 
    87|    LIMIT $per_page OFFSET $offset
    88|");
    89|$stmt->execute($params);
    90|$masuk_list = $stmt->fetchAll();
    91|
    92|// Total nilai masuk
    93|$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) as total FROM barang_masuk bm JOIN barang b ON bm.barang_id = b.id WHERE $where_sql");
    94|$stmt->execute($params);
    95|$total_nilai = $stmt->fetch()['total'];
    96|
    97|// Barang untuk dropdown
    98|$barang_list = $pdo->query("SELECT id, kode_barang, nama_barang, satuan FROM barang WHERE aktif = 1 ORDER BY kode_barang")->fetchAll();
    99|
   100|<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
   101|?>
   102|
   103|<div class="main-content">
   104|    <div class="page-header">
   105|        <div>
   106|            <h1>Barang Masuk</h1>
   107|            <div class="page-header-sub">Catat penerimaan barang ke gudang</div>
   108|        </div>
   109|        <div class="page-header-actions">
   110|            <button onclick="openModal()" class="btn btn-accent">
   111|                <i class="fas fa-plus"></i> Tambah Transaksi
   112|            </button>
   113|        </div>
   114|    </div>
   115|
   116|    <?php if ($msg = flash('success')): ?>
   117|        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
   118|    <?php endif; ?>
   119|
   120|    <!-- Summary -->
   121|    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:var(--space-lg);">
   122|        <div class="stat-card">
   123|            <div class="stat-card-label">Total Transaksi</div>
   124|            <div class="stat-card-value" style="font-size:1.5rem;"><?= $total ?></div>
   125|        </div>
   126|        <div class="stat-card">
   127|            <div class="stat-card-label">Total Nilai Masuk</div>
   128|            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($total_nilai) ?></div>
   129|        </div>
   130|    </div>
   131|
   132|    <div class="card">
   133|        <div class="card-header">
   134|            <form method="GET" class="toolbar" style="width:100%;margin:0;">
   135|                <div class="search-box">
   136|                    <i class="fas fa-search"></i>
   137|                    <input type="text" name="q" placeholder="Cari no transaksi, barang, supplier..." value="<?= sanitize($search) ?>">
   138|                </div>
   139|                <input type="date" name="dari" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_dari) ?>" placeholder="Dari">
   140|                <input type="date" name="sampai" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_sampai) ?>" placeholder="Sampai">
   141|                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
   142|                <?php if ($search || $tanggal_dari || $tanggal_sampai): ?>
   143|                    <a href="masuk.php" class="btn btn-outline btn-sm">Reset</a>
   144|                <?php endif; ?>
   145|            </form>
   146|        </div>
   147|
   148|        <div class="card-body no-pad">
   149|            <div class="table-container">
   150|                <table class="table">
   151|                    <thead>
   152|                        <tr>
   153|                            <th>No. Transaksi</th>
   154|                            <th>Tanggal</th>
   155|                            <th>Barang</th>
   156|                            <th>Jumlah</th>
   157|                            <th>Harga Satuan</th>
   158|                            <th>Total</th>
   159|                            <th>Supplier</th>
   160|                            <th>Aksi</th>
   161|                        </tr>
   162|                    </thead>
   163|                    <tbody>
   164|                        <?php foreach ($masuk_list as $m): ?>
   165|                        <tr>
   166|                            <td><code class="text-mono"><?= sanitize($m['no_transaksi']) ?></code></td>
   167|                            <td class="text-muted"><?= tglPendek($m['tanggal_masuk']) ?></td>
   168|                            <td>
   169|                                <div class="fw-600"><?= sanitize($m['nama_barang']) ?></div>
   170|                                <small class="text-muted"><?= sanitize($m['kode_barang']) ?></small>
   171|                            </td>
   172|                            <td><span class="badge badge-success">+<?= $m['jumlah'] ?> <?= sanitize($m['satuan']) ?></span></td>
   173|                            <td class="text-muted"><?= rupiah($m['harga_satuan']) ?></td>
   174|                            <td class="fw-600"><?= rupiah($m['total_harga']) ?></td>
   175|                            <td class="text-muted"><?= sanitize($m['supplier'] ?? '-') ?></td>
   176|                            <td>
   177|                                <button onclick="if(confirm('Yakin hapus?')) document.getElementById('hapus-<?= $m['id'] ?>').submit()" class="btn btn-ghost btn-sm">
   178|                                    <i class="fas fa-trash text-danger"></i>
   179|                                </button>
   180|                                <form id="hapus-<?= $m['id'] ?>" method="POST" style="display:none;">
   181|                                    <input type="hidden" name="action" value="hapus">
   182|                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
   183|                                </form>
   184|                            </td>
   185|                        </tr>
   186|                        <?php endforeach; ?>
   187|                        <?php if (empty($masuk_list)): ?>
   188|                        <tr>
   189|                            <td colspan="8">
   190|                                <div class="empty-state">
   191|                                    <div class="empty-state-icon"><i class="fas fa-arrow-down"></i></div>
   192|                                    <h3>Belum ada transaksi masuk</h3>
   193|                                    <p>Klik tombol "Tambah Transaksi" untuk mencatat penerimaan barang.</p>
   194|                                </div>
   195|                            </td>
   196|                        </tr>
   197|                        <?php endif; ?>
   198|                    </tbody>
   199|                </table>
   200|            </div>
   201|        </div>
   202|
   203|        <?php if ($total_pages > 1): ?>
   204|        <div class="pagination">
   205|            <div class="pagination-info">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> dari <?= $total ?></div>
   206|            <div class="pagination-buttons">
   207|                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">←</a>
   208|                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
   209|                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
   210|                <?php endfor; ?>
   211|                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">→</a>
   212|            </div>
   213|        </div>
   214|        <?php endif; ?>
   215|    </div>
   216|</div>
   217|
   218|<!-- Modal Tambah -->
   219|<div class="modal-overlay" id="formModal">
   220|    <div class="modal">
   221|        <div class="modal-header">
   222|            <h3>Tambah Barang Masuk</h3>
   223|            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
   224|        </div>
   225|        <form method="POST">
   226|            <div class="modal-body">
   227|                <input type="hidden" name="action" value="tambah">
   228|                
   229|                <div class="form-group">
   230|                    <label class="form-label">Barang <span class="required">*</span></label>
   231|                    <select name="barang_id" class="form-select" required>
   232|                        <option value="">-- Pilih Barang --</option>
   233|                        <?php foreach ($barang_list as $b): ?>
   234|                            <option value="<?= $b['id'] ?>"><?= sanitize($b['kode_barang']) ?> - <?= sanitize($b['nama_barang']) ?></option>
   235|                        <?php endforeach; ?>
   236|                    </select>
   237|                </div>
   238|                
   239|                <div class="form-row">
   240|                    <div class="form-group">
   241|                        <label class="form-label">Jumlah <span class="required">*</span></label>
   242|                        <input type="number" name="jumlah" class="form-control" min="1" required>
   243|                    </div>
   244|                    <div class="form-group">
   245|                        <label class="form-label">Tanggal Masuk <span class="required">*</span></label>
   246|                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
   247|                    </div>
   248|                </div>
   249|                
   250|                <div class="form-group">
   251|                    <label class="form-label">Harga Satuan (Rp)</label>
   252|                    <input type="number" name="harga_satuan" class="form-control" value="0" min="0">
   253|                </div>
   254|                
   255|                <div class="form-group">
   256|                    <label class="form-label">Supplier</label>
   257|                    <input type="text" name="supplier" class="form-control" placeholder="Nama supplier/distributor">
   258|                </div>
   259|                
   260|                <div class="form-group">
   261|                    <label class="form-label">Keterangan</label>
   262|                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
   263|                </div>
   264|            </div>
   265|            <div class="modal-footer">
   266|                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
   267|                <button type="submit" class="btn btn-accent">Simpan</button>
   268|            </div>
   269|        </form>
   270|    </div>
   271|</div>
   272|
   273|<script>
   274|function openModal() { document.getElementById('formModal').classList.add('show'); }
   275|function closeModal() { document.getElementById('formModal').classList.remove('show'); }
   276|document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
   277|document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
   278|</script>
   279|
</body>
</html>
