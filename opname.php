<?php
// opname.php - Stok Opname
require_once 'config/database.php';
requireLogin();

$page_title = 'Stok Opname — Inventaris Bengkel Jaya';

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'buat') {
        // Generate no_opname
        $today = date('Ymd');
        $pattern = "SO-$today-%";
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM stok_opname WHERE no_opname LIKE ?");
        $stmt->execute([$pattern]);
        $count = $stmt->fetch()['cnt'] + 1;
        $no_opname = sprintf("SO-%s-%03d", $today, $count);
        
        // Create stok_opname
        $stmt = $pdo->prepare("INSERT INTO stok_opname (no_opname, tanggal_opname, keterangan, status, user_id) VALUES (?, ?, ?, 'draft', ?)");
        $stmt->execute([$no_opname, date('Y-m-d'), $_POST['keterangan'] ?? '', $_SESSION['user_id']]);
        $opname_id = $pdo->lastInsertId();
        
        // Auto-populate detail for ALL active barang
        $barang_all = $pdo->query("SELECT id, stok FROM barang WHERE aktif = 1 ORDER BY kode_barang")->fetchAll();
        $stmt_detail = $pdo->prepare("INSERT INTO stok_opname_detail (stok_opname_id, barang_id, stok_sistem, stok_fisik, selisih) VALUES (?, ?, ?, NULL, 0)");
        foreach ($barang_all as $b) {
            $stmt_detail->execute([$opname_id, $b['id'], $b['stok']]);
        }
        
        addAuditLog($pdo, 'create', 'stok_opname', $opname_id, null, ['no_opname' => $no_opname]);
        setFlash('success', "Stok opname $no_opname berhasil dibuat.");
        redirect("opname.php?action=view&id=$opname_id");
    }
    
    if ($action === 'simpan_fisik') {
        $opname_id = $_POST['opname_id'];
        $stok_fisik = $_POST['stok_fisik'] ?? [];
        $catatan = $_POST['catatan'] ?? [];
        
        $stmt_update = $pdo->prepare("UPDATE stok_opname_detail SET stok_fisik = ?, selisih = ? - stok_sistem, catatan = ? WHERE id = ? AND stok_opname_id = ?");
        foreach ($stok_fisik as $detail_id => $fisik) {
            $fisik_val = $fisik !== '' ? (int)$fisik : null;
            $selisih = $fisik_val !== null ? $fisik_val : 0;
            $stmt_update->execute([$fisik_val, $fisik_val ?? 0, $catatan[$detail_id] ?? '', $detail_id, $opname_id]);
        }
        
        setFlash('success', 'Data stok fisik berhasil disimpan.');
        redirect("opname.php?action=view&id=$opname_id");
    }
    
    if ($action === 'selesai') {
        $opname_id = $_POST['opname_id'];
        
        // Check for divergence
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM stok_opname_detail WHERE stok_opname_id = ? AND selisih != 0");
        $stmt->execute([$opname_id]);
        $divergence = $stmt->fetch()['cnt'];
        
        if ($divergence > 0) {
            $stmt = $pdo->prepare("UPDATE stok_opname SET status = 'divergence' WHERE id = ?");
            $stmt->execute([$opname_id]);
            setFlash('error', "Ditemukan $divergence barang dengan selisih stok. Silakan review dan resolve.");
        } else {
            $stmt = $pdo->prepare("UPDATE stok_opname SET status = 'resolved' WHERE id = ?");
            $stmt->execute([$opname_id]);
            setFlash('success', 'Stok opname selesai. Tidak ditemukan selisih.');
        }
        redirect("opname.php?action=view&id=$opname_id");
    }
    
    if ($action === 'resolve') {
        $opname_id = $_POST['opname_id'];
        
        // Update barang stok to stok_fisik for items with selisih
        $stmt = $pdo->prepare("
            SELECT sod.barang_id, sod.stok_fisik, sod.stok_sistem, b.nama_barang 
            FROM stok_opname_detail sod 
            JOIN barang b ON sod.barang_id = b.id 
            WHERE sod.stok_opname_id = ? AND sod.selisih != 0 AND sod.stok_fisik IS NOT NULL
        ");
        $stmt->execute([$opname_id]);
        $divergent_items = $stmt->fetchAll();
        
        $stmt_update = $pdo->prepare("UPDATE barang SET stok = ? WHERE id = ?");
        foreach ($divergent_items as $item) {
            $stmt_update->execute([$item['stok_fisik'], $item['barang_id']]);
        }
        
        $stmt = $pdo->prepare("UPDATE stok_opname SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$opname_id]);
        
        addAuditLog($pdo, 'update', 'stok_opname', $opname_id, null, ['status' => 'resolved', 'items_resolved' => count($divergent_items)]);
        setFlash('success', 'Stok telah disesuaikan. Opname selesai.');
        redirect("opname.php?action=view&id=$opname_id");
    }
}

// View mode
$view_id = $_GET['id'] ?? '';
$action_view = $_GET['action'] ?? '';

if ($action_view === 'view' && $view_id) {
    // Show opname detail
    $stmt = $pdo->prepare("SELECT so.*, u.nama_lengkap FROM stok_opname so LEFT JOIN users u ON so.user_id = u.id WHERE so.id = ?");
    $stmt->execute([$view_id]);
    $opname = $stmt->fetch();
    
    if (!$opname) {
        setFlash('error', 'Stok opname tidak ditemukan.');
        redirect('opname.php');
    }
    
    $stmt = $pdo->prepare("
        SELECT sod.*, b.kode_barang, b.nama_barang, b.satuan, b.stok as stok_terkini
        FROM stok_opname_detail sod 
        JOIN barang b ON sod.barang_id = b.id 
        WHERE sod.stok_opname_id = ? 
        ORDER BY b.kode_barang
    ");
    $stmt->execute([$view_id]);
    $detail_list = $stmt->fetchAll();
    
    // Stats
    $total_items = count($detail_list);
    $divergent_items = array_filter($detail_list, function($d) { return $d['selisih'] != 0; });
    $total_selisih_positif = 0;
    $total_selisih_negatif = 0;
    foreach ($detail_list as $d) {
        if ($d['selisih'] > 0) $total_selisih_positif += $d['selisih'];
        if ($d['selisih'] < 0) $total_selisih_negatif += abs($d['selisih']);
    }
    
    include 'includes/header.php';
    include 'includes/sidebar.php';
    ?>
    
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>Detail Stok Opname</h1>
                <div class="page-header-sub"><?= sanitize($opname['no_opname']) ?> — <?= tglIndo($opname['tanggal_opname']) ?></div>
            </div>
            <div class="page-header-actions">
                <a href="opname.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Kembali</a>
                <?php if ($opname['status'] === 'divergence'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="resolve">
                        <input type="hidden" name="opname_id" value="<?= $opname['id'] ?>">
                        <button type="submit" class="btn btn-accent" onclick="return confirm('Stok barang akan disesuaikan. Lanjutkan?')">
                            <i class="fas fa-check-double"></i> Resolve & Sesuaikan Stok
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?= $msg ?></div>
        <?php endif; ?>
        
        <!-- Info -->
        <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom:var(--space-lg);">
            <div class="stat-card">
                <div class="stat-card-label">Status</div>
                <?php if ($opname['status'] === 'draft'): ?>
                    <span class="badge badge-neutral">Draft</span>
                <?php elseif ($opname['status'] === 'divergence'): ?>
                    <span class="badge badge-warning">Divergence</span>
                <?php else: ?>
                    <span class="badge badge-success">Resolved</span>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Total Item</div>
                <div class="stat-card-value" style="font-size:1.5rem;"><?= $total_items ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Selisih (+)</div>
                <div class="stat-card-value text-success" style="font-size:1.5rem;"><?= $total_selisih_positif ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">Selisih (-)</div>
                <div class="stat-card-value text-danger" style="font-size:1.5rem;"><?= $total_selisih_negatif ?></div>
            </div>
        </div>
        
        <?php if ($opname['keterangan']): ?>
        <div class="alert alert-info mb-3"><i class="fas fa-info-circle"></i> <?= sanitize($opname['keterangan']) ?></div>
        <?php endif; ?>
        
        <!-- Detail Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-check"></i> Detail Item Opname</h3>
                <?php if ($opname['status'] === 'draft'): ?>
                <form method="POST" style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="action" value="simpan_fisik">
                    <input type="hidden" name="opname_id" value="<?= $opname['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Stok Fisik</button>
                    <button type="button" class="btn btn-accent btn-sm" onclick="if(confirm('Selesaikan opname?')) document.getElementById('selesaiForm').submit()">
                        <i class="fas fa-flag-checkered"></i> Selesai
                    </button>
                </form>
                <form id="selesaiForm" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="selesai">
                    <input type="hidden" name="opname_id" value="<?= $opname['id'] ?>">
                </form>
                <?php endif; ?>
            </div>
            
            <?php if ($opname['status'] === 'draft'): ?>
            <form method="POST">
                <input type="hidden" name="action" value="simpan_fisik">
                <input type="hidden" name="opname_id" value="<?= $opname['id'] ?>">
                <div class="card-body no-pad">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Nama Barang</th>
                                    <th>Satuan</th>
                                    <th>Stok Sistem</th>
                                    <th>Stok Fisik</th>
                                    <th>Selisih</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detail_list as $i => $d): ?>
                                <tr class="<?= $d['selisih'] != 0 ? 'row-divergence' : '' ?>">
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td><code class="text-mono"><?= sanitize($d['kode_barang']) ?></code></td>
                                    <td class="fw-600"><?= sanitize($d['nama_barang']) ?></td>
                                    <td class="text-muted"><?= sanitize($d['satuan']) ?></td>
                                    <td class="fw-600"><?= $d['stok_sistem'] ?></td>
                                    <td>
                                        <input type="number" name="stok_fisik[<?= $d['id'] ?>]" class="form-control" style="width:100px;" 
                                               value="<?= $d['stok_fisik'] ?>" min="0" 
                                               onchange="updateSelisih(this, <?= $d['stok_sistem'] ?>, 'selisih-<?= $d['id'] ?>')">
                                    </td>
                                    <td>
                                        <span id="selisih-<?= $d['id'] ?>" class="fw-700 <?= $d['selisih'] > 0 ? 'text-success' : ($d['selisih'] < 0 ? 'text-danger' : '') ?>">
                                            <?= $d['selisih'] > 0 ? '+' . $d['selisih'] : $d['selisih'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text" name="catatan[<?= $d['id'] ?>]" class="form-control" style="width:150px;" 
                                               value="<?= sanitize($d['catatan'] ?? '') ?>" placeholder="Catatan...">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer" style="padding:var(--space-md) var(--space-lg);border-top:1px solid var(--border);">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Semua</button>
                </div>
            </form>
            
            <?php else: ?>
            <!-- Read-only view for completed opname -->
            <div class="card-body no-pad">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th>Stok Sistem</th>
                                <th>Stok Fisik</th>
                                <th>Selisih</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail_list as $i => $d): ?>
                            <tr class="<?= $d['selisih'] != 0 ? 'row-divergence' : '' ?>">
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td><code class="text-mono"><?= sanitize($d['kode_barang']) ?></code></td>
                                <td class="fw-600"><?= sanitize($d['nama_barang']) ?></td>
                                <td class="text-muted"><?= sanitize($d['satuan']) ?></td>
                                <td class="fw-600"><?= $d['stok_sistem'] ?></td>
                                <td class="fw-600"><?= $d['stok_fisik'] ?? '-' ?></td>
                                <td>
                                    <?php if ($d['selisih'] > 0): ?>
                                        <span class="badge badge-success">+<?= $d['selisih'] ?></span>
                                    <?php elseif ($d['selisih'] < 0): ?>
                                        <span class="badge badge-danger"><?= $d['selisih'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= sanitize($d['catatan'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function updateSelisih(input, stokSistem, selisihId) {
        const fisik = parseInt(input.value) || 0;
        const selisih = fisik - stokSistem;
        const el = document.getElementById(selisihId);
        el.textContent = selisih > 0 ? '+' + selisih : selisih;
        el.className = 'fw-700 ' + (selisih > 0 ? 'text-success' : (selisih < 0 ? 'text-danger' : ''));
        // Highlight row
        const row = input.closest('tr');
        row.className = selisih !== 0 ? 'row-divergence' : '';
    }
    </script>
    
    </body>
    </html>
    <?php
    exit;
}

// List view
$search = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(so.no_opname LIKE ? OR so.keterangan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $where[] = "so.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where);

$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page_num - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM stok_opname so WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT so.*, u.nama_lengkap,
        (SELECT COUNT(*) FROM stok_opname_detail WHERE stok_opname_id = so.id) as total_item,
        (SELECT COUNT(*) FROM stok_opname_detail WHERE stok_opname_id = so.id AND selisih != 0) as divergent_item
    FROM stok_opname so 
    LEFT JOIN users u ON so.user_id = u.id 
    WHERE $where_sql 
    ORDER BY so.created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$opname_list = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Stok Opname</h1>
            <div class="page-header-sub">Buat dan kelola sesi stok opname</div>
        </div>
        <div class="page-header-actions">
            <button onclick="openModal()" class="btn btn-accent">
                <i class="fas fa-plus"></i> Buat Opname Baru
            </button>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="card">
        <div class="card-header">
            <form method="GET" class="toolbar" style="width:100%;margin:0;">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Cari no opname..." value="<?= sanitize($search) ?>">
                </div>
                <select name="status" class="form-select" style="width:auto;min-width:140px;" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="divergence" <?= $status_filter === 'divergence' ? 'selected' : '' ?>>Divergence</option>
                    <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>

        <div class="card-body no-pad">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Opname</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Jumlah Item</th>
                            <th>Divergen</th>
                            <th>Status</th>
                            <th>User</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($opname_list as $o): ?>
                        <tr>
                            <td><code class="text-mono"><?= sanitize($o['no_opname']) ?></code></td>
                            <td class="text-muted"><?= tglPendek($o['tanggal_opname']) ?></td>
                            <td class="text-muted"><?= sanitize($o['keterangan'] ?? '-') ?></td>
                            <td><span class="badge badge-neutral"><?= $o['total_item'] ?> item</span></td>
                            <td>
                                <?php if ($o['divergent_item'] > 0): ?>
                                    <span class="badge badge-danger"><?= $o['divergent_item'] ?> item</span>
                                <?php else: ?>
                                    <span class="badge badge-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($o['status'] === 'draft'): ?>
                                    <span class="badge badge-neutral">Draft</span>
                                <?php elseif ($o['status'] === 'divergence'): ?>
                                    <span class="badge badge-warning">Divergence</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Resolved</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= sanitize($o['nama_lengkap'] ?? '-') ?></td>
                            <td>
                                <a href="opname.php?action=view&id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm" title="Lihat Detail">
                                    <i class="fas fa-eye text-primary"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($opname_list)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-clipboard-list"></i></div>
                                    <h3>Belum ada stok opname</h3>
                                    <p>Klik tombol "Buat Opname Baru" untuk memulai.</p>
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
                <a href="?page=<?= $page_num - 1 ?>&q=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                   class="pagination-btn <?= $page_num <= 1 ? 'disabled' : '' ?>">←</a>
                <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                       class="pagination-btn <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page_num + 1 ?>&q=<?= urlencode($search) ?>&status=<?= $status_filter ?>" 
                   class="pagination-btn <?= $page_num >= $total_pages ? 'disabled' : '' ?>">→</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Buat Opname -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Buat Stok Opname Baru</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="buat">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>Sistem akan otomatis membuat detail untuk semua barang aktif dengan stok sistem saat ini.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="3" placeholder="Keterangan opname (opsional)..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-accent"><i class="fas fa-plus"></i> Buat Opname</button>
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
