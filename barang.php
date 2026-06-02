<?php
// barang.php - Data Barang (Spare Part)
require_once 'config/database.php';
requireLogin();

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'tambah') {
        $stmt = $pdo->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan, stok, stok_minimum, harga_beli, harga_jual, lokasi_rak) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['kode_barang'], $_POST['nama_barang'], $_POST['kategori_id'] ?: null,
            $_POST['satuan'], 0, $_POST['stok_minimum'], $_POST['harga_beli'], $_POST['harga_jual'], $_POST['lokasi_rak']
        ]);
        setFlash('success', 'Barang berhasil ditambahkan.');
        redirect('barang.php');
    }
    
    if ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE barang SET nama_barang=?, kategori_id=?, satuan=?, stok_minimum=?, harga_beli=?, harga_jual=?, lokasi_rak=? WHERE id=?");
        $stmt->execute([
            $_POST['nama_barang'], $_POST['kategori_id'] ?: null, $_POST['satuan'],
            $_POST['stok_minimum'], $_POST['harga_beli'], $_POST['harga_jual'], $_POST['lokasi_rak'], $_POST['id']
        ]);
        setFlash('success', 'Data barang berhasil diupdate.');
        redirect('barang.php');
    }
    
    if ($action === 'hapus') {
        $stmt = $pdo->prepare("DELETE FROM barang WHERE id=?");
        $stmt->execute([$_POST['id']]);
        setFlash('success', 'Barang berhasil dihapus.');
        redirect('barang.php');
    }
}

// Filter & Search
$search = $_GET['q'] ?? '';
$filter_kategori = $_GET['kategori'] ?? '';
$filter_stok = $_GET['filter'] ?? '';

$where = ["b.aktif = 1"];
$params = [];

if ($search) {
    $where[] = "(b.kode_barang LIKE ? OR b.nama_barang LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_kategori) {
    $where[] = "b.kategori_id = ?";
    $params[] = $filter_kategori;
}
if ($filter_stok === 'menipis') {
    $where[] = "b.stok <= b.stok_minimum";
}

$where_sql = implode(' AND ', $where);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang b WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT b.*, k.nama_kategori 
    FROM barang b 
    LEFT JOIN kategori k ON b.kategori_id = k.id 
    WHERE $where_sql 
    ORDER BY b.kode_barang ASC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$barang_list = $stmt->fetchAll();

// Kategori untuk dropdown
$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

// Hitung total nilai stok
$stmt = $pdo->query("SELECT COALESCE(SUM(stok * harga_beli), 0) as total FROM barang WHERE aktif = 1");
$total_nilai_stok = $stmt->fetch()['total'];

include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Data Barang</h1>
            <div class="page-header-sub">Kelola data spare part sepeda motor</div>
        </div>
        <div class="page-header-actions">
            <button onclick="openModal('tambah')" class="btn btn-accent">
                <i class="fas fa-plus"></i> Tambah Barang
            </button>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:var(--space-lg);">
        <div class="stat-card">
            <div class="stat-card-label">Total Jenis</div>
            <div class="stat-card-value" style="font-size:1.5rem;"><?= $total ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Total Stok</div>
            <div class="stat-card-value" style="font-size:1.5rem;"><?= number_format(array_sum(array_column($barang_list, 'stok'))) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Nilai Stok</div>
            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($total_nilai_stok) ?></div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card">
        <div class="card-header">
            <form method="GET" class="toolbar" style="width:100%;margin:0;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Cari kode atau nama barang..." value="<?= sanitize($search) ?>">
                </div>
                <select name="kategori" class="form-select" style="width:auto;min-width:180px;" onchange="this.form.submit()">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategori_list as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $filter_kategori == $k['id'] ? 'selected' : '' ?>><?= sanitize($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filter_stok === 'menipis'): ?>
                    <a href="barang.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Reset Filter</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Cari</button>
            </form>
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
                            <th>Harga Beli</th>
                            <th>Harga Jual</th>
                            <th>Lokasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($barang_list as $b): ?>
                        <tr>
                            <td><code class="text-mono"><?= sanitize($b['kode_barang']) ?></code></td>
                            <td class="fw-600"><?= sanitize($b['nama_barang']) ?></td>
                            <td><span class="badge badge-neutral"><?= sanitize($b['nama_kategori'] ?? '-') ?></span></td>
                            <td>
                                <?php if ($b['stok'] <= 0): ?>
                                    <span class="badge badge-danger"><?= $b['stok'] ?> <?= sanitize($b['satuan']) ?></span>
                                <?php elseif ($b['stok'] <= $b['stok_minimum']): ?>
                                    <span class="badge badge-warning"><?= $b['stok'] ?> <?= sanitize($b['satuan']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= $b['stok'] ?> <?= sanitize($b['satuan']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= rupiah($b['harga_beli']) ?></td>
                            <td class="fw-600"><?= rupiah($b['harga_jual']) ?></td>
                            <td class="text-muted"><?= sanitize($b['lokasi_rak'] ?? '-') ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($b)) ?>)" class="btn btn-ghost btn-sm" title="Edit">
                                        <i class="fas fa-pen text-warning"></i>
                                    </button>
                                    <button onclick="openModal('hapus', <?= htmlspecialchars(json_encode($b)) ?>)" class="btn btn-ghost btn-sm" title="Hapus">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($barang_list)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-box-open"></i></div>
                                    <h3>Belum ada data barang</h3>
                                    <p>Klik tombol "Tambah Barang" untuk menambahkan data baru.</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> dari <?= $total ?> data
            </div>
            <div class="pagination-buttons">
                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&kategori=<?= $filter_kategori ?>&filter=<?= $filter_stok ?>" 
                   class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">←</a>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&kategori=<?= $filter_kategori ?>&filter=<?= $filter_stok ?>" 
                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&kategori=<?= $filter_kategori ?>&filter=<?= $filter_stok ?>" 
                   class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">→</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Barang</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId">
                
                <div class="form-group" id="kodeGroup">
                    <label class="form-label">Kode Barang <span class="required">*</span></label>
                    <input type="text" name="kode_barang" id="formKode" class="form-control" placeholder="Contoh: OLI-001" required>
                    <div class="form-hint">Kode unik untuk identifikasi barang</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nama Barang <span class="required">*</span></label>
                    <input type="text" name="nama_barang" id="formNama" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" id="formKategori" class="form-select">
                            <option value="">-- Pilih --</option>
                            <?php foreach ($kategori_list as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= sanitize($k['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Satuan</label>
                        <input type="text" name="satuan" id="formSatuan" class="form-control" value="pcs" placeholder="pcs, botol, set">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Stok Minimum</label>
                        <input type="number" name="stok_minimum" id="formStokMin" class="form-control" value="5" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lokasi Rak</label>
                        <input type="text" name="lokasi_rak" id="formLokasi" class="form-control" placeholder="Rak A1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Harga Beli (Rp)</label>
                        <input type="number" name="harga_beli" id="formBeli" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" id="formJual" class="form-control" value="0" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-accent" id="formSubmit">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Hapus -->
<div class="modal-overlay" id="hapusModal">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3>Konfirmasi Hapus</h3>
            <button class="modal-close" onclick="closeHapus()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" id="hapusId">
                <p>Yakin ingin menghapus barang <strong id="hapusNama"></strong>?</p>
                <p class="text-muted mt-1">Data yang dihapus tidak dapat dikembalikan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeHapus()">Batal</button>
                <button type="submit" class="btn btn-danger">Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(action, data = null) {
    const modal = document.getElementById('formModal');
    const title = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const kodeGroup = document.getElementById('kodeGroup');
    
    if (action === 'tambah') {
        title.textContent = 'Tambah Barang';
        formAction.value = 'tambah';
        kodeGroup.style.display = 'block';
        document.getElementById('formKode').required = true;
        document.getElementById('formKode').value = '';
        document.getElementById('formNama').value = '';
        document.getElementById('formKategori').value = '';
        document.getElementById('formSatuan').value = 'pcs';
        document.getElementById('formStokMin').value = '5';
        document.getElementById('formLokasi').value = '';
        document.getElementById('formBeli').value = '0';
        document.getElementById('formJual').value = '0';
    } else if (action === 'edit' && data) {
        title.textContent = 'Edit Barang';
        formAction.value = 'edit';
        kodeGroup.style.display = 'none';
        document.getElementById('formKode').required = false;
        document.getElementById('formId').value = data.id;
        document.getElementById('formNama').value = data.nama_barang;
        document.getElementById('formKategori').value = data.kategori_id || '';
        document.getElementById('formSatuan').value = data.satuan;
        document.getElementById('formStokMin').value = data.stok_minimum;
        document.getElementById('formLokasi').value = data.lokasi_rak || '';
        document.getElementById('formBeli').value = data.harga_beli;
        document.getElementById('formJual').value = data.harga_jual;
    }
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('formModal').classList.remove('show');
}

function openModal(action, data = null) {
    if (action === 'hapus' && data) {
        document.getElementById('hapusId').value = data.id;
        document.getElementById('hapusNama').textContent = data.nama_barang;
        document.getElementById('hapusModal').classList.add('show');
        return;
    }
    // ... existing code for tambah/edit
    const modal = document.getElementById('formModal');
    const title = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const kodeGroup = document.getElementById('kodeGroup');
    
    if (action === 'tambah') {
        title.textContent = 'Tambah Barang';
        formAction.value = 'tambah';
        kodeGroup.style.display = 'block';
        document.getElementById('formKode').required = true;
        document.getElementById('formKode').value = '';
        document.getElementById('formNama').value = '';
        document.getElementById('formKategori').value = '';
        document.getElementById('formSatuan').value = 'pcs';
        document.getElementById('formStokMin').value = '5';
        document.getElementById('formLokasi').value = '';
        document.getElementById('formBeli').value = '0';
        document.getElementById('formJual').value = '0';
    } else if (action === 'edit' && data) {
        title.textContent = 'Edit Barang';
        formAction.value = 'edit';
        kodeGroup.style.display = 'none';
        document.getElementById('formKode').required = false;
        document.getElementById('formId').value = data.id;
        document.getElementById('formNama').value = data.nama_barang;
        document.getElementById('formKategori').value = data.kategori_id || '';
        document.getElementById('formSatuan').value = data.satuan;
        document.getElementById('formStokMin').value = data.stok_minimum;
        document.getElementById('formLokasi').value = data.lokasi_rak || '';
        document.getElementById('formBeli').value = data.harga_beli;
        document.getElementById('formJual').value = data.harga_jual;
    }
    modal.classList.add('show');
}

function closeHapus() {
    document.getElementById('hapusModal').classList.remove('show');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
    }
});
</script>
