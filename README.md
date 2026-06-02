# Inventaris Spare Part Motor - Bengkel Jaya

Sistem inventarisasi barang gudang spare part sepeda motor berbasis web. Dibuat dengan PHP, HTML, CSS, dan MySQL.

## Fitur

- **Dashboard** — Ringkasan stok, transaksi hari ini, stok menipis
- **Data Barang** — CRUD spare part dengan kode, kategori, stok, harga
- **Kategori** — Kelola kategori barang (Oli, Kampas Rem, Ban, dll)
- **Barang Masuk** — Catat penerimaan barang dari supplier
- **Barang Keluar** — Catat pengeluaran barang ke bengkel
- **Laporan** — Statistik per bulan, per kategori, stok menipis
- **Kelola User** — Manajemen akun admin dan operator

## Tech Stack

- PHP 8.x
- MySQL 8.x
- HTML5 + CSS3
- Bootstrap-icons via Font Awesome 6
- Google Fonts (Poppins + Open Sans)

## Cara Install

### 1. Import Database

```bash
mysql -u root -p < database.sql
```

Atau buka phpMyAdmin → Import → pilih `database.sql`

### 2. Konfigurasi Database

Edit `config/database.php`:

```php
$host = 'localhost';
$dbname = 'inventory_sparepart';
$username = 'root';      // sesuaikan
$password = '';           // sesuaikan
```

### 3. Jalankan

```bash
php -S localhost:8000
```

Buka `http://localhost:8000` di browser.

### 4. Login

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | password |
| Operator | operator1 | password |

## Struktur Folder

```
inventory-sparepart/
├── config/
│   └── database.php      # Koneksi DB + helper functions
├── includes/
│   └── sidebar.php        # Navigasi sidebar
├── assets/
│   └── css/
│       └── style.css      # Design system 2026
├── index.php              # Login
├── dashboard.php          # Dashboard utama
├── barang.php             # CRUD barang
├── kategori.php           # CRUD kategori
├── masuk.php              # Barang masuk
├── keluar.php             # Barang keluar
├── laporan.php            # Laporan inventaris
├── users.php              # Kelola user (admin only)
├── logout.php             # Logout
├── database.sql           # Schema + data awal
└── README.md
```

## Rumus Stok

- **Stok Minimum** = batas peringatan stok menipis
- **Nilai Stok** = stok × harga beli
- **Status:** Normal (> minimum), Menipis (≤ minimum), Habis (0)

## License

MIT
