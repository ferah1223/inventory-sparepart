     1|<?php
     2|// users.php - Kelola User (Admin only)
     3|require_once 'config/database.php';
     4|requireAdmin();
     5|
     6|if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     7|    $action = $_POST['action'] ?? '';
     8|    
     9|    if ($action === 'tambah') {
    10|        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    11|        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
    12|        $stmt->execute([$_POST['username'], $hash, $_POST['nama_lengkap'], $_POST['role']]);
    13|        setFlash('success', 'User berhasil ditambahkan.');
    14|        redirect('users.php');
    15|    }
    16|    if ($action === 'edit') {
    17|        $sql = "UPDATE users SET nama_lengkap=?, role=?";
    18|        $params = [$_POST['nama_lengkap'], $_POST['role']];
    19|        
    20|        if (!empty($_POST['password'])) {
    21|            $sql .= ", password=?";
    22|            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
    23|        }
    24|        
    25|        $sql .= " WHERE id=?";
    26|        $params[] = $_POST['id'];
    27|        
    28|        $stmt = $pdo->prepare($sql);
    29|        $stmt->execute($params);
    30|        setFlash('success', 'User berhasil diupdate.');
    31|        redirect('users.php');
    32|    }
    33|    if ($action === 'toggle') {
    34|        $stmt = $pdo->prepare("UPDATE users SET aktif = NOT aktif WHERE id = ?");
    35|        $stmt->execute([$_POST['id']]);
    36|        redirect('users.php');
    37|    }
    38|}
    39|
    40|$users = $pdo->query("SELECT * FROM users ORDER BY role, nama_lengkap")->fetchAll();
    41|
    42|<?php include 'includes/header.php'; ?>

include 'includes/sidebar.php';
    43|?>
    44|
    45|<div class="main-content">
    46|    <div class="page-header">
    47|        <div>
    48|            <h1>Kelola User</h1>
    49|            <div class="page-header-sub">Manajemen akun pengguna sistem</div>
    50|        </div>
    51|        <div class="page-header-actions">
    52|            <button onclick="openModal('tambah')" class="btn btn-accent">
    53|                <i class="fas fa-plus"></i> Tambah User
    54|            </button>
    55|        </div>
    56|    </div>
    57|
    58|    <?php if ($msg = flash('success')): ?>
    59|        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    60|    <?php endif; ?>
    61|
    62|    <div class="card">
    63|        <div class="card-body no-pad">
    64|            <table class="table">
    65|                <thead>
    66|                    <tr>
    67|                        <th>No</th>
    68|                        <th>Username</th>
    69|                        <th>Nama Lengkap</th>
    70|                        <th>Role</th>
    71|                        <th>Status</th>
    72|                        <th>Aksi</th>
    73|                    </tr>
    74|                </thead>
    75|                <tbody>
    76|                    <?php foreach ($users as $i => $u): ?>
    77|                    <tr>
    78|                        <td class="text-muted"><?= $i + 1 ?></td>
    79|                        <td><code class="text-mono"><?= sanitize($u['username']) ?></code></td>
    80|                        <td class="fw-600"><?= sanitize($u['nama_lengkap']) ?></td>
    81|                        <td>
    82|                            <?php if ($u['role'] === 'admin'): ?>
    83|                                <span class="badge badge-info">Admin</span>
    84|                            <?php else: ?>
    85|                                <span class="badge badge-neutral">Operator</span>
    86|                            <?php endif; ?>
    87|                        </td>
    88|                        <td>
    89|                            <?php if ($u['aktif']): ?>
    90|                                <span class="badge badge-success">Aktif</span>
    91|                            <?php else: ?>
    92|                                <span class="badge badge-danger">Nonaktif</span>
    93|                            <?php endif; ?>
    94|                        </td>
    95|                        <td>
    96|                            <div class="d-flex gap-1">
    97|                                <button onclick="openModal('edit', <?= htmlspecialchars(json_encode($u)) ?>)" class="btn btn-ghost btn-sm">
    98|                                    <i class="fas fa-pen text-warning"></i>
    99|                                </button>
   100|                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
   101|                                <form method="POST" style="display:inline;">
   102|                                    <input type="hidden" name="action" value="toggle">
   103|                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
   104|                                    <button type="submit" class="btn btn-ghost btn-sm" title="<?= $u['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
   105|                                        <i class="fas fa-<?= $u['aktif'] ? 'ban text-danger' : 'check text-success' ?>"></i>
   106|                                    </button>
   107|                                </form>
   108|                                <?php endif; ?>
   109|                            </div>
   110|                        </td>
   111|                    </tr>
   112|                    <?php endforeach; ?>
   113|                </tbody>
   114|            </table>
   115|        </div>
   116|    </div>
   117|</div>
   118|
   119|<div class="modal-overlay" id="formModal">
   120|    <div class="modal">
   121|        <div class="modal-header">
   122|            <h3 id="modalTitle">Tambah User</h3>
   123|            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
   124|        </div>
   125|        <form method="POST">
   126|            <div class="modal-body">
   127|                <input type="hidden" name="action" id="formAction" value="tambah">
   128|                <input type="hidden" name="id" id="formId">
   129|                <div class="form-group">
   130|                    <label class="form-label">Username <span class="required">*</span></label>
   131|                    <input type="text" name="username" id="formUsername" class="form-control" required>
   132|                </div>
   133|                <div class="form-group">
   134|                    <label class="form-label">Nama Lengkap <span class="required">*</span></label>
   135|                    <input type="text" name="nama_lengkap" id="formNama" class="form-control" required>
   136|                </div>
   137|                <div class="form-row">
   138|                    <div class="form-group">
   139|                        <label class="form-label">Role</label>
   140|                        <select name="role" id="formRole" class="form-select">
   141|                            <option value="operator">Operator</option>
   142|                            <option value="admin">Admin</option>
   143|                        </select>
   144|                    </div>
   145|                    <div class="form-group">
   146|                        <label class="form-label">Password <span id="passRequired">*</span></label>
   147|                        <input type="password" name="password" id="formPassword" class="form-control">
   148|                        <div class="form-hint" id="passHint">Kosongkan jika tidak ingin mengubah password</div>
   149|                    </div>
   150|                </div>
   151|            </div>
   152|            <div class="modal-footer">
   153|                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
   154|                <button type="submit" class="btn btn-accent">Simpan</button>
   155|            </div>
   156|        </form>
   157|    </div>
   158|</div>
   159|
   160|<script>
   161|function openModal(action, data = null) {
   162|    const modal = document.getElementById('formModal');
   163|    document.getElementById('modalTitle').textContent = action === 'edit' ? 'Edit User' : 'Tambah User';
   164|    document.getElementById('formAction').value = action;
   165|    
   166|    if (action === 'edit' && data) {
   167|        document.getElementById('formId').value = data.id;
   168|        document.getElementById('formUsername').value = data.username;
   169|        document.getElementById('formUsername').readOnly = true;
   170|        document.getElementById('formNama').value = data.nama_lengkap;
   171|        document.getElementById('formRole').value = data.role;
   172|        document.getElementById('formPassword').value = '';
   173|        document.getElementById('formPassword').required = false;
   174|        document.getElementById('passRequired').style.display = 'none';
   175|        document.getElementById('passHint').style.display = 'block';
   176|    } else {
   177|        document.getElementById('formId').value = '';
   178|        document.getElementById('formUsername').value = '';
   179|        document.getElementById('formUsername').readOnly = false;
   180|        document.getElementById('formNama').value = '';
   181|        document.getElementById('formRole').value = 'operator';
   182|        document.getElementById('formPassword').value = '';
   183|        document.getElementById('formPassword').required = true;
   184|        document.getElementById('passRequired').style.display = 'inline';
   185|        document.getElementById('passHint').style.display = 'none';
   186|    }
   187|    modal.classList.add('show');
   188|}
   189|function closeModal() { document.getElementById('formModal').classList.remove('show'); }
   190|document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
   191|document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.show').forEach(m => m.classList.remove('show')); });
   192|</script>
   193|
</body>
</html>
