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

> Jika sebelumnya sudah punya database `sipam_db` versi lama (tanpa QRIS),
> **hapus dulu database lama** lalu import ulang `sipam_db.sql` yang baru ini,
> karena ada tabel & kolom tambahan untuk fitur QRIS.

### 3. Pastikan folder upload bisa ditulis
Folder berikut harus writable oleh web server (biasanya otomatis di XAMPP/Laragon Windows):
```
assets/qris/
assets/uploads/bukti_transfer/
```

### 4. Sesuaikan koneksi database (jika perlu)
Edit file `config/db.php`:
```php
$host     = 'localhost';
$dbname   = 'sipam_db';
$username = 'root';    // sesuaikan
$password = '';        // sesuaikan
```

### 5. Akses aplikasi
Buka browser → **http://localhost/sipam**

---

## Akun Default

| Role  | Email              | Password  |
|-------|--------------------|-----------|
| Admin | admin@sipam.com    | admin123  |

> **Segera ganti password admin** setelah login pertama melalui menu Profil & Pengaturan.

---

## 🆕 Fitur Pembayaran QRIS

### Alur Admin (sekali setup)
1. Login sebagai admin → menu **Kelola QRIS**
2. Unggah gambar QRIS (JPG/PNG, maks 3MB) — bisa QRIS bank/e-wallet apa pun
3. Gambar otomatis menjadi QRIS aktif yang dilihat semua pelanggan

### Alur Pelanggan
1. Login sebagai pelanggan → tombol **"Bayar via QRIS"** di tagihan aktif (atau menu **Bayar QRIS** di navigasi bawah)
2. Gambar QRIS muncul → pelanggan bisa **scan langsung** atau **unduh gambar** lalu scan dari galeri
3. Setelah transfer, pelanggan **unggah screenshot/foto bukti pembayaran**
4. Status tagihan berubah menjadi **"Menunggu Verifikasi"**

### Alur Verifikasi Admin
1. Admin login → muncul notifikasi **"X bukti pembayaran QRIS menunggu"** di dashboard
2. Buka menu **Verifikasi Pembayaran QRIS**
3. Admin melihat preview bukti transfer, lalu:
   - **Terima** → tagihan otomatis berstatus **Lunas**, kuitansi langsung aktif
   - **Tolak** (dengan catatan alasan) → pelanggan diminta unggah ulang
4. Begitu diterima, pelanggan otomatis bisa lihat **kuitansi digital** di dashboard mereka (`pelanggan/kuitansi.php`) — bisa dicetak/disimpan sebagai PDF dari browser

---

## Fitur Sistem

| Fitur | Deskripsi |
|-------|-----------|
| Login Multi-Role | Admin, Pelanggan, Perangkat Desa |
| Registrasi Pelanggan | Daftarkan warga baru beserta akun login |
| Input Meteran Bulanan | Catat angka meteran & hitung tagihan otomatis |
| Manajemen Tagihan | Pantau status tagihan seluruh pelanggan |
| Catat Pembayaran (Manual) | Admin input pembayaran tunai/transfer & terbitkan kuitansi |
| **Pembayaran QRIS** | Pelanggan bayar mandiri via QRIS + unggah bukti |
| **Verifikasi Pembayaran** | Admin menyetujui/menolak bukti transfer QRIS |
| **Kelola QRIS** | Admin mengunggah & mengatur gambar QRIS aktif |
| Data Tunggakan | Identifikasi & eskalasi tunggakan kritis |
| Kirim Reminder | Catat reminder ke pelanggan belum bayar |
| Eskalasi ke Perangkat Desa | Laporan tunggakan ≥ 3 bulan |
| Laporan Keuangan | Laporan bulanan & tahunan (cetak PDF) |
| Dashboard Pelanggan | Pelanggan lihat tagihan, riwayat, & kuitansi |
| Profil & Pengaturan | Ganti password & update tarif air |

---

## Struktur Folder

```
sipam/
├── admin/
│   ├── dashboard.php
│   ├── pelanggan.php
│   ├── tambah-pelanggan.php
│   ├── proses-tambah.php
│   ├── meteran.php
│   ├── tagihan.php
│   ├── pembayaran.php              ← catat pembayaran manual (tunai/transfer)
│   ├── verifikasi-pembayaran.php   ← 🆕 verifikasi bukti QRIS pelanggan
│   ├── qris.php                    ← 🆕 kelola gambar QRIS
│   ├── kuitansi.php
│   ├── tunggakan.php
│   ├── eskalasi.php
│   ├── kirim-reminder.php
│   ├── notifikasi.php
│   ├── laporan.php
│   └── profil.php
├── pelanggan/
│   ├── dashboard.php
│   ├── bayar.php                   ← 🆕 lihat QRIS, unduh, unggah bukti
│   └── kuitansi.php                ← 🆕 kuitansi digital pelanggan
├── auth/
│   ├── login.php
│   └── logout.php
├── config/
│   └── db.php
├── assets/
│   ├── style.css
│   ├── qris/                       ← 🆕 gambar QRIS tersimpan di sini
│   └── uploads/bukti_transfer/     ← 🆕 bukti transfer pelanggan tersimpan di sini
├── index.php
└── sipam_db.sql
```

---

## Catatan Teknis
- PHP ≥ 7.4 (menggunakan `match`, `fn()` arrow functions, `mime_content_type`)
- MariaDB / MySQL
- Password di-hash menggunakan **bcrypt** (`password_hash` / `password_verify`)
- Upload file divalidasi berdasarkan **ekstensi + MIME type asli** (bukan hanya nama file) untuk keamanan
- Folder upload diberi `.htaccess` agar file di dalamnya tidak bisa dieksekusi sebagai script
- Tidak memerlukan library/framework tambahan
