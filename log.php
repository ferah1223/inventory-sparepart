<?php
// log.php - Audit Log Viewer (Admin only)
require_once 'config/database.php';
requireAdmin();

$page_title = 'Log Aktivitas — Inventaris Bengkel Jaya';

// Filters
$filter_user = $_GET['user'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_table = $_GET['table'] ?? '';
$filter_dari = $_GET['dari'] ?? '';
$filter_sampai = $_GET['sampai'] ?? '';

$where = ["1=1"];
$params = [];

if ($filter_user) {
    $where[] = "al.user_id = ?";
    $params[] = $filter_user;
}
if ($filter_action) {
    $where[] = "al.action = ?";
    $params[] = $filter_action;
}
if ($filter_table) {
    $where[] = "al.table_name = ?";
    $params[] = $filter_table;
}
if ($filter_dari) {
    $where[] = "DATE(al.created_at) >= ?";
    $params[] = $filter_dari;
}
if ($filter_sampai) {
    $where[] = "DATE(al.created_at) <= ?";
    $params[] = $filter_sampai;
}

$where_sql = implode(' AND ', $where);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM audit_log al WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['cnt'];
$total_pages = ceil($total / $per_page);

$stmt = $pdo->prepare("
    SELECT al.*, u.nama_lengkap, u.username 
    FROM audit_log al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE $where_sql 
    ORDER BY al.created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$log_list = $stmt->fetchAll();

// Users for filter dropdown
$users_list = $pdo->query("SELECT id, nama_lengkap FROM users ORDER BY nama_lengkap")->fetchAll();

// Distinct tables
$tables_list = $pdo->query("SELECT DISTINCT table_name FROM audit_log ORDER BY table_name")->fetchAll();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Log Aktivitas</h1>
            <div class="page-header-sub">Riwayat semua perubahan data dalam sistem</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:var(--space-lg);">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filter Log</h3>
            <?php if ($filter_user || $filter_action || $filter_table || $filter_dari || $filter_sampai): ?>
                <a href="log.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Reset</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="GET" class="toolbar" style="margin:0;">
                <select name="user" class="form-select" style="width:auto;min-width:160px;">
                    <option value="">Semua User</option>
                    <?php foreach ($users_list as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?= sanitize($u['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="action" class="form-select" style="width:auto;min-width:130px;">
                    <option value="">Semua Aksi</option>
                    <option value="create" <?= $filter_action === 'create' ? 'selected' : '' ?>>Create</option>
                    <option value="update" <?= $filter_action === 'update' ? 'selected' : '' ?>>Update</option>
                    <option value="delete" <?= $filter_action === 'delete' ? 'selected' : '' ?>>Delete</option>
                </select>
                <select name="table" class="form-select" style="width:auto;min-width:130px;">
                    <option value="">Semua Tabel</option>
                    <?php foreach ($tables_list as $t): ?>
                        <option value="<?= sanitize($t['table_name']) ?>" <?= $filter_table === $t['table_name'] ? 'selected' : '' ?>><?= sanitize($t['table_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="dari" class="form-control" style="width:auto;" value="<?= sanitize($filter_dari) ?>" placeholder="Dari">
                <input type="date" name="sampai" class="form-control" style="width:auto;" value="<?= sanitize($filter_sampai) ?>" placeholder="Sampai">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body no-pad">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Aksi</th>
                            <th>Tabel</th>
                            <th>ID Record</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log_list as $log): ?>
                        <tr>
                            <td class="text-muted text-mono"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td class="fw-600"><?= sanitize($log['nama_lengkap'] ?? 'System') ?></td>
                            <td>
                                <?php if ($log['action'] === 'create'): ?>
                                    <span class="badge badge-success"><i class="fas fa-plus"></i> Create</span>
                                <?php elseif ($log['action'] === 'update'): ?>
                                    <span class="badge badge-info"><i class="fas fa-pen"></i> Update</span>
                                <?php elseif ($log['action'] === 'delete'): ?>
                                    <span class="badge badge-danger"><i class="fas fa-trash"></i> Delete</span>
                                <?php endif; ?>
                            </td>
                            <td><code class="text-mono"><?= sanitize($log['table_name']) ?></code></td>
                            <td><?= $log['record_id'] ?></td>
                            <td>
                                <?php if ($log['old_values'] || $log['new_values']): ?>
                                    <button onclick="toggleDetail('detail-<?= $log['id'] ?>')" class="btn btn-ghost btn-sm">
                                        <i class="fas fa-eye"></i> Lihat
                                    </button>
                                    <div id="detail-<?= $log['id'] ?>" class="log-detail" style="display:none;">
                                        <?php if ($log['old_values']): ?>
                                            <div class="log-detail-section">
                                                <strong class="text-danger">Data Lama:</strong>
                                                <pre class="log-pre"><?php 
                                                    $old = json_decode($log['old_values'], true);
                                                    if ($old) {
                                                        foreach ($old as $k => $v) {
                                                            echo sanitize($k) . ': ' . sanitize($v ?? 'null') . "\n";
                                                        }
                                                    } else {
                                                        echo sanitize($log['old_values']);
                                                    }
                                                ?></pre>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($log['new_values']): ?>
                                            <div class="log-detail-section">
                                                <strong class="text-success">Data Baru:</strong>
                                                <pre class="log-pre"><?php 
                                                    $new = json_decode($log['new_values'], true);
                                                    if ($new) {
                                                        foreach ($new as $k => $v) {
                                                            echo sanitize($k) . ': ' . sanitize($v ?? 'null') . "\n";
                                                        }
                                                    } else {
                                                        echo sanitize($log['new_values']);
                                                    }
                                                ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($log_list)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-state-icon"><i class="fas fa-clipboard-list"></i></div>
                                    <h3>Tidak ada log ditemukan</h3>
                                    <p>Coba ubah filter atau periode pencarian.</p>
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
                Menampilkan <?= $offset + 1 ?>-<?= min($offset + $per_page, $total) ?> dari <?= $total ?> log
            </div>
            <div class="pagination-buttons">
                <?php 
                $query_params = http_build_query(['user'=>$filter_user,'action'=>$filter_action,'table'=>$filter_table,'dari'=>$filter_dari,'sampai'=>$filter_sampai]);
                ?>
                <a href="?page=<?= $page - 1 ?>&<?= $query_params ?>" 
                   class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">←</a>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&<?= $query_params ?>" 
                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page + 1 ?>&<?= $query_params ?>" 
                   class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">→</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetail(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>
