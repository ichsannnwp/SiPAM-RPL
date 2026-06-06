
# SiPAM — Sistem PAM Swadaya Masyarakat
## Desa Ngasem, Kecamatan Masaran, Kabupaten Sragen

---

## Cara Deploy (XAMPP / Laragon)

### 1. Letakkan folder ke htdocs
Salin folder `sipam` ke dalam `C:\xampp\htdocs\` (XAMPP) atau folder web server Anda.

### 2. Import database
- Buka **phpMyAdmin** → http://localhost/phpmyadmin
- Buat database baru bernama `sipam_db`
- Klik tab **Import**, pilih file `sipam_db.sql`, klik **Go**

### 3. Sesuaikan koneksi database (jika perlu)
Edit file `config/db.php`:
```php
$host     = 'localhost';
$dbname   = 'sipam_db';
$username = 'root';    // sesuaikan
$password = '';        // sesuaikan
```

### 4. Akses aplikasi
Buka browser → **http://localhost/sipam**

---

## Akun Default

| Role  | Email              | Password  |
|-------|--------------------|-----------|
| Admin | admin@sipam.com    | admin123  |

> **Segera ganti password admin** setelah login pertama melalui menu Profil & Pengaturan.

---

## Fitur Sistem

| Fitur | Deskripsi |
|-------|-----------|
| Login Multi-Role | Admin, Pelanggan, Perangkat Desa |
| Registrasi Pelanggan | Daftarkan warga baru beserta akun login |
| Input Meteran Bulanan | Catat angka meteran & hitung tagihan otomatis |
| Manajemen Tagihan | Pantau status tagihan seluruh pelanggan |
| Catat Pembayaran | Input pembayaran & terbitkan kuitansi digital |
| Data Tunggakan | Identifikasi & eskalasi tunggakan kritis |
| Kirim Reminder | Catat reminder ke pelanggan belum bayar |
| Eskalasi ke Perangkat Desa | Laporan tunggakan ≥ 3 bulan |
| Laporan Keuangan | Laporan bulanan & tahunan (cetak PDF) |
| Dashboard Pelanggan | Pelanggan lihat tagihan & riwayat pembayaran |
| Profil & Pengaturan | Ganti password & update tarif air |

---

## Struktur Folder

```
sipam/
├── admin/              ← Halaman admin & bendahara
│   ├── dashboard.php
│   ├── pelanggan.php
│   ├── tambah-pelanggan.php
│   ├── proses-tambah.php
│   ├── meteran.php
│   ├── tagihan.php
│   ├── pembayaran.php
│   ├── kuitansi.php
│   ├── tunggakan.php
│   ├── eskalasi.php
│   ├── kirim-reminder.php
│   ├── notifikasi.php
│   ├── laporan.php
│   └── profil.php
├── pelanggan/          ← Dashboard pelanggan
│   └── dashboard.php
├── auth/               ← Autentikasi
│   ├── login.php
│   └── logout.php
├── config/
│   └── db.php          ← Konfigurasi database
├── assets/
│   └── style.css
├── index.php           ← Redirect ke login
└── sipam_db.sql        ← File database
```

---

## Catatan Teknis
- PHP ≥ 7.4 (menggunakan `match`, `fn()` arrow functions)
- MariaDB / MySQL
- Password di-hash menggunakan **bcrypt** (`password_hash` / `password_verify`)
- Tidak memerlukan library/framework tambahan
=======
#SiPAM
>>>>>>> b0398cc3c4d30168098242d86e46c42a86798f40
