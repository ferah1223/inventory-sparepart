<?php
// kategori.php - Data Kategori
require_once 'config/database.php';
requireLogin();

$page_title = 'Kategori — Inventaris Bengkel Jaya';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'tambah') {
        $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
        $stmt->execute([$_POST['nama_kategori'], $_POST['deskripsi']]);
        setFlash('success', 'Kategori berhasil ditambahkan.');
        redirect('kategori.php');
    }
    if ($action === 'edit') {
        $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori=?, deskripsi=? WHERE id=?");
        $stmt->execute([$_POST['nama_kategori'], $_POST['deskripsi'], $_POST['id']]);
        setFlash('success', 'Kategori berhasil diupdate.');
        redirect('kategori.php');
    }
    if ($action === 'hapus') {
        // Cek apakah masih ada barang di kategori ini
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang WHERE kategori_id = ?");
        $stmt->execute([$_POST['id']]);
        $cnt = $stmt->fetch()['cnt'];
        if ($cnt > 0) {
            setFlash('error', 'Kategori masih digunakan oleh ' . $cnt . ' barang. Hapus barang terlebih dahulu.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM kategori WHERE id=?");
            $stmt->execute([$_POST['id']]);
            setFlash('success', 'Kategori berhasil dihapus.');
        }
        redirect('kategori.php');
    }
}

$kategori_list = $pdo->query("
    SELECT k.*, COUNT(b.id) as jumlah_barang, COALESCE(SUM(b.stok), 0) as total_stok 
    FROM kategori k 
    LEFT JOIN barang b ON k.id = b.kategori_id AND b.aktif = 1
    GROUP BY k.id 
    ORDER BY k.nama_kategori
")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Data Kategori</h1>
            <div class="page-header-sub">Kelola kategori spare part</div>
        </div>
        <div class="page-header-actions">
            <button onclick="openModal('tambah')" class="btn btn-accent">
                <i class="fas fa-plus"></i> Tambah Kategori
            </button>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body no-pad">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Jumlah Barang</th>
                            <th>Total Stok</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kategori_list as $i => $k): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td class="fw-600"><?= sanitize($k['nama_kategori']) ?></td>
                            <td class="text-muted"><?= sanitize($k['deskripsi'] ?? '-') ?></td>
                            <td><span class="badge badge-info"><?= $k['jumlah_barang'] ?> item</span></td>
                            <td><?= number_format($k['total_stok']) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($k)) ?>)" class="btn btn-ghost btn-sm">
                                        <i class="fas fa-pen text-warning"></i>
                                    </button>
                                    <button onclick="openModal('hapus', <?= htmlspecialchars(json_encode($k)) ?>)" class="btn btn-ghost btn-sm">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Kategori</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId">
                <div class="form-group">
                    <label class="form-label">Nama Kategori <span class="required">*</span></label>
                    <input type="text" name="nama_kategori" id="formNama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" id="formDeskripsi" class="form-control" rows="3" placeholder="Deskripsi singkat kategori..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-accent">Simpan</button>
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
                <p>Yakin ingin menghapus kategori <strong id="hapusNama"></strong>?</p>
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
    if (action === 'hapus' && data) {
        document.getElementById('hapusId').value = data.id;
        document.getElementById('hapusNama').textContent = data.nama_kategori;
        document.getElementById('hapusModal').classList.add('show');
        return;
    }
    const modal = document.getElementById('formModal');
    document.getElementById('modalTitle').textContent = action === 'edit' ? 'Edit Kategori' : 'Tambah Kategori';
    document.getElementById('formAction').value = action;
    if (action === 'edit' && data) {
        document.getElementById('formId').value = data.id;
        document.getElementById('formNama').value = data.nama_kategori;
        document.getElementById('formDeskripsi').value = data.deskripsi || '';
    } else {
        document.getElementById('formId').value = '';
        document.getElementById('formNama').value = '';
        document.getElementById('formDeskripsi').value = '';
    }
    modal.classList.add('show');
}
function closeModal() { document.getElementById('formModal').classList.remove('show'); }
function closeHapus() { document.getElementById('hapusModal').classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
</script>

</body>
</html>
