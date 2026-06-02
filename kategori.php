     1|<?php
     2|// kategori.php - Data Kategori
     3|require_once 'config/database.php';
     4|requireLogin();

$page_title = 'Kategori — Inventaris Bengkel Jaya';
     5|
     6|if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     7|    $action = $_POST['action'] ?? '';
     8|    
     9|    if ($action === 'tambah') {
    10|        $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
    11|        $stmt->execute([$_POST['nama_kategori'], $_POST['deskripsi']]);
    12|        setFlash('success', 'Kategori berhasil ditambahkan.');
    13|        redirect('kategori.php');
    14|    }
    15|    if ($action === 'edit') {
    16|        $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori=?, deskripsi=? WHERE id=?");
    17|        $stmt->execute([$_POST['nama_kategori'], $_POST['deskripsi'], $_POST['id']]);
    18|        setFlash('success', 'Kategori berhasil diupdate.');
    19|        redirect('kategori.php');
    20|    }
    21|    if ($action === 'hapus') {
    22|        // Cek apakah masih ada barang di kategori ini
    23|        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang WHERE kategori_id = ?");
    24|        $stmt->execute([$_POST['id']]);
    25|        $cnt = $stmt->fetch()['cnt'];
    26|        if ($cnt > 0) {
    27|            setFlash('error', 'Kategori masih digunakan oleh ' . $cnt . ' barang. Hapus barang terlebih dahulu.');
    28|        } else {
    29|            $stmt = $pdo->prepare("DELETE FROM kategori WHERE id=?");
    30|            $stmt->execute([$_POST['id']]);
    31|            setFlash('success', 'Kategori berhasil dihapus.');
    32|        }
    33|        redirect('kategori.php');
    34|    }
    35|}
    36|
    37|$kategori_list = $pdo->query("
    38|    SELECT k.*, COUNT(b.id) as jumlah_barang, COALESCE(SUM(b.stok), 0) as total_stok 
    39|    FROM kategori k 
    40|    LEFT JOIN barang b ON k.id = b.kategori_id AND b.aktif = 1
    41|    GROUP BY k.id 
    42|    ORDER BY k.nama_kategori
    43|")->fetchAll();
    44|
    45|<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
    46|?>
    47|
    48|<div class="main-content">
    49|    <div class="page-header">
    50|        <div>
    51|            <h1>Data Kategori</h1>
    52|            <div class="page-header-sub">Kelola kategori spare part</div>
    53|        </div>
    54|        <div class="page-header-actions">
    55|            <button onclick="openModal('tambah')" class="btn btn-accent">
    56|                <i class="fas fa-plus"></i> Tambah Kategori
    57|            </button>
    58|        </div>
    59|    </div>
    60|
    61|    <?php if ($msg = flash('success')): ?>
    62|        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    63|    <?php endif; ?>
    64|    <?php if ($msg = flash('error')): ?>
    65|        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $msg ?></div>
    66|    <?php endif; ?>
    67|
    68|    <div class="card">
    69|        <div class="card-body no-pad">
    70|            <div class="table-container">
    71|                <table class="table">
    72|                    <thead>
    73|                        <tr>
    74|                            <th>No</th>
    75|                            <th>Nama Kategori</th>
    76|                            <th>Deskripsi</th>
    77|                            <th>Jumlah Barang</th>
    78|                            <th>Total Stok</th>
    79|                            <th>Aksi</th>
    80|                        </tr>
    81|                    </thead>
    82|                    <tbody>
    83|                        <?php foreach ($kategori_list as $i => $k): ?>
    84|                        <tr>
    85|                            <td class="text-muted"><?= $i + 1 ?></td>
    86|                            <td class="fw-600"><?= sanitize($k['nama_kategori']) ?></td>
    87|                            <td class="text-muted"><?= sanitize($k['deskripsi'] ?? '-') ?></td>
    88|                            <td><span class="badge badge-info"><?= $k['jumlah_barang'] ?> item</span></td>
    89|                            <td><?= number_format($k['total_stok']) ?></td>
    90|                            <td>
    91|                                <div class="d-flex gap-1">
    92|                                    <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($k)) ?>)" class="btn btn-ghost btn-sm">
    93|                                        <i class="fas fa-pen text-warning"></i>
    94|                                    </button>
    95|                                    <button onclick="openModal('hapus', <?= htmlspecialchars(json_encode($k)) ?>)" class="btn btn-ghost btn-sm">
    96|                                        <i class="fas fa-trash text-danger"></i>
    97|                                    </button>
    98|                                </div>
    99|                            </td>
   100|                        </tr>
   101|                        <?php endforeach; ?>
   102|                    </tbody>
   103|                </table>
   104|            </div>
   105|        </div>
   106|    </div>
   107|</div>
   108|
   109|<!-- Modal Form -->
   110|<div class="modal-overlay" id="formModal">
   111|    <div class="modal">
   112|        <div class="modal-header">
   113|            <h3 id="modalTitle">Tambah Kategori</h3>
   114|            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
   115|        </div>
   116|        <form method="POST">
   117|            <div class="modal-body">
   118|                <input type="hidden" name="action" id="formAction" value="tambah">
   119|                <input type="hidden" name="id" id="formId">
   120|                <div class="form-group">
   121|                    <label class="form-label">Nama Kategori <span class="required">*</span></label>
   122|                    <input type="text" name="nama_kategori" id="formNama" class="form-control" required>
   123|                </div>
   124|                <div class="form-group">
   125|                    <label class="form-label">Deskripsi</label>
   126|                    <textarea name="deskripsi" id="formDeskripsi" class="form-control" rows="3" placeholder="Deskripsi singkat kategori..."></textarea>
   127|                </div>
   128|            </div>
   129|            <div class="modal-footer">
   130|                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
   131|                <button type="submit" class="btn btn-accent">Simpan</button>
   132|            </div>
   133|        </form>
   134|    </div>
   135|</div>
   136|
   137|<!-- Modal Hapus -->
   138|<div class="modal-overlay" id="hapusModal">
   139|    <div class="modal" style="max-width:400px;">
   140|        <div class="modal-header">
   141|            <h3>Konfirmasi Hapus</h3>
   142|            <button class="modal-close" onclick="closeHapus()"><i class="fas fa-times"></i></button>
   143|        </div>
   144|        <form method="POST">
   145|            <div class="modal-body">
   146|                <input type="hidden" name="action" value="hapus">
   147|                <input type="hidden" name="id" id="hapusId">
   148|                <p>Yakin ingin menghapus kategori <strong id="hapusNama"></strong>?</p>
   149|            </div>
   150|            <div class="modal-footer">
   151|                <button type="button" class="btn btn-outline" onclick="closeHapus()">Batal</button>
   152|                <button type="submit" class="btn btn-danger">Hapus</button>
   153|            </div>
   154|        </form>
   155|    </div>
   156|</div>
   157|
   158|<script>
   159|function openModal(action, data = null) {
   160|    if (action === 'hapus' && data) {
   161|        document.getElementById('hapusId').value = data.id;
   162|        document.getElementById('hapusNama').textContent = data.nama_kategori;
   163|        document.getElementById('hapusModal').classList.add('show');
   164|        return;
   165|    }
   166|    const modal = document.getElementById('formModal');
   167|    document.getElementById('modalTitle').textContent = action === 'edit' ? 'Edit Kategori' : 'Tambah Kategori';
   168|    document.getElementById('formAction').value = action;
   169|    if (action === 'edit' && data) {
   170|        document.getElementById('formId').value = data.id;
   171|        document.getElementById('formNama').value = data.nama_kategori;
   172|        document.getElementById('formDeskripsi').value = data.deskripsi || '';
   173|    } else {
   174|        document.getElementById('formId').value = '';
   175|        document.getElementById('formNama').value = '';
   176|        document.getElementById('formDeskripsi').value = '';
   177|    }
   178|    modal.classList.add('show');
   179|}
   180|function closeModal() { document.getElementById('formModal').classList.remove('show'); }
   181|function closeHapus() { document.getElementById('hapusModal').classList.remove('show'); }
   182|document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
   183|document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
   184|</script>
   185|
</body>
</html>
