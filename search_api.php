<?php
// search_api.php - Global search API endpoint
require_once 'config/database.php';
requireLogin();

$query = trim($_GET['q'] ?? '');

header('Content-Type: application/json; charset=utf-8');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT b.kode_barang, b.nama_barang, b.stok, b.stok_minimum, k.nama_kategori
    FROM barang b
    LEFT JOIN kategori k ON b.kategori_id = k.id
    WHERE b.aktif = 1 
      AND (b.nama_barang LIKE ? OR b.kode_barang LIKE ?)
    ORDER BY b.nama_barang ASC
    LIMIT 10
");

$search = "%$query%";
$stmt->execute([$search, $search]);
$results = $stmt->fetchAll();

echo json_encode($results);
?>
