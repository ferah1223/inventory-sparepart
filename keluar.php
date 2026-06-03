<?php
// keluar.php - Barang Keluar
require_once 'config/database.php';
requireLogin();

$page_title = 'Barang Keluar — Inventaris Bengkel Jaya';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'tambah') {
        // Cek stok cukup
        $stmt = $pdo->prepare("SELECT stok, nama_barang FROM barang WHERE id = ?");
        $stmt->execute([$_POST['barang_id']]);
        $barang = $stmt->fetch();
        
        $jumlah = (int)$_POST['jumlah'];
        
        if ($barang['stok'] < $jumlah) {
            setFlash('error', "Stok tidak cukup! Stok {$barang['nama_barang']} tersisa: {$barang['stok']}");
            redirect('keluar.php');
        }
        
        $no_transaksi = generateNoTransaksi('BK', $pdo, 'barang_keluar');
        $harga = (int)$_POST['harga_satuan'];
        $total = $jumlah * $harga;
        
        $stmt = $pdo->prepare("INSERT INTO barang_keluar (no_transaksi, barang_id, jumlah, harga_satuan, total_harga, tanggal_keluar, tujuan, penerima, keterangan, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $no_transaksi, $_POST['barang_id'], $jumlah, $harga, $total,
            $_POST['tanggal_keluar'], $_POST['tujuan'], $_POST['penerima'], $_POST['keterangan'], $_SESSION['user_id']
        ]);
        
        $newId = $pdo->lastInsertId();
        addAuditLog($pdo, 'create', 'barang_keluar', $newId, null, [
            'no_transaksi' => $no_transaksi, 'barang_id' => $_POST['barang_id'],
            'jumlah' => $jumlah, 'tujuan' => $_POST['tujuan'], 'penerima' => $_POST['penerima']
        ]);
        
        // Kurangi stok
        $stmt = $pdo->prepare("UPDATE barang SET stok = stok - ? WHERE id = ?");
        $stmt->execute([$jumlah, $_POST['barang_id']]);
        
        setFlash('success', "Barang keluar berhasil dicatat. No: $no_transaksi");
        redirect('keluar.php');
    }
    
    if ($action === 'hapus') {
        $stmt = $pdo->prepare("SELECT barang_id, jumlah FROM barang_keluar WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $bk = $stmt->fetch();
        
        // Capture old values
        $stmt_old = $pdo->prepare("SELECT * FROM barang_keluar WHERE id = ?");
        $stmt_old->execute([$_POST['id']]);
        $old = $stmt_old->fetch();
        
        if ($bk) {
            $stmt = $pdo->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
            $stmt->execute([$bk['jumlah'], $bk['barang_id']]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM barang_keluar WHERE id=?");
        $stmt->execute([$_POST['id']]);
        if ($old) {
            addAuditLog($pdo, 'delete', 'barang_keluar', $_POST['id'], $old, null);
        }
        setFlash('success', 'Data barang keluar berhasil dihapus (stok dikembalikan).');
        redirect('keluar.php');
    }
}

$search = $_GET['q'] ?? '';
$tanggal_dari = $_GET['dari'] ?? '';
$tanggal_sampai = $_GET['sampai'] ?? '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(bk.no_transaksi LIKE ? OR b.nama_barang LIKE ? OR bk.tujuan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($tanggal_dari) {
    $where[] = "bk.tanggal_keluar >= ?";
    $params[] = $tanggal_dari;
}
if ($tanggal_sampai) {
    $where[] = "bk.tanggal_keluar <= ?";
    $params[] = $tanggal_sampai;
}

$where_sql = implode(' AND ', $where);

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang_keluar bk JOIN barang b ON bk.barang_id = b.id WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT bk.*, b.kode_barang, b.nama_barang, b.satuan, u.nama_lengkap 
    FROM barang_keluar bk 
    JOIN barang b ON bk.barang_id = b.id 
    LEFT JOIN users u ON bk.user_id = u.id 
    WHERE $where_sql 
    ORDER BY bk.created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$keluar_list = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_harga), 0) as total FROM barang_keluar bk JOIN barang b ON bk.barang_id = b.id WHERE $where_sql");
$stmt->execute($params);
$total_nilai = $stmt->fetch()['total'];

$barang_list = $pdo->query("SELECT id, kode_barang, nama_barang, satuan, stok FROM barang WHERE aktif = 1 AND stok > 0 ORDER BY kode_barang")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Barang Keluar</h1>
            <div class="page-header-sub">Catat pengeluaran barang dari gudang</div>
        </div>
        <div class="page-header-actions">
            <a href="export.php?type=keluar&format=csv&bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" class="btn btn-outline btn-sm" title="Export Excel">
                <i class="fas fa-file-csv"></i>
            </a>
            <a href="export.php?type=keluar&format=pdf&bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" target="_blank" class="btn btn-outline btn-sm" title="Export PDF">
                <i class="fas fa-file-pdf"></i>
            </a>
            <a href="export.php?type=keluar&format=print&bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>" target="_blank" class="btn btn-outline btn-sm" title="Print">
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
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:var(--space-lg);">
        <div class="stat-card">
            <div class="stat-card-label">Total Transaksi</div>
            <div class="stat-card-value" style="font-size:1.5rem;"><?= $total ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Total Nilai Keluar</div>
            <div class="stat-card-value" style="font-size:1.25rem;"><?= rupiah($total_nilai) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" class="toolbar" style="width:100%;margin:0;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Cari no transaksi, barang, tujuan..." value="<?= sanitize($search) ?>">
                </div>
                <input type="date" name="dari" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_dari) ?>">
                <input type="date" name="sampai" class="form-control" style="width:auto;" value="<?= sanitize($tanggal_sampai) ?>">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($search || $tanggal_dari || $tanggal_sampai): ?>
                    <a href="keluar.php" class="btn btn-outline btn-sm">Reset</a>
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
                            <th>Harga</th>
                            <th>Total</th>
                            <th>Tujuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keluar_list as $k): ?>
                        <tr>
                            <td><code class="text-mono"><?= sanitize($k['no_transaksi']) ?></code></td>
                            <td class="text-muted"><?= tglPendek($k['tanggal_keluar']) ?></td>
                            <td>
                                <div class="fw-600"><?= sanitize($k['nama_barang']) ?></div>
                                <small class="text-muted"><?= sanitize($k['kode_barang']) ?></small>
                            </td>
                            <td><span class="badge badge-danger">-<?= $k['jumlah'] ?> <?= sanitize($k['satuan']) ?></span></td>
                            <td class="text-muted"><?= rupiah($k['harga_satuan']) ?></td>
                            <td class="fw-600"><?= rupiah($k['total_harga']) ?></td>
                            <td class="text-muted"><?= sanitize($k['tujuan'] ?? '-') ?></td>
                            <td>
                                <button onclick="showDeleteConfirm('Yakin hapus data barang keluar ini? Stok akan dikembalikan.', 'hapus-<?= $k['id'] ?>')" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-trash text-danger"></i>
                                </button>
                                <form id="hapus-<?= $k['id'] ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($keluar_list)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-arrow-up"></i></div>
                                    <h3>Belum ada transaksi keluar</h3>
                                    <p>Klik tombol "Tambah Transaksi" untuk mencatat pengeluaran barang.</p>
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

<!-- Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Tambah Barang Keluar</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="tambah">
                
                <div class="form-group">
                    <label class="form-label">Barang <span class="required">*</span></label>
                    <select name="barang_id" class="form-select" required onchange="updateStok(this)">
                        <option value="">-- Pilih Barang --</option>
                        <?php foreach ($barang_list as $b): ?>
                            <option value="<?= $b['id'] ?>" data-stok="<?= $b['stok'] ?>" data-harga="0">
                                <?= sanitize($b['kode_barang']) ?> - <?= sanitize($b['nama_barang']) ?> (Stok: <?= $b['stok'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint" id="stokInfo">Pilih barang untuk melihat stok tersedia</div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Jumlah <span class="required">*</span></label>
                        <input type="number" name="jumlah" id="formJumlah" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Keluar <span class="required">*</span></label>
                        <input type="date" name="tanggal_keluar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Harga Satuan (Rp)</label>
                    <input type="number" name="harga_satuan" class="form-control" value="0" min="0">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tujuan</label>
                        <input type="text" name="tujuan" class="form-control" placeholder="Bengkel, Pelanggan, dll">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Penerima</label>
                        <input type="text" name="penerima" class="form-control" placeholder="Nama penerima">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan..."></textarea>
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
function updateStok(select) {
    const option = select.options[select.selectedIndex];
    const stok = option.dataset.stok || 0;
    document.getElementById('stokInfo').textContent = stok > 0 ? 'Stok tersedia: ' + stok : '';
    document.getElementById('formJumlah').max = stok;
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
</script>

</body>
</html>
