<?php
// config/database.php
// Koneksi database MySQL + Helper Functions

$host = 'localhost';
$dbname = 'inventory_sparepart';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("<div style='padding:40px;text-align:center;font-family:sans-serif;'>
         <h2 style='color:#dc2626;'>Koneksi Database Gagal</h2>
         <p>Pastikan MySQL sudah berjalan dan database <code>inventory_sparepart</code> sudah dibuat.</p>
         <p style='color:#666;'>Error: " . $e->getMessage() . "</p>
         <p><strong>Cara fix:</strong></p>
         <pre style='background:#f1f5f9;padding:15px;border-radius:8px;text-align:left;display:inline-block;'>mysql -u root -p < database.sql</pre>
         </div>");
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// Helper Functions
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['flash_error'] = 'Anda tidak memiliki akses ke halaman ini.';
        header('Location: dashboard.php');
        exit;
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function tglIndo($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = date('j', strtotime($date));
    $m = $bulan[(int)date('n', strtotime($date))];
    $Y = date('Y', strtotime($date));
    return "$d $m $Y";
}

function tglPendek($date) {
    return date('d/m/Y', strtotime($date));
}

function flash($key) {
    $full_key = "flash_$key";
    if (isset($_SESSION[$full_key])) {
        $msg = $_SESSION[$full_key];
        unset($_SESSION[$full_key]);
        return $msg;
    }
    return null;
}

function setFlash($key, $message) {
    $_SESSION["flash_$key"] = $message;
}

function generateNoTransaksi($prefix, $pdo, $table, $column = 'no_transaksi') {
    $today = date('Ymd');
    $pattern = "$prefix-$today-%";
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM $table WHERE $column LIKE ?");
    $stmt->execute([$pattern]);
    $count = $stmt->fetch()['cnt'] + 1;
    return sprintf("%s-%s-%03d", $prefix, $today, $count);
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function addAuditLog($pdo, $action, $table, $recordId, $oldValues = null, $newValues = null) {
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $table,
        $recordId,
        $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
        $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);
}
?>
