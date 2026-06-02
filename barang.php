     1|<?php
     2|// barang.php - Data Barang (Spare Part)
     3|require_once 'config/database.php';
     4|requireLogin();

$page_title = 'Barang — Inventaris Bengkel Jaya';
     5|
     6|// Proses form
     7|if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     8|    $action = $_POST['action'] ?? '';
     9|    
    10|    if ($action === 'tambah') {
    11|        $stmt = $pdo->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan, stok, stok_minimum, harga_beli, harga_jual, lokasi_rak) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    12|        $stmt->execute([
    13|            $_POST['kode_barang'], $_POST['nama_barang'], $_POST['kategori_id'] ?: null,
    14|            $_POST['satuan'], 0, $_POST['stok_minimum'], $_POST['harga_beli'], $_POST['harga_jual'], $_POST['lokasi_rak']
    15|        ]);
    16|        setFlash('success', 'Barang berhasil ditambahkan.');
    17|        redirect('barang.php');
    18|    }
    19|    
    20|    if ($action === 'edit') {
    21|        $stmt = $pdo->prepare("UPDATE barang SET nama_barang=?, kategori_id=?, satuan=?, stok_minimum=?, harga_beli=?, harga_jual=?, lokasi_rak=? WHERE id=?");
    22|        $stmt->execute([
    23|            $_POST['nama_barang'], $_POST['kategori_id'] ?: null, $_POST['satuan'],
    24|            $_POST['stok_minimum'], $_POST['harga_beli'], $_POST['harga_jual'], $_POST['lokasi_rak'], $_POST['id']
    25|        ]);
    26|        setFlash('success', 'Data barang berhasil diupdate.');
    27|        redirect('barang.php');
    28|    }
    29|    
    30|    if ($action === 'hapus') {
    31|        $stmt = $pdo->prepare("DELETE FROM barang WHERE id=?");
    32|        $stmt->execute([$_POST['id']]);
    33|        setFlash('success', 'Barang berhasil dihapus.');
    34|        redirect('barang.php');
    35|    }
    36|}
    37|
    38|// Filter & Search
    39|$search = $_GET['q'] ?? '';
    40|$filter_kategori = $_GET['kategori'] ?? '';
    41|$filter_stok = $_GET['filter'] ?? '';
    42|
    43|$where = ["b.aktif = 1"];
    44|$params = [];
    45|
    46|if ($search) {
    47|    $where[] = "(b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
    48|    $params[] = "%$search%";
    49|    $params[] = "%$search%";
    50|}
    51|if ($filter_kategori) {
    52|    $where[] = "b.kategori_id = ?";
    53|    $params[] = $filter_kategori;
    54|}
    55|if ($filter_stok === 'menipis') {
    56|    $where[] = "b.stok <= b.stok_minimum";
    57|}
    58|
    59|$where_sql = implode(' AND ', $where);
    60|
    61|// Pagination
    62|$page = max(1, (int)($_GET['page'] ?? 1));
    63|$per_page = 15;
    64|$offset = ($page - 1) * $per_page;
    65|
    66|$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang b WHERE $where_sql");
    67|$stmt->execute($params);
    68|$total = $stmt->fetch()['cnt'];
    69|$total_pages = ceil($total / $per_page);
    70|
    71|$stmt = $pdo->prepare("
    72|    SELECT b.*, k.nama_kategori 
    73|    FROM barang b 
    74|    LEFT JOIN kategori k ON b.kategori_id = k.id 
    75|    WHERE $where_sql 
    76|    ORDER BY b.kode_barang ASC 
    77|    LIMIT $per_page OFFSET $offset
    78|");
    79|$stmt->execute($params);
    80|$barang_list = $stmt->fetchAll();
    81|
    82|// Kategori untuk dropdown
    83|$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();
    84|
    85|// Hitung total nilai stok
    86|$stmt = $pdo->query("SELECT COALESCE(SUM(stok * harga_beli), 0) as total FROM barang WHERE aktif = 1");
    87|$total_nilai_stok = $stmt->fetch()['total'];
    88|
    89|<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
    90|?>
    91|
    92|<div class="main-content">
    93|    <div class="page-header">
    94|        <div>
    95|            <h1>Data Barang</h1>
    96|            <div class="page-header-sub">Kelola data spare part sepeda motor</div>
    97|        </div>
    98|        <div class="page-header-actions">
    99|            <button onclick="openModal('tambah')" class="btn btn-accent">
   100|                <i class="fas fa-plus"></i> Tambah Barang
   101|            </button>
   102|        </div>
   103|    </div>
   104|
   105|    <?php if ($msg = flash('success')): ?>
   106|        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
   107|    <?php endif; ?>
   108|
   109|    <!-- Summary -->
   110|    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:var(--space-lg);">
   111|        <div class="stat-card">
   112|            <div class="stat-card-label">Total Jenis</div>
   113|            <div class="stat-card-value" style="font-size:1.5rem;"><?= $total ?></div>
   114|        </div>
   115|        <div class="stat-card">
   116|            <div class="stat-card-label">Total Stok</div>
   117|            <div class="stat-card-value" style="font-size:1.5rem;"><?= number_format(array_sum(array_column($barang_list, 'stok'))) ?></div>
   118|        </div>
   119|        <div class="stat-card">
   120|            <div class="stat-card-label">Nilai Stok</div>
   121|            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($total_nilai_stok) ?></div>
   122|        </div>
   123|    </div>
   124|
   125|    <!-- Filter & Search -->
   126|    <div class="card">
   127|        <div class="card-header">
   128|            <form method="GET" class="toolbar" style="width:100%;margin:0;">
   129|                <div class="search-box">
   130|                    <i class="fas fa-search"></i>
   131|                    <input type="text" name="q" placeholder="Cari kode atau nama barang..." value="<?= sanitize($search) ?>">
   132|                </div>
   133|                <select name="kategori" class="form-select" style="width:auto;min-width:180px;" onchange="this.form.submit()">
   134|                    <option value="">Semua Kategori</option>
   135|                    <?php foreach ($kategori_list as $k): ?>
   136|                        <option value="<?= $k['id'] ?>" <?= $filter_kategori == $k['id'] ? 'selected' : '' ?>><?= sanitize($k['nama_kategori']) ?></option>
   137|                    <?php endforeach; ?>
   138|                </select>
   139|                <?php if ($filter_stok === 'menipis'): ?>
   140|                    <a href="barang.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Reset Filter</a>
   141|                <?php endif; ?>
   142|                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Cari</button>
   143|            </form>
   144|        </div>
   145|
   146|        <div class="card-body no-pad">
   147|            <div class="table-container">
   148|                <table class="table">
   149|                    <thead>
   150|                        <tr>
   151|                            <th>Kode</th>
   152|                            <th>Nama Barang</th>
   153|                            <th>Kategori</th>
   154|                            <th>Stok</th>
   155|                            <th>Harga Beli</th>
   156|                            <th>Harga Jual</th>
   157|                            <th>Lokasi</th>
   158|                            <th>Aksi</th>
   159|                        </tr>
   160|                    </thead>
   161|                    <tbody>
   162|                        <?php foreach ($barang_list as $b): ?>
   163|                        <tr>
   164|                            <td><code class="text-mono"><?= sanitize($b['kode_barang']) ?></code></td>
   165|                            <td class="fw-600"><?= sanitize($b['nama_barang']) ?></td>
   166|                            <td><span class="badge badge-neutral"><?= sanitize($b['nama_kategori'] ?? '-') ?></span></td>
   167|                            <td>
   168|                                <?php if ($b['stok'] <= 0): ?>
   169|                                    <span class="badge badge-danger"><?= $b['stok'] ?> <?= sanitize($b['satuan']) ?></span>
   170|                                <?php elseif ($b['stok'] <= $b['stok_minimum']): ?>
   171|                                    <span class="badge badge-warning"><?= $b['stok'] ?> <?= sanitize($b['satuan']) ?></span>
   172|                                <?php else: ?>
   173|                                    <span class="badge badge-success"><?= $b['stok'] ?> <?= sanitize($b['satuan']) ?></span>
   174|                                <?php endif; ?>
   175|                            </td>
   176|                            <td class="text-muted"><?= rupiah($b['harga_beli']) ?></td>
   177|                            <td class="fw-600"><?= rupiah($b['harga_jual']) ?></td>
   178|                            <td class="text-muted"><?= sanitize($b['lokasi_rak'] ?? '-') ?></td>
   179|                            <td>
   180|                                <div class="d-flex gap-1">
   181|                                    <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($b)) ?>)" class="btn btn-ghost btn-sm" title="Edit">
   182|                                        <i class="fas fa-pen text-warning"></i>
   183|                                    </button>
   184|                                    <button onclick="openModal('hapus', <?= htmlspecialchars(json_encode($b)) ?>)" class="btn btn-ghost btn-sm" title="Hapus">
   185|                                        <i class="fas fa-trash text-danger"></i>
   186|                                    </button>
   187|                                </div>
   188|                            </td>
   189|                        </tr>
   190|                        <?php endforeach; ?>
   191|                        <?php if (empty($barang_list)): ?>
   192|                        <tr>
   193|                            <td colspan="8">
   194|                                <div class="empty-state">
   195|                                    <div class="empty-state-icon"><i class="fas fa-box-open"></i></div>
   196|                                    <h3>Belum ada data barang</h3>
   197|                                    <p>Klik tombol "Tambah Barang" untuk menambahkan data baru.</p>
   198|                                </div>
   199|                            </td>
   200|                        </tr>
   201|                        <?php endif; ?>
   202|                    </tbody>
   203|                </table>
   204|            </div>
   205|        </div>
   206|
   207|        <?php if ($total_pages > 1): ?>
   208|        <div class="pagination">
   209|            <div class="pagination-info">
   210|                Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> dari <?= $total ?> data
   211|            </div>
   212|            <div class="pagination-buttons">
   213|                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&kategori=<?= $filter_kategori ?>&filter=<?= $filter_stok ?>" 
   214|                   class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">←</a>
   215|                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
   216|                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&kategori=<?= $filter_kategori ?>&filter=<?= $filter_stok ?>" 
   217|                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
   218|                <?php endfor; ?>
   219|                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&kategori=<?= $filter_kategori ?>&filter=<?= $filter_stok ?>" 
   220|                   class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">→</a>
   221|            </div>
   222|        </div>
   223|        <?php endif; ?>
   224|    </div>
   225|</div>
   226|
   227|<!-- Modal Tambah/Edit -->
   228|<div class="modal-overlay" id="formModal">
   229|    <div class="modal">
   230|        <div class="modal-header">
   231|            <h3 id="modalTitle">Tambah Barang</h3>
   232|            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
   233|        </div>
   234|        <form method="POST">
   235|            <div class="modal-body">
   236|                <input type="hidden" name="action" id="formAction" value="tambah">
   237|                <input type="hidden" name="id" id="formId">
   238|                
   239|                <div class="form-group" id="kodeGroup">
   240|                    <label class="form-label">Kode Barang <span class="required">*</span></label>
   241|                    <input type="text" name="kode_barang" id="formKode" class="form-control" placeholder="Contoh: OLI-001" required>
   242|                    <div class="form-hint">Kode unik untuk identifikasi barang</div>
   243|                </div>
   244|                
   245|                <div class="form-group">
   246|                    <label class="form-label">Nama Barang <span class="required">*</span></label>
   247|                    <input type="text" name="nama_barang" id="formNama" class="form-control" required>
   248|                </div>
   249|                
   250|                <div class="form-row">
   251|                    <div class="form-group">
   252|                        <label class="form-label">Kategori</label>
   253|                        <select name="kategori_id" id="formKategori" class="form-select">
   254|                            <option value="">-- Pilih --</option>
   255|                            <?php foreach ($kategori_list as $k): ?>
   256|                                <option value="<?= $k['id'] ?>"><?= sanitize($k['nama_kategori']) ?></option>
   257|                            <?php endforeach; ?>
   258|                        </select>
   259|                    </div>
   260|                    <div class="form-group">
   261|                        <label class="form-label">Satuan</label>
   262|                        <input type="text" name="satuan" id="formSatuan" class="form-control" value="pcs" placeholder="pcs, botol, set">
   263|                    </div>
   264|                </div>
   265|                
   266|                <div class="form-row">
   267|                    <div class="form-group">
   268|                        <label class="form-label">Stok Minimum</label>
   269|                        <input type="number" name="stok_minimum" id="formStokMin" class="form-control" value="5" min="0">
   270|                    </div>
   271|                    <div class="form-group">
   272|                        <label class="form-label">Lokasi Rak</label>
   273|                        <input type="text" name="lokasi_rak" id="formLokasi" class="form-control" placeholder="Rak A1">
   274|                    </div>
   275|                </div>
   276|                
   277|                <div class="form-row">
   278|                    <div class="form-group">
   279|                        <label class="form-label">Harga Beli (Rp)</label>
   280|                        <input type="number" name="harga_beli" id="formBeli" class="form-control" value="0" min="0">
   281|                    </div>
   282|                    <div class="form-group">
   283|                        <label class="form-label">Harga Jual (Rp)</label>
   284|                        <input type="number" name="harga_jual" id="formJual" class="form-control" value="0" min="0">
   285|                    </div>
   286|                </div>
   287|            </div>
   288|            <div class="modal-footer">
   289|                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
   290|                <button type="submit" class="btn btn-accent" id="formSubmit">Simpan</button>
   291|            </div>
   292|        </form>
   293|    </div>
   294|</div>
   295|
   296|<!-- Modal Hapus -->
   297|<div class="modal-overlay" id="hapusModal">
   298|    <div class="modal" style="max-width:400px;">
   299|        <div class="modal-header">
   300|            <h3>Konfirmasi Hapus</h3>
   301|            <button class="modal-close" onclick="closeHapus()"><i class="fas fa-times"></i></button>
   302|        </div>
   303|        <form method="POST">
   304|            <div class="modal-body">
   305|                <input type="hidden" name="action" value="hapus">
   306|                <input type="hidden" name="id" id="hapusId">
   307|                <p>Yakin ingin menghapus barang <strong id="hapusNama"></strong>?</p>
   308|                <p class="text-muted mt-1">Data yang dihapus tidak dapat dikembalikan.</p>
   309|            </div>
   310|            <div class="modal-footer">
   311|                <button type="button" class="btn btn-outline" onclick="closeHapus()">Batal</button>
   312|                <button type="submit" class="btn btn-danger">Hapus</button>
   313|            </div>
   314|        </form>
   315|    </div>
   316|</div>
   317|
   318|<script>
   319|function openModal(action, data = null) {
   320|    const modal = document.getElementById('formModal');
   321|    const title = document.getElementById('modalTitle');
   322|    const formAction = document.getElementById('formAction');
   323|    const kodeGroup = document.getElementById('kodeGroup');
   324|    
   325|    if (action === 'tambah') {
   326|        title.textContent = 'Tambah Barang';
   327|        formAction.value = 'tambah';
   328|        kodeGroup.style.display = 'block';
   329|        document.getElementById('formKode').required = true;
   330|        document.getElementById('formKode').value = '';
   331|        document.getElementById('formNama').value = '';
   332|        document.getElementById('formKategori').value = '';
   333|        document.getElementById('formSatuan').value = 'pcs';
   334|        document.getElementById('formStokMin').value = '5';
   335|        document.getElementById('formLokasi').value = '';
   336|        document.getElementById('formBeli').value = '0';
   337|        document.getElementById('formJual').value = '0';
   338|    } else if (action === 'edit' && data) {
   339|        title.textContent = 'Edit Barang';
   340|        formAction.value = 'edit';
   341|        kodeGroup.style.display = 'none';
   342|        document.getElementById('formKode').required = false;
   343|        document.getElementById('formId').value = data.id;
   344|        document.getElementById('formNama').value = data.nama_barang;
   345|        document.getElementById('formKategori').value = data.kategori_id || '';
   346|        document.getElementById('formSatuan').value = data.satuan;
   347|        document.getElementById('formStokMin').value = data.stok_minimum;
   348|        document.getElementById('formLokasi').value = data.lokasi_rak || '';
   349|        document.getElementById('formBeli').value = data.harga_beli;
   350|        document.getElementById('formJual').value = data.harga_jual;
   351|    }
   352|    modal.classList.add('show');
   353|}
   354|
   355|function closeModal() {
   356|    document.getElementById('formModal').classList.remove('show');
   357|}
   358|
   359|function openModal(action, data = null) {
   360|    if (action === 'hapus' && data) {
   361|        document.getElementById('hapusId').value = data.id;
   362|        document.getElementById('hapusNama').textContent = data.nama_barang;
   363|        document.getElementById('hapusModal').classList.add('show');
   364|        return;
   365|    }
   366|    // ... existing code for tambah/edit
   367|    const modal = document.getElementById('formModal');
   368|    const title = document.getElementById('modalTitle');
   369|    const formAction = document.getElementById('formAction');
   370|    const kodeGroup = document.getElementById('kodeGroup');
   371|    
   372|    if (action === 'tambah') {
   373|        title.textContent = 'Tambah Barang';
   374|        formAction.value = 'tambah';
   375|        kodeGroup.style.display = 'block';
   376|        document.getElementById('formKode').required = true;
   377|        document.getElementById('formKode').value = '';
   378|        document.getElementById('formNama').value = '';
   379|        document.getElementById('formKategori').value = '';
   380|        document.getElementById('formSatuan').value = 'pcs';
   381|        document.getElementById('formStokMin').value = '5';
   382|        document.getElementById('formLokasi').value = '';
   383|        document.getElementById('formBeli').value = '0';
   384|        document.getElementById('formJual').value = '0';
   385|    } else if (action === 'edit' && data) {
   386|        title.textContent = 'Edit Barang';
   387|        formAction.value = 'edit';
   388|        kodeGroup.style.display = 'none';
   389|        document.getElementById('formKode').required = false;
   390|        document.getElementById('formId').value = data.id;
   391|        document.getElementById('formNama').value = data.nama_barang;
   392|        document.getElementById('formKategori').value = data.kategori_id || '';
   393|        document.getElementById('formSatuan').value = data.satuan;
   394|        document.getElementById('formStokMin').value = data.stok_minimum;
   395|        document.getElementById('formLokasi').value = data.lokasi_rak || '';
   396|        document.getElementById('formBeli').value = data.harga_beli;
   397|        document.getElementById('formJual').value = data.harga_jual;
   398|    }
   399|    modal.classList.add('show');
   400|}
   401|
   402|function closeHapus() {
   403|    document.getElementById('hapusModal').classList.remove('show');
   404|}
   405|
   406|// Close modal on overlay click
   407|document.querySelectorAll('.modal-overlay').forEach(overlay => {
   408|    overlay.addEventListener('click', function(e) {
   409|        if (e.target === this) {
   410|            this.classList.remove('show');
   411|        }
   412|    });
   413|});
   414|
   415|// Close modal on Escape key
   416|document.addEventListener('keydown', function(e) {
   417|    if (e.key === 'Escape') {
   418|        document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
   419|    }
   420|});
   421|</script>
   422|
</body>
</html>
