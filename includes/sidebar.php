<!-- includes/sidebar.php -->
<?php
// Low stock count for badge
if (isset($pdo)) {
    $stmt_lowstock = $pdo->prepare("SELECT COUNT(*) as cnt FROM barang WHERE stok <= stok_minimum AND aktif = 1");
    $stmt_lowstock->execute();
    $low_stock_count = $stmt_lowstock->fetch()['cnt'];
} else {
    $low_stock_count = 0;
}
?>

<!-- Global Search Bar -->
<div class="global-search-header" id="globalSearchContainer">
    <div class="global-search-wrapper">
        <i class="fas fa-search global-search-icon"></i>
        <input type="text" id="globalSearchInput" class="global-search-input" placeholder="Cari barang berdasarkan nama atau kode..." autocomplete="off">
        <div class="global-search-results" id="globalSearchResults"></div>
    </div>
</div>

<button class="mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('open')">
    <i class="fas fa-bars"></i>
</button>

<nav class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="fas fa-motorcycle"></i>
        </div>
        <div class="sidebar-brand-text">
            <h2>Bengkel Jaya</h2>
            <small>Sistem Inventaris</small>
        </div>
    </div>

    <div class="sidebar-nav">
        <div class="nav-section">Menu Utama</div>
        <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
            <?php if ($low_stock_count > 0): ?>
                <span class="nav-badge"><?= $low_stock_count ?></span>
            <?php endif; ?>
        </a>
        <a href="barang.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'barang.php' ? 'active' : '' ?>">
            <i class="fas fa-boxes-stacked"></i>
            <span>Data Barang</span>
        </a>
        <a href="kategori.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'kategori.php' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i>
            <span>Kategori</span>
        </a>
        <a href="supplier.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'supplier.php' ? 'active' : '' ?>">
            <i class="fas fa-truck"></i>
            <span>Supplier</span>
        </a>

        <div class="nav-section">Transaksi</div>
        <a href="masuk.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'masuk.php' ? 'active' : '' ?>">
            <i class="fas fa-arrow-down"></i>
            <span>Barang Masuk</span>
        </a>
        <a href="keluar.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'keluar.php' ? 'active' : '' ?>">
            <i class="fas fa-arrow-up"></i>
            <span>Barang Keluar</span>
        </a>
        <a href="opname.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'opname.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Stok Opname</span>
        </a>

        <div class="nav-section">Laporan</div>
        <a href="laporan.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'laporan.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Laporan</span>
        </a>

        <?php if (isAdmin()): ?>
        <div class="nav-section">Pengaturan</div>
        <a href="users.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i>
            <span>Kelola User</span>
        </a>
        <a href="log.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'log.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Log Aktivitas</span>
        </a>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= sanitize($_SESSION['nama_lengkap']) ?></div>
                <div class="sidebar-user-role"><?= sanitize($_SESSION['role']) ?></div>
            </div>
        </div>
        <a href="logout.php" class="btn btn-outline btn-sm w-full">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<!-- JS includes -->
<script src="assets/js/validation.js"></script>
<script src="assets/js/app.js"></script>
