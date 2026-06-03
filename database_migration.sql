-- ============================================
-- Database Migration: New Features
-- Sistem Inventaris Spare Part - Bengkel Jaya
-- Run: mysql -u root inventory_sparepart < database_migration.sql
-- ============================================

USE inventory_sparepart;

-- ============================================
-- Tabel Supplier
-- ============================================
CREATE TABLE IF NOT EXISTS supplier (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_supplier VARCHAR(150) NOT NULL,
    alamat TEXT,
    telepon VARCHAR(30),
    email VARCHAR(100),
    kontak_person VARCHAR(100),
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- Tabel Audit Log
-- ============================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action ENUM('create', 'update', 'delete') NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================
-- Tabel Stok Opname
-- ============================================
CREATE TABLE IF NOT EXISTS stok_opname (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_opname VARCHAR(30) NOT NULL UNIQUE,
    tanggal_opname DATE NOT NULL,
    keterangan TEXT,
    status ENUM('draft', 'divergence', 'resolved') DEFAULT 'draft',
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tanggal (tanggal_opname),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- Tabel Stok Opname Detail
-- ============================================
CREATE TABLE IF NOT EXISTS stok_opname_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stok_opname_id INT NOT NULL,
    barang_id INT NOT NULL,
    stok_sistem INT DEFAULT 0,
    stok_fisik INT DEFAULT NULL,
    selisih INT DEFAULT 0,
    catatan TEXT,
    FOREIGN KEY (stok_opname_id) REFERENCES stok_opname(id) ON DELETE CASCADE,
    FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
    INDEX idx_opname (stok_opname_id),
    INDEX idx_barang (barang_id)
) ENGINE=InnoDB;

-- ============================================
-- Tabel Harga History
-- ============================================
CREATE TABLE IF NOT EXISTS harga_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barang_id INT NOT NULL,
    harga_beli_lama DECIMAL(12,2) DEFAULT 0,
    harga_beli_baru DECIMAL(12,2) DEFAULT 0,
    harga_jual_lama DECIMAL(12,2) DEFAULT 0,
    harga_jual_baru DECIMAL(12,2) DEFAULT 0,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_barang (barang_id)
) ENGINE=InnoDB;

-- ============================================
-- Tambah kolom supplier_id ke barang_masuk (idempotent)
-- ============================================
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'barang_masuk' AND column_name = 'supplier_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE barang_masuk ADD COLUMN supplier_id INT NULL AFTER supplier, ADD CONSTRAINT fk_barang_masuk_supplier FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Data Awal: Supplier (idempotent via INSERT IGNORE with explicit IDs)
-- ============================================
INSERT IGNORE INTO supplier (id, nama_supplier, alamat, telepon, email, kontak_person) VALUES
(1, 'PT. Oli Nusantara', 'Jl. Industri Raya No. 45, Jakarta Barat', '021-5551234', 'sales@olinusantara.co.id', 'Budi Hartono'),
(2, 'Distributor Honda', 'Jl. Raya Bogor Km 30, Depok', '021-7771234', 'order@distributorhonda.com', 'Agus Setiawan'),
(3, 'Grosir NGK', 'Jl. Pergudangan Sentra No. 12, Surabaya', '031-8881234', 'info@grosirngk.co.id', 'Dewi Lestari'),
(4, 'Supplier DID', 'Jl. Otomotif No. 88, Bandung', '022-4441234', 'marketing@supplierdid.com', 'Hendra Kusuma'),
(5, 'PT. IRC Indonesia', 'Jl. Ban Nasional No. 21, Tangerang', '021-6661234', 'order@ircindonesia.co.id', 'Rina Marlina');

-- ============================================
-- Update barang_masuk lama: link ke supplier
-- ============================================
UPDATE barang_masuk SET supplier_id = 1 WHERE supplier = 'PT. Oli Nusantara' AND supplier_id IS NULL;
UPDATE barang_masuk SET supplier_id = 2 WHERE supplier = 'Distributor Honda' AND supplier_id IS NULL;
UPDATE barang_masuk SET supplier_id = 3 WHERE supplier = 'Grosir NGK' AND supplier_id IS NULL;
UPDATE barang_masuk SET supplier_id = 4 WHERE supplier = 'Supplier DID' AND supplier_id IS NULL;
UPDATE barang_masuk SET supplier_id = 5 WHERE supplier = 'PT. IRC Indonesia' AND supplier_id IS NULL;
UPDATE barang_masuk SET supplier_id = NULL WHERE supplier = 'Toko Lampu Jaya';
