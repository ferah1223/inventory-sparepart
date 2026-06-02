<?php
// users.php - Kelola User (Admin only)
require_once 'config/database.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'tambah') {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['username'], $hash, $_POST['nama_lengkap'], $_POST['role']]);
        setFlash('success', 'User berhasil ditambahkan.');
        redirect('users.php');
    }
    if ($action === 'edit') {
        $sql = "UPDATE users SET nama_lengkap=?, role=?";
        $params = [$_POST['nama_lengkap'], $_POST['role']];
        
        if (!empty($_POST['password'])) {
            $sql .= ", password=?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        $sql .= " WHERE id=?";
        $params[] = $_POST['id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        setFlash('success', 'User berhasil diupdate.');
        redirect('users.php');
    }
    if ($action === 'toggle') {
        $stmt = $pdo->prepare("UPDATE users SET aktif = NOT aktif WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        redirect('users.php');
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, nama_lengkap")->fetchAll();

<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>Kelola User</h1>
            <div class="page-header-sub">Manajemen akun pengguna sistem</div>
        </div>
        <div class="page-header-actions">
            <button onclick="openModal('tambah')" class="btn btn-accent">
                <i class="fas fa-plus"></i> Tambah User
            </button>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body no-pad">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><code class="text-mono"><?= sanitize($u['username']) ?></code></td>
                        <td class="fw-600"><?= sanitize($u['nama_lengkap']) ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-info">Admin</span>
                            <?php else: ?>
                                <span class="badge badge-neutral">Operator</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['aktif']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($u)) ?>)" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-pen text-warning"></i>
                                </button>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" title="<?= $u['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="fas fa-<?= $u['aktif'] ? 'ban text-danger' : 'check text-success' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah User</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="id" id="formId">
                <div class="form-group">
                    <label class="form-label">Username <span class="required">*</span></label>
                    <input type="text" name="username" id="formUsername" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" name="nama_lengkap" id="formNama" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="formRole" class="form-select">
                            <option value="operator">Operator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span id="passRequired">*</span></label>
                        <input type="password" name="password" id="formPassword" class="form-control">
                        <div class="form-hint" id="passHint">Kosongkan jika tidak ingin mengubah password</div>
                    </div>
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
function openModal(action, data = null) {
    const modal = document.getElementById('formModal');
    document.getElementById('modalTitle').textContent = action === 'edit' ? 'Edit User' : 'Tambah User';
    document.getElementById('formAction').value = action;
    
    if (action === 'edit' && data) {
        document.getElementById('formId').value = data.id;
        document.getElementById('formUsername').value = data.username;
        document.getElementById('formUsername').readOnly = true;
        document.getElementById('formNama').value = data.nama_lengkap;
        document.getElementById('formRole').value = data.role;
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = false;
        document.getElementById('passRequired').style.display = 'none';
        document.getElementById('passHint').style.display = 'block';
    } else {
        document.getElementById('formId').value = '';
        document.getElementById('formUsername').value = '';
        document.getElementById('formUsername').readOnly = false;
        document.getElementById('formNama').value = '';
        document.getElementById('formRole').value = 'operator';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = true;
        document.getElementById('passRequired').style.display = 'inline';
        document.getElementById('passHint').style.display = 'none';
    }
    modal.classList.add('show');
}
function closeModal() { document.getElementById('formModal').classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
</script>

</body>
</html>
