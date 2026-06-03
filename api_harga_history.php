<?php
// api_harga_history.php - Price history AJAX endpoint
require_once 'config/database.php';
requireLogin();

$barang_id = $_GET['barang_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT hh.*, u.nama_lengkap 
    FROM harga_history hh 
    LEFT JOIN users u ON hh.user_id = u.id 
    WHERE hh.barang_id = ? 
    ORDER BY hh.created_at DESC
");
$stmt->execute([$barang_id]);
$history = $stmt->fetchAll();

if (empty($history)):
?>
<div class="text-center text-muted" style="padding:24px;">
    <i class="fas fa-clock" style="font-size:2rem;opacity:0.3;margin-bottom:8px;display:block;"></i>
    Belum ada riwayat perubahan harga.
</div>
<?php else: ?>
<table class="price-history-table">
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Harga Beli</th>
            <th>Harga Jual</th>
            <th>User</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($history as $h): ?>
        <tr>
            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
            <td>
                <?php if ($h['harga_beli_lama'] != $h['harga_beli_baru']): ?>
                    <span class="text-muted" style="text-decoration:line-through;"><?= rupiah($h['harga_beli_lama']) ?></span>
                    <i class="fas fa-arrow-right" style="font-size:0.7rem;margin:0 4px;"></i>
                    <span class="fw-600"><?= rupiah($h['harga_beli_baru']) ?></span>
                <?php else: ?>
                    <span class="text-muted"><?= rupiah($h['harga_beli_baru']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($h['harga_jual_lama'] != $h['harga_jual_baru']): ?>
                    <span class="text-muted" style="text-decoration:line-through;"><?= rupiah($h['harga_jual_lama']) ?></span>
                    <i class="fas fa-arrow-right" style="font-size:0.7rem;margin:0 4px;"></i>
                    <span class="fw-600"><?= rupiah($h['harga_jual_baru']) ?></span>
                <?php else: ?>
                    <span class="text-muted"><?= rupiah($h['harga_jual_baru']) ?></span>
                <?php endif; ?>
            </td>
            <td class="text-muted"><?= sanitize($h['nama_lengkap'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
