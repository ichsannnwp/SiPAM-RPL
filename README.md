# Sistem Pembayaran Tagihan Air (Water Billing System)

Proyek ini merupakan tugas besar Rekayasa Perangkat Lunak (RPL) yang bertujuan untuk membangun sistem manajemen dan pembayaran tagihan air secara digital. Sistem ini dirancang untuk memudahkan pelanggan dalam mengecek dan membayar tagihan, serta membantu admin/petugas dalam mengelola data penggunaan air pelanggan.

## 🚀 Teknologi yang Digunakan

Proyek ini dibangun menggunakan arsitektur *Decoupled/Clean Architecture* dengan teknologi berikut:

- **Frontend:** [React.js](https://react.dev/) (JavaScript/TypeScript) & Tailwind CSS untuk antarmuka yang responsif.
- **Backend:** [Go (Golang)](https://go.dev/) menggunakan framework Echo / Gin untuk performa API yang cepat dan efisien.
- **Database:** PostgreSQL / MySQL (Sesuaikan dengan yang kamu pakai).
- **Tools Tambahan:** Docker (opsional untuk *environment setup*), Git & GitHub untuk *version control*.

## ✨ Fitur Utama Sistem

### 👥 Sisi Pelanggan (User)
- **Autentikasi:** Registrasi, Login, dan Manajemen Profil.
- **Dashboard Pelanggan:** Informasi ringkas mengenai status tagihan bulan ini.
- **Riwayat Tagihan:** Melihat riwayat penggunaan air dan status pembayaran bulan-bulan sebelumnya.
- **Pembayaran Digital:** Integrasi simulasi *payment gateway* / unggah bukti transfer.
- **Cetak Invoice:** Mengunduh bukti pembayaran dalam format PDF.

### 👨‍💼 Sisi Admin / Petugas
- **Manajemen Pelanggan:** Menambah, mengubah, dan menghapus data pelanggan.
- **Pencatatan Meteran Air:** Petugas dapat menginput angka meteran air pelanggan setiap bulan.
- **Generasi Tagihan:** Sistem otomatis menghitung total biaya berdasarkan tarif per meter kubik.
- **Laporan Keuangan:** Grafik dan laporan pemasukan bulanan/tahunan.

## 🛠️ Langkah Instalasi & Menjalankan Proyek

### Prasyarat (Prerequisites)
Pastikan kamu sudah menginstal:
- Node.js (v18 atau terbaru)
- Go (v1.20 atau terbaru)
- Database Engine (PostgreSQL/MySQL)
