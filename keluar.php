     1|<?php
     2|// keluar.php - Barang Keluar
     3|require_once 'config/database.php';
     4|requireLogin();

$page_title = 'Barang Keluar — Inventaris Bengkel Jaya';
     5|
     6|if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     7|    $action = $_POST['action'] ?? '';
     8|    
     9|    if ($action === 'tambah') {
    10|        // Cek stok cukup
    11|        $stmt = $pdo->prepare("SELECT stok, nama_barang FROM barang WHERE id = ?");
    12|        $stmt->execute([$_POST['barang_id']]);
    13|        $barang = $stmt->fetch();
    14|        
    15|        $jumlah = (int)$_POST['jumlah'];
    16|        
    17|        if ($barang['stok'] < $jumlah) {
    18|            setFlash('error', "Stok tidak cukup! Stok {$barang['nama_barang']} tersisa: {$barang['stok']}");
    19|            redirect('keluar.php');
    20|        }
    21|        
    22|        $no_transaksi = generateNoTransaksi('BK', $pdo, 'barang_keluar');
    23|        $harga = (int)$_POST['harga_satuan'];
    24|        $total = $jumlah * $harga;
    25|        
    26|        $stmt = $pdo->prepare("INSERT INTO barang_keluar (no_transaksi, barang_id, jumlah, harga_satuan, total_harga, tanggal_keluar, tujuan, penerima, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    27|        $stmt->execute([
    28|            $no_transaksi, $_POST['barang_id'], $jumlah, $harga, $total,
    29|            $_POST['tanggal_keluar'], $_POST['tujuan'], $_POST['penerima'], $_POST['keterangan'], $_SESSION['user_id']
    30|        ]);
    31|        
    32|        // Kurangi stok
    33|        $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE id = ?");
    34|        $stmt->execute([$jumlah, $_POST['barang_id']]);
    35|        
    36|        setFlash('success', "Barang keluar berhasil dicatat. No: $no_transaksi");
    37|        redirect('keluar.php');
    38|    }
    39|    
    40|    if ($action === 'hapus') {
    41|        $stmt = $pdo->prepare("SELECT barang_id, jumlah FROM barang_keluar WHERE id = ?");
    42|        $stmt->execute([$_POST['id']]);
    43|        $bk = $stmt->fetch();
    44|        
    45|        if ($bk) {
    46|            $stmt = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
    47|            $stmt->execute([$bk['jumlah'], $bk['barang_id']]);
    48|        }
    49|        
    50|        $stmt = $pdo->prepare("DELETE FROM barang_keluar WHERE id=?");
    51|        $stmt->execute([$_POST['id']]);
    52|        setFlash('success', 'Data barang keluar berhasil dihapus (stok dikembalikan).');
    53|        redirect('keluar.php');
    54|    }
    55|}
    56|
    57|$search = $_GET['q'] ?? '';
    58|$tanggal_dari = $_GET['dari'] ?? '';
    59|$tanggal_sampai = $_GET['sampai'] ?? '';
    60|
    61|$where = ["1=1"];
    62|$params = [];
    63|
    64|if ($search) {
    65|    $where[] = "(bk.no_transaksi LIKE ? OR b.nama_barang LIKE ? OR bk.tujuan LIKE ?)";
    66|    $params[] = "%$search%";
    67|    $params[] = "%$search%";
    68|    $params[] = "%$search%";
    69|}
    70|if ($tanggal_dari) {
    71|    $where[] = "bk.tanggal_keluar >= ?";
    72|    $params[] = $tanggal_dari;
    73|}
    74|if ($tanggal_sampai) {
    75|    $where[] = "bk.tanggal_keluar <= ?";
    76|    $params[] = $tanggal_sampai;
    77|}
    78|
    79|$where_sql = implode(' AND ', $where);
    80|
    81|$page = max(1, (int)($_GET['page'] ?? 1));
    82|$per_page = 15;
    83|$offset = ($page - 1) * $per_page;
    84|
    85|$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang_keluar bk JOIN barang b ON bk.barang_id = b.id WHERE $where_sql");
    86|$stmt->execute($params);
    87|$total = $stmt->fetch()['cnt'];
    88|$total_pages = ceil($total / $per_page);
    89|
    90|$stmt = $pdo->prepare("
    91|    SELECT bk.*, b.kode_barang, b.nama_barang, b.satuan, u.nama_lengkap 
    92|    FROM barang_keluar bk 
    93|    JOIN barang b ON bk.barang_id = b.id 
    94|    LEFT JOIN users u ON bk.user_id = u.id 
    95|    WHERE $where_sql 
    96|    ORDER BY bk.created_at DESC 
    97|    LIMIT $per_page OFFSET $offset
    98|");
    99|$stmt->execute($params);
   100|$keluar_list = $stmt->fetchAll();
   101|
   102|$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) as total FROM barang_keluar bk JOIN barang b ON bk.barang_id = b.id WHERE $where_sql");
   103|$stmt->execute($params);
   104|$total_nilai = $stmt->fetch()['total'];
   105|
   106|$barang_list = $pdo->query("SELECT id, kode_barang, nama_barang, satuan, stok FROM barang WHERE aktif = 1 AND stok > 0 ORDER BY kode_barang")->fetchAll();
   107|
   108|<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
   109|?>
   110|
   111|<div class="main-content">
   112|    <div class="page-header">
   113|        <div>
   114|            <h1>Barang Keluar</h1>
   115|            <div class="page-header-sub">Catat pengeluaran barang dari gudang</div>
   116|        </div>
   117|        <div class="page-header-actions">
   118|            <button onclick="openModal()" class="btn btn-accent">
   119|                <i class="fas fa-plus"></i> Tambah Transaksi
   120|            </button>
   121|        </div>
   122|    </div>
   123|
   124|    <?php if ($msg = flash('success')): ?>
   125|        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
   126|    <?php endif; ?>
   127|    <?php if ($msg = flash('error')): ?>
   128|        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $msg ?></div>
   129|    <?php endif; ?>
   130|
   131|    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:var(--space-lg);">
   132|        <div class="stat-card">
   133|            <div class="stat-card-label">Total Transaksi</div>
   134|            <div class="stat-card-value" style="font-size:1.5rem;"><?= $total ?></div>
   135|        </div>
   136|        <div class="stat-card">
   137|            <div class="stat-card-label">Total Nilai Keluar</div>
   138|            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($total_nilai) ?></div>
   139|        </div>
   140|    </div>
   141|
   142|    <div class="card">
   143|        <div class="card-header">
   144|            <form method="GET" class="toolbar" style="width:100%;margin:0;">
   145|                <div class="search-box">
   146|                    <i class="fas fa-search"></i>
   147|                    <input type="text" name="q" placeholder="Cari no transaksi, barang, tujuan..." value="<?= sanitize($search) ?>">
   148|                </div>
   149|                <input type="date" name="dari" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_dari) ?>">
   150|                <input type="date" name="sampai" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_sampai) ?>">
   151|                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
   152|                <?php if ($search || $tanggal_dari || $tanggal_sampai): ?>
   153|                    <a href="keluar.php" class="btn btn-outline btn-sm">Reset</a>
   154|                <?php endif; ?>
   155|            </form>
   156|        </div>
   157|
   158|        <div class="card-body no-pad">
   159|            <div class="table-container">
   160|                <table class="table">
   161|                    <thead>
   162|                        <tr>
   163|                            <th>No. Transaksi</th>
   164|                            <th>Tanggal</th>
   165|                            <th>Barang</th>
   166|                            <th>Jumlah</th>
   167|                            <th>Harga</th>
   168|                            <th>Total</th>
   169|                            <th>Tujuan</th>
   170|                            <th>Aksi</th>
   171|                        </tr>
   172|                    </thead>
   173|                    <tbody>
   174|                        <?php foreach ($keluar_list as $k): ?>
   175|                        <tr>
   176|                            <td><code class="text-mono"><?= sanitize($k['no_transaksi']) ?></code></td>
   177|                            <td class="text-muted"><?= tglPendek($k['tanggal_keluar']) ?></td>
   178|                            <td>
   179|                                <div class="fw-600"><?= sanitize($k['nama_barang']) ?></div>
   180|                                <small class="text-muted"><?= sanitize($k['kode_barang']) ?></small>
   181|                            </td>
   182|                            <td><span class="badge badge-danger">-<?= $k['jumlah'] ?> <?= sanitize($k['satuan']) ?></span></td>
   183|                            <td class="text-muted"><?= rupiah($k['harga_satuan']) ?></td>
   184|                            <td class="fw-600"><?= rupiah($k['total_harga']) ?></td>
   185|                            <td class="text-muted"><?= sanitize($k['tujuan'] ?? '-') ?></td>
   186|                            <td>
   187|                                <button onclick="if(confirm('Yakin hapus? Stok akan dikembalikan.')) document.getElementById('hapus-<?= $k['id'] ?>').submit()" class="btn btn-ghost btn-sm">
   188|                                    <i class="fas fa-trash text-danger"></i>
   189|                                </button>
   190|                                <form id="hapus-<?= $k['id'] ?>" method="POST" style="display:none;">
   191|                                    <input type="hidden" name="action" value="hapus">
   192|                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
   193|                                </form>
   194|                            </td>
   195|                        </tr>
   196|                        <?php endforeach; ?>
   197|                        <?php if (empty($keluar_list)): ?>
   198|                        <tr>
   199|                            <td colspan="8">
   200|                                <div class="empty-state">
   201|                                    <div class="empty-state-icon"><i class="fas fa-arrow-up"></i></div>
   202|                                    <h3>Belum ada transaksi keluar</h3>
   203|                                    <p>Klik tombol "Tambah Transaksi" untuk mencatat pengeluaran barang.</p>
   204|                                </div>
   205|                            </td>
   206|                        </tr>
   207|                        <?php endif; ?>
   208|                    </tbody>
   209|                </table>
   210|            </div>
   211|        </div>
   212|
   213|        <?php if ($total_pages > 1): ?>
   214|        <div class="pagination">
   215|            <div class="pagination-info">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> dari <?= $total ?></div>
   216|            <div class="pagination-buttons">
   217|                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">←</a>
   218|                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
   219|                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
   220|                <?php endfor; ?>
   221|                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">→</a>
   222|            </div>
   223|        </div>
   224|        <?php endif; ?>
   225|    </div>
   226|</div>
   227|
   228|<!-- Modal -->
   229|<div class="modal-overlay" id="formModal">
   230|    <div class="modal">
   231|        <div class="modal-header">
   232|            <h3>Tambah Barang Keluar</h3>
   233|            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
   234|        </div>
   235|        <form method="POST">
   236|            <div class="modal-body">
   237|                <input type="hidden" name="action" value="tambah">
   238|                
   239|                <div class="form-group">
   240|                    <label class="form-label">Barang <span class="required">*</span></label>
   241|                    <select name="barang_id" class="form-select" required onchange="updateStok(this)">
   242|                        <option value="">-- Pilih Barang --</option>
   243|                        <?php foreach ($barang_list as $b): ?>
   244|                            <option value="<?= $b['id'] ?>" data-stok="<?= $b['stok'] ?>" data-harga="0">
   245|                                <?= sanitize($b['kode_barang']) ?> - <?= sanitize($b['nama_barang']) ?> (Stok: <?= $b['stok'] ?>)
   246|                            </option>
   247|                        <?php endforeach; ?>
   248|                    </select>
   249|                    <div class="form-hint" id="stokInfo">Pilih barang untuk melihat stok tersedia</div>
   250|                </div>
   251|                
   252|                <div class="form-row">
   253|                    <div class="form-group">
   254|                        <label class="form-label">Jumlah <span class="required">*</span></label>
   255|                        <input type="number" name="jumlah" id="formJumlah" class="form-control" min="1" required>
   256|                    </div>
   257|                    <div class="form-group">
   258|                        <label class="form-label">Tanggal Keluar <span class="required">*</span></label>
   259|                        <input type="date" name="tanggal_keluar" class="form-control" value="<?= date('Y-m-d') ?>" required>
   260|                    </div>
   261|                </div>
   262|                
   263|                <div class="form-group">
   264|                    <label class="form-label">Harga Satuan (Rp)</label>
   265|                    <input type="number" name="harga_satuan" class="form-control" value="0" min="0">
   266|                </div>
   267|                
   268|                <div class="form-row">
   269|                    <div class="form-group">
   270|                        <label class="form-label">Tujuan</label>
   271|                        <input type="text" name="tujuan" class="form-control" placeholder="Bengkel, Pelanggan, dll">
   272|                    </div>
   273|                    <div class="form-group">
   274|                        <label class="form-label">Penerima</label>
   275|                        <input type="text" name="penerima" class="form-control" placeholder="Nama penerima">
   276|                    </div>
   277|                </div>
   278|                
   279|                <div class="form-group">
   280|                    <label class="form-label">Keterangan</label>
   281|                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan..."></textarea>
   282|                </div>
   283|            </div>
   284|            <div class="modal-footer">
   285|                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
   286|                <button type="submit" class="btn btn-accent">Simpan</button>
   287|            </div>
   288|        </form>
   289|    </div>
   290|</div>
   291|
   292|<script>
   293|function openModal() { document.getElementById('formModal').classList.add('show'); }
   294|function closeModal() { document.getElementById('formModal').classList.remove('show'); }
   295|function updateStok(select) {
   296|    const option = select.options[select.selectedIndex];
   297|    const stok = option.dataset.stok || 0;
   298|    document.getElementById('stokInfo').textContent = stok > 0 ? 'Stok tersedia: ' + stok : '';
   299|    document.getElementById('formJumlah').max = stok;
   300|}
   301|document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
   302|document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
   303|</script>
   304|
</body>
</html>
