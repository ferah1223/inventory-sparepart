<?php
// supplier.php - Data Supplier
require_once 'config/database.php';
requireLogin();

$page_title = 'Supplier — Inventaris Bengkel Jaya';

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'tambah') {
        $stmt = $pdo->prepare("INSERT INTO supplier (nama_supplier, alamat, telepon, email, kontak_person) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['nama_supplier'], $_POST['alamat'], $_POST['telepon'], $_POST['email'], $_POST['kontak_person']
        ]);
        $newId = $pdo->lastInsertId();
        addAuditLog($pdo, 'create', 'supplier', $newId, null, [
            'nama_supplier' => $_POST['nama_supplier'],
            'alamat' => $_POST['alamat'],
            'telepon' => $_POST['telepon'],
            'email' => $_POST['email'],
            'kontak_person' => $_POST['kontak_person']
        ]);
        setFlash('success', 'Supplier berhasil ditambahkan.');
        redirect('supplier.php');
    }
    
    if ($action === 'edit') {
        // Get old values
        $stmt = $pdo->prepare("SELECT * FROM supplier WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $old = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE supplier SET nama_supplier=?, alamat=?, telepon=?, email=?, kontak_person=? WHERE id=?");
        $stmt->execute([
            $_POST['nama_supplier'], $_POST['alamat'], $_POST['telepon'], $_POST['email'], $_POST['kontak_person'], $_POST['id']
        ]);
        addAuditLog($pdo, 'update', 'supplier', $_POST['id'], [
            'nama_supplier' => $old['nama_supplier'],
            'alamat' => $old['alamat'],
            'telepon' => $old['telepon'],
            'email' => $old['email'],
            'kontak_person' => $old['kontak_person']
        ], [
            'nama_supplier' => $_POST['nama_supplier'],
            'alamat' => $_POST['alamat'],
            'telepon' => $_POST['telepon'],
            'email' => $_POST['email'],
            'kontak_person' => $_POST['kontak_person']
        ]);
        setFlash('success', 'Data supplier berhasil diupdate.');
        redirect('supplier.php');
    }
    
    if ($action === 'hapus') {
        // Get old values
        $stmt = $pdo->prepare("SELECT * FROM supplier WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $old = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE supplier SET aktif = 0 WHERE id=?");
        $stmt->execute([$_POST['id']]);
        addAuditLog($pdo, 'delete', 'supplier', $_POST['id'], $old, null);
        setFlash('success', 'Supplier berhasil dinonaktifkan.');
        redirect('supplier.php');
    }
}

// Filter & Search
$search = $_GET['q'] ?? '';
$where = ["s.aktif = 1"];
$params = [];

if ($search) {
    $where[] = "(s.nama_supplier LIKE ? OR s.email LIKE ? OR s.telepon LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM supplier s WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT s.*, COUNT(bm.id) as jumlah_transaksi
    FROM supplier s 
    LEFT JOIN barang_masuk bm ON s.id = bm.supplier_id 
    WHERE $where_sql 
    GROUP BY s.id
    ORDER BY s.nama_supplier ASC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$supplier_list = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Data Supplier</h1>
            <div class="page-header-sub">Kelola data supplier/distributor</div>
        </div>
        <div class="page-header-actions">
            <button onclick="openModal('tambah')" class="btn btn-accent">
                <i class="fas fa-plus"></i> Tambah Supplier
            </button>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom:var(--space-lg);">
        <div class="stat-card">
            <div class="stat-card-label">Total Supplier Aktif</div>
            <div class="stat-card-value" style="font-size:1.5rem;"><?= $total ?></div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card">
        <div class="card-header">
            <form method="GET" class="toolbar" style="width:100%;margin:0;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Cari nama supplier, email, telepon..." value="<?= sanitize($search) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Cari</button>
            </form>
        </div>

        <div class="card-body no-pad">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Supplier</th>
                            <th>Alamat</th>
                            <th>Telepon</th>
                            <th>Email</th>
                            <th>Kontak Person</th>
                            <th>Transaksi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supplier_list as $i => $s): ?>
                        <tr>
                            <td class="text-muted"><?= $offset + $i + 1 ?></td>
                            <td class="fw-600"><?= sanitize($s['nama_supplier']) ?></td>
                            <td class="text-muted"><?= sanitize($s['alamat'] ?? '-') ?></td>
                            <td class="text-muted"><?= sanitize($s['telepon'] ?? '-') ?></td>
                            <td class="text-muted"><?= sanitize($s['email'] ?? '-') ?></td>
                            <td class="text-muted"><?= sanitize($s['kontak_person'] ?? '-') ?></td>
                            <td><span class="badge badge-info"><?= $s['jumlah_transaksi'] ?> transaksi</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($s)) ?>)" class="btn btn-ghost btn-sm" title="Edit">
                                        <i class="fas fa-pen text-warning"></i>
                                    </button>
                                    <button onclick="openModal('hapus', <?= htmlspecialchars(json_encode($s)) ?>)" class="btn btn-ghost btn-sm" title="Hapus">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($supplier_list)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-truck"></i></div>
                                    <h3>Belum ada data supplier</h3>
                                    <p>Klik tombol "Tambah Supplier" untuk menambahkan data baru.</p>
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
                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" 
                   class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">←</a>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" 
                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" 
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
            <h3 id="modalTitle">Tambah Supplier</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId">
                
                <div class="form-group">
                    <label class="form-label">Nama Supplier <span class="required">*</span></label>
                    <input type="text" name="nama_supplier" id="formNama" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" id="formAlamat" class="form-control" rows="2" placeholder="Alamat lengkap supplier..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Telepon</label>
                        <input type="text" name="telepon" id="formTelepon" class="form-control" placeholder="021-xxxxxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="formEmail" class="form-control" placeholder="email@supplier.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kontak Person</label>
                    <input type="text" name="kontak_person" id="formKontak" class="form-control" placeholder="Nama PIC supplier">
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
            <h3>Konfirmasi Nonaktifkan</h3>
            <button class="modal-close" onclick="closeHapus()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" id="hapusId">
                <p>Yakin ingin menonaktifkan supplier <strong id="hapusNama"></strong>?</p>
                <p class="text-muted mt-1">Data transaksi terkait tetap tersimpan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeHapus()">Batal</button>
                <button type="submit" class="btn btn-danger">Nonaktifkan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(action, data = null) {
    if (action === 'hapus' && data) {
        document.getElementById('hapusId').value = data.id;
        document.getElementById('hapusNama').textContent = data.nama_supplier;
        document.getElementById('hapusModal').classList.add('show');
        return;
    }
    const modal = document.getElementById('formModal');
    const title = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    
    if (action === 'tambah') {
        title.textContent = 'Tambah Supplier';
        formAction.value = 'tambah';
        document.getElementById('formId').value = '';
        document.getElementById('formNama').value = '';
        document.getElementById('formAlamat').value = '';
        document.getElementById('formTelepon').value = '';
        document.getElementById('formEmail').value = '';
        document.getElementById('formKontak').value = '';
    } else if (action === 'edit' && data) {
        title.textContent = 'Edit Supplier';
        formAction.value = 'edit';
        document.getElementById('formId').value = data.id;
        document.getElementById('formNama').value = data.nama_supplier;
        document.getElementById('formAlamat').value = data.alamat || '';
        document.getElementById('formTelepon').value = data.telepon || '';
        document.getElementById('formEmail').value = data.email || '';
        document.getElementById('formKontak').value = data.kontak_person || '';
    }
    modal.classList.add('show');
}

function closeModal() {
    document.getElementById('formModal').classList.remove('show');
}

function closeHapus() {
    document.getElementById('hapusModal').classList.remove('show');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show'));
    }
});
</script>

</body>
</html>
