<?php
// masuk.php - Barang Masuk
require_once 'config/database.php';
requireLogin();

$page_title = 'Barang Masuk — Inventaris Bengkel Jaya';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'tambah') {
        $no_transaksi = generateNoTransaksi('BM', $pdo, 'barang_masuk');
        $jumlah = (int)$_POST['jumlah'];
        $harga = (int)$_POST['harga_satuan'];
        $total = $jumlah * $harga;
        
        $stmt = $pdo->prepare("INSERT INTO barang_masuk (no_transaksi, barang_id, jumlah, harga_satuan, total_harga, tanggal_masuk, supplier, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $no_transaksi, $_POST['barang_id'], $jumlah, $harga, $total,
            $_POST['tanggal_masuk'], $_POST['supplier'], $_POST['keterangan'], $_SESSION['user_id']
        ]);
        
        // Update stok barang
        $stmt = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
        $stmt->execute([$jumlah, $_POST['barang_id']]);
        
        setFlash('success', "Barang masuk berhasil dicatat. No: $no_transaksi");
        redirect('masuk.php');
    }
    
    if ($action === 'hapus') {
        // Kurangi stok dulu
        $stmt = $pdo->prepare("SELECT barang_id, jumlah FROM barang_masuk WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $bm = $stmt->fetch();
        
        if ($bm) {
            $stmt = $pdo->prepare("UPDATE barang SET stok = GREATEST(stok - ?, 0) WHERE id = ?");
            $stmt->execute([$bm['jumlah'], $bm['barang_id']]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM barang_masuk WHERE id=?");
        $stmt->execute([$_POST['id']]);
        setFlash('success', 'Data barang masuk berhasil dihapus.');
        redirect('masuk.php');
    }
}

$search = $_GET['q'] ?? '';
$tanggal_dari = $_GET['dari'] ?? '';
$tanggal_sampai = $_GET['sampai'] ?? '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(bm.no_transaksi LIKE ? OR b.nama_barang LIKE ? OR bm.supplier LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($tanggal_dari) {
    $where[] = "bm.tanggal_masuk >= ?";
    $params[] = $tanggal_dari;
}
if ($tanggal_sampai) {
    $where[] = "bm.tanggal_masuk <= ?";
    $params[] = $tanggal_sampai;
}

$where_sql = implode(' AND ', $where);

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang_masuk bm JOIN barang b ON bm.barang_id = b.id WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT bm.*, b.kode_barang, b.nama_barang, b.satuan, u.nama_lengkap 
    FROM barang_masuk bm 
    JOIN barang b ON bm.barang_id = b.id 
    LEFT JOIN users u ON bm.user_id = u.id 
    WHERE $where_sql 
    ORDER BY bm.created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$masuk_list = $stmt->fetchAll();

// Total nilai masuk
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) as total FROM barang_masuk bm JOIN barang b ON bm.barang_id = b.id WHERE $where_sql");
$stmt->execute($params);
$total_nilai = $stmt->fetch()['total'];

// Barang untuk dropdown
$barang_list = $pdo->query("SELECT id, kode_barang, nama_barang, satuan FROM barang WHERE aktif = 1 ORDER BY kode_barang")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Barang Masuk</h1>
            <div class="page-header-sub">Catat penerimaan barang ke gudang</div>
        </div>
        <div class="page-header-actions">
            <a href="export.php?type=masuk&format=csv&bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" class="btn btn-outline btn-sm" title="Export Excel">
                <i class="fas fa-file-csv"></i>
            </a>
            <a href="export.php?type=masuk&format=pdf&bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" target="_blank" class="btn btn-outline btn-sm" title="Export PDF">
                <i class="fas fa-file-pdf"></i>
            </a>
            <a href="export.php?type=masuk&format=print&bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" target="_blank" class="btn btn-outline btn-sm" title="Print">
                <i class="fas fa-print"></i>
            </a>
            <button onclick="openModal()" class="btn btn-accent">
                <i class="fas fa-plus"></i> Tambah Transaksi
            </button>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:var(--space-lg);">
        <div class="stat-card">
            <div class="stat-card-label">Total Transaksi</div>
            <div class="stat-card-value" style="font-size:1.5rem;"><?= $total ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Total Nilai Masuk</div>
            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($total_nilai) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" class="toolbar" style="width:100%;margin:0;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Cari no transaksi, barang, supplier..." value="<?= sanitize($search) ?>">
                </div>
                <input type="date" name="dari" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_dari) ?>" placeholder="Dari">
                <input type="date" name="sampai" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_sampai) ?>" placeholder="Sampai">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($search || $tanggal_dari || $tanggal_sampai): ?>
                    <a href="masuk.php" class="btn btn-outline btn-sm">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body no-pad">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Harga Satuan</th>
                            <th>Total</th>
                            <th>Supplier</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($masuk_list as $m): ?>
                        <tr>
                            <td><code class="text-mono"><?= sanitize($m['no_transaksi']) ?></code></td>
                            <td class="text-muted"><?= tglPendek($m['tanggal_masuk']) ?></td>
                            <td>
                                <div class="fw-600"><?= sanitize($m['nama_barang']) ?></div>
                                <small class="text-muted"><?= sanitize($m['kode_barang']) ?></small>
                            </td>
                            <td><span class="badge badge-success">+<?= $m['jumlah'] ?> <?= sanitize($m['satuan']) ?></span></td>
                            <td class="text-muted"><?= rupiah($m['harga_satuan']) ?></td>
                            <td class="fw-600"><?= rupiah($m['total_harga']) ?></td>
                            <td class="text-muted"><?= sanitize($m['supplier'] ?? '-') ?></td>
                            <td>
                                <button onclick="if(confirm('Yakin hapus?')) document.getElementById('hapus-<?= $m['id'] ?>').submit()" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-trash text-danger"></i>
                                </button>
                                <form id="hapus-<?= $m['id'] ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($masuk_list)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-arrow-down"></i></div>
                                    <h3>Belum ada transaksi masuk</h3>
                                    <p>Klik tombol "Tambah Transaksi" untuk mencatat penerimaan barang.</p>
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
            <div class="pagination-info">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> dari <?= $total ?></div>
            <div class="pagination-buttons">
                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">←</a>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&dari=<?= $tanggal_dari ?>&sampai=<?= $tanggal_sampai ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">→</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Tambah Barang Masuk</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="tambah">
                
                <div class="form-group">
                    <label class="form-label">Barang <span class="required">*</span></label>
                    <select name="barang_id" class="form-select" required>
                        <option value="">-- Pilih Barang --</option>
                        <?php foreach ($barang_list as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= sanitize($b['kode_barang']) ?> - <?= sanitize($b['nama_barang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jumlah <span class="required">*</span></label>
                        <input type="number" name="jumlah" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Masuk <span class="required">*</span></label>
                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Harga Satuan (Rp)</label>
                    <input type="number" name="harga_satuan" class="form-control" value="0" min="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Supplier</label>
                    <input type="text" name="supplier" class="form-control" placeholder="Nama supplier/distributor">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-accent">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('formModal').classList.add('show'); }
function closeModal() { document.getElementById('formModal').classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
</script>

</body>
</html>
