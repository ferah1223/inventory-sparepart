-- ============================================
-- Database: inventory_sparepart
-- Sistem Inventarisasi Barang Gudang
-- Spare Part Sepeda Motor - Bengkel Jaya
-- ============================================

CREATE DATABASE IF NOT EXISTS inventory_sparepart
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE inventory_sparepart;

-- ============================================
-- Tabel Users
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'operator') DEFAULT 'operator',
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Tabel Kategori
-- ============================================
CREATE TABLE kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Tabel Barang (Spare Part)
-- ============================================
CREATE TABLE barang (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_barang VARCHAR(20) NOT NULL UNIQUE,
    nama_barang VARCHAR(150) NOT NULL,
    kategori_id INT,
    satuan VARCHAR(30) DEFAULT 'pcs',
    stok INT DEFAULT 0,
    stok_minimum INT DEFAULT 5,
    harga_beli DECIMAL(12,2) DEFAULT 0,
    harga_jual DECIMAL(12,2) DEFAULT 0,
    lokasi_rak VARCHAR(50),
    deskripsi TEXT,
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL,
    INDEX idx_kategori (kategori_id),
    INDEX idx_kode (kode_barang),
    INDEX idx_nama (nama_barang)
) ENGINE=InnoDB;

-- ============================================
-- Tabel Barang Masuk
-- ============================================
CREATE TABLE barang_masuk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_transaksi VARCHAR(30) NOT NULL UNIQUE,
    barang_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga_satuan DECIMAL(12,2) DEFAULT 0,
    total_harga DECIMAL(12,2) DEFAULT 0,
    tanggal_masuk DATE NOT NULL,
    supplier VARCHAR(150),
    keterangan TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tanggal (tanggal_masuk),
    INDEX idx_barang (barang_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabel Barang Keluar
-- ============================================
CREATE TABLE barang_keluar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_transaksi VARCHAR(30) NOT NULL UNIQUE,
    barang_id INT NOT NULL,
    jumlah INT NOT NULL,
    harga_satuan DECIMAL(12,2) DEFAULT 0,
    total_harga DECIMAL(12,2) DEFAULT 0,
    tanggal_keluar DATE NOT NULL,
    tujuan VARCHAR(150),
    penerima VARCHAR(100),
    keterangan TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tanggal (tanggal_keluar),
    INDEX idx_barang (barang_id)
) ENGINE=InnoDB;

-- ============================================
-- Data Awal: Users
-- ============================================
-- Password default: "password" (bcrypt hash)
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin'),
('operator1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso', 'operator');

-- ============================================
-- Data Awal: Kategori
-- ============================================
INSERT INTO kategori (nama_kategori, deskripsi) VALUES
('Oli & Pelumas', 'Oli mesin, oli gardan, gemuk, dan pelumas lainnya'),
('Kampas Rem', 'Kampas rem depan, belakang, dan cakram untuk berbagai tipe motor'),
('Ban & Velg', 'Ban dalam, ban luar, velg racing dan standar'),
('Busi & Kelistikan', 'Busi, kabel busi, aki, koil, dan komponen kelistikan'),
('Rantai & Gir', 'Rantai, gir depan, gir belakang, dan bearing rantai'),
('Filter & Saringan', 'Filter oli, filter udara, saringan bensin'),
('Lampu & Penerangan', 'Lampu utama, lampu senja, lampu sein, reflektor'),
('Komponen Mesin', 'Piston, ring piston, kampas kopling, seal, bearing');

-- ============================================
-- Data Awal: Barang
-- ============================================
INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan, stok, stok_minimum, harga_beli, harga_jual, lokasi_rak) VALUES
('OLI-001', 'Oli Mesin Yamalube 10W-40 800ml', 1, 'botol', 45, 10, 35000, 45000, 'Rak A1'),
('OLI-002', 'Oli Mesin Federal 10W-30 800ml', 1, 'botol', 30, 10, 28000, 38000, 'Rak A1'),
('OLI-003', 'Oli Gardan Matic Yamalube 100ml', 1, 'botol', 25, 10, 18000, 25000, 'Rak A2'),
('OLI-004', 'Oli Mesin Castrol Power1 800ml', 1, 'botol', 20, 8, 42000, 55000, 'Rak A2'),
('KRM-001', 'Kampas Rem Depan CBS Honda', 2, 'set', 20, 5, 45000, 65000, 'Rak B1'),
('KRM-002', 'Kampas Rem Belakang Yamaha', 2, 'set', 15, 5, 35000, 50000, 'Rak B1'),
('KRM-003', 'Kampas Rem Cakram Nissin', 2, 'set', 18, 5, 55000, 75000, 'Rak B2'),
('BAN-001', 'Ban Dalam IRC 80/90-14', 3, 'pcs', 25, 8, 30000, 42000, 'Rak C1'),
('BAN-002', 'Ban Luar Swallow 80/90-14', 3, 'pcs', 12, 5, 120000, 155000, 'Rak C1'),
('BAN-003', 'Velg Racing 14x1.40 CW', 3, 'pcs', 8, 3, 250000, 320000, 'Rak C2'),
('BSI-001', 'Busi NGK CPR8EA-9', 4, 'pcs', 50, 15, 15000, 22000, 'Rak D1'),
('BSI-002', 'Busi Denso U24EPR9', 4, 'pcs', 40, 15, 18000, 25000, 'Rak D1'),
('BSI-003', 'Aki GS Astra 12V 5Ah', 4, 'pcs', 10, 3, 180000, 230000, 'Rak D2'),
('RNT-001', 'Rantai DID 428H-116L', 5, 'set', 15, 5, 85000, 110000, 'Rak E1'),
('RNT-002', 'Gir Depan 14T Honda Beat', 5, 'pcs', 20, 5, 25000, 35000, 'Rak E1'),
('RNT-003', 'Gir Belakang 42T Yamaha Mio', 5, 'pcs', 18, 5, 35000, 48000, 'Rak E2'),
('FLT-001', 'Filter Oli Honda Vario 125', 6, 'pcs', 22, 8, 15000, 22000, 'Rak F1'),
('FLT-002', 'Filter Udara Yamaha NMAX', 6, 'pcs', 15, 5, 25000, 35000, 'Rak F1'),
('FLT-003', 'Saringan Bensin Motor Bebek', 6, 'pcs', 30, 10, 8000, 15000, 'Rak F2'),
('LMP-001', 'Lampu Utama LED H4 12V', 7, 'pcs', 18, 5, 45000, 65000, 'Rak G1'),
('LMP-002', 'Lampu Sein LED Universal', 7, 'set', 12, 5, 35000, 50000, 'Rak G1'),
('LMP-003', 'Lampu Senja T10 LED Putih', 7, 'pcs', 25, 10, 10000, 18000, 'Rak G2'),
('MSN-001', 'Piston Kit Honda Beat 55mm', 8, 'set', 8, 3, 95000, 130000, 'Rak H1'),
('MSN-002', 'Kampas Kopling Yamaha Vixion', 8, 'set', 10, 3, 75000, 105000, 'Rak H1'),
('MSN-003', 'Bearing 6201 2RS', 8, 'pcs', 30, 10, 12000, 18000, 'Rak H2'),
('MSN-004', 'Seal Shock Depan Honda', 8, 'set', 15, 5, 22000, 35000, 'Rak H2');

-- ============================================
-- Data Awal: Barang Masuk
-- ============================================
INSERT INTO barang_masuk (no_transaksi, barang_id, jumlah, harga_satuan, total_harga, tanggal_masuk, supplier, keterangan, user_id) VALUES
('BM-20260520-001', 1, 50, 35000, 1750000, '2026-05-20', 'PT. Oli Nusantara', 'Pembelian rutin bulanan', 1),
('BM-20260521-001', 5, 25, 45000, 1125000, '2026-05-21', 'Distributor Honda', 'Restock kampas rem', 1),
('BM-20260522-001', 11, 60, 15000, 900000, '2026-05-22', 'Grosir NGK', 'Pembelian grosir busi', 2),
('BM-20260523-001', 14, 20, 85000, 1700000, '2026-05-23', 'Supplier DID', 'Order rantai premium', 1),
('BM-20260524-001', 8, 30, 30000, 900000, '2026-05-24', 'PT. IRC Indonesia', 'Restock ban dalam', 2),
('BM-20260525-001', 21, 15, 35000, 525000, '2026-05-25', 'Toko Lampu Jaya', 'Order lampu sein LED', 1);

-- ============================================
-- Data Awal: Barang Keluar
-- ============================================
INSERT INTO barang_keluar (no_transaksi, barang_id, jumlah, harga_satuan, total_harga, tanggal_keluar, tujuan, penerima, keterangan, user_id) VALUES
('BK-20260525-001', 1, 5, 45000, 225000, '2026-05-25', 'Bengkel', 'Teknisi', 'Service rutin motor pelanggan', 2),
('BK-20260525-002', 5, 5, 65000, 325000, '2026-05-25', 'Bengkel', 'Teknisi', 'Ganti kampas rem motor matic', 2),
('BK-20260526-001', 11, 10, 22000, 220000, '2026-05-26', 'Bengkel', 'Teknisi', 'Ganti busi motor bebek', 2),
('BK-20260526-002', 14, 5, 110000, 550000, '2026-05-26', 'Bengkel', 'Teknisi', 'Ganti rantai motor sport', 1),
('BK-20260527-001', 8, 5, 42000, 210000, '2026-05-27', 'Bengkel', 'Teknisi', 'Ganti ban dalam pelanggan', 2);
