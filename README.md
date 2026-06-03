# Inventaris Spare Part Motor - Bengkel Jaya

Sistem inventarisasi barang gudang spare part sepeda motor berbasis web. Dibuat dengan PHP, HTML, CSS, dan MySQL.

## Preview

![Dashboard](assets/img/dashboard.png)

## Fitur

### Menu Utama
- **Dashboard** — Ringkasan stok, transaksi hari ini, grafik penjualan & distribusi stok (Chart.js), stok menipis
- **Data Barang** — CRUD spare part dengan kode, kategori, stok, harga, riwayat perubahan harga
- **Kategori** — Kelola kategori barang (Oli, Kampas Rem, Ban, dll)
- **Supplier** — Master data supplier (nama, alamat, telepon, email)

### Transaksi
- **Barang Masuk** — Catat penerimaan barang dari supplier (dropdown supplier + custom)
- **Barang Keluar** — Catat pengeluaran barang ke bengkel
- **Stock Opname** — Hitung fisik stok, bandingkan dengan sistem, catat selisih, resolve

### Laporan & Export
- **Laporan** — Statistik per bulan, per kategori, stok menipis, detail masuk/keluar per transaksi
- **Export** — Download Excel/CSV, PDF (server-side generation), dan Print dari setiap halaman

### Pengaturan
- **Kelola User** — Manajemen akun admin dan operator
- **Log Aktivitas** — Audit trail semua aksi CRUD (siapa, kapan, apa yang diubah)

### UI/UX
- **Global Search** — Cari barang dari mana saja (search bar di header)
- **Notifikasi Stok** — Badge merah di sidebar kalau ada barang menipis
- **Validasi Form** — Client-side validation sebelum submit
- **Konfirmasi Hapus** — Modal modern (bukan browser confirm)
- **Loading Spinner** — Prevent double-submit

## Tech Stack

- PHP 8.x
- MySQL 8.x
- HTML5 + CSS3 (Design System 2026)
- Chart.js 4.x (Dashboard charts)
- Font Awesome 6
- Google Fonts (Poppins + Open Sans)
- MiniPDF (custom zero-dependency PDF generator)

## Cara Install

### 1. Import Database

```bash
mysql -u root -p < database.sql
```

Atau buka phpMyAdmin → Import → pilih `database.sql`

### 2. Import Fitur Tambahan

```bash
mysql -u root -p inventory_sparepart < database_migration.sql
```

Ini menambahkan tabel: `supplier`, `audit_log`, `stok_opname`, `stok_opname_detail`, `harga_history`, dan kolom `supplier_id` di `barang_masuk`.

### 3. Konfigurasi Database

Edit `config/database.php`:

```php
$host = 'localhost';
$dbname = 'inventory_sparepart';
$username = 'root';      // sesuaikan
$password = '';           // sesuaikan
```

### 4. Jalankan

```bash
php -S localhost:8000
```

Buka `http://localhost:8000` di browser.

### 5. Login

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | password |
| Operator | operator1 | password |

## Struktur Folder

```
inventory-sparepart/
├── config/
│   └── database.php          # Koneksi DB + helper functions + audit log
├── includes/
│   ├── header.php             # HTML head, Chart.js, JS includes
│   └── sidebar.php            # Navigasi sidebar + notifikasi stok
├── assets/
│   ├── css/
│   │   └── style.css          # Design system 2026
│   ├── js/
│   │   ├── app.js             # Global search, delete modal, loading states
│   │   └── validation.js      # Client-side form validation
│   └── img/
│       └── dashboard.png
├── lib/
│   └── minipdf.php            # PDF generator (zero dependency)
├── index.php                  # Login
├── dashboard.php              # Dashboard + Chart.js
├── barang.php                 # CRUD barang + harga history
├── kategori.php               # CRUD kategori
├── supplier.php               # CRUD supplier
├── masuk.php                  # Barang masuk + supplier dropdown
├── keluar.php                 # Barang keluar
├── opname.php                 # Stock opname
├── laporan.php                # Laporan + detail transaksi + export
├── export.php                 # Export CSV, PDF, Print
├── log.php                    # Audit log viewer (admin)
├── search_api.php             # Global search API (JSON)
├── api_harga_history.php      # Harga history API
├── users.php                  # Kelola user (admin only)
├── logout.php                 # Logout
├── database.sql               # Schema + data awal
├── database_migration.sql     # Tabel tambahan (supplier, audit, opname, harga)
└── README.md
```

## Data Sample

Sudah termasuk data awal:
- 25 jenis spare part (Oli, Kampas Rem, Ban, Busi, Rantai, Filter, Lampu, Komponen Mesin)
- 8 kategori
- 5 supplier
- 6 transaksi masuk
- 5 transaksi keluar

## Rumus Stok

- **Stok Minimum** = batas peringatan stok menipis
- **Nilai Stok** = stok × harga beli
- **Status:** Normal (> minimum), Menipis (≤ minimum), Habis (0)
- **Stock Opname:** Selisih = Stok Fisik − Stok Sistem

## License

MIT
