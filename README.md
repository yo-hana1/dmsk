# DMSK – Sistem Manajemen Dokumen & Keuangan Proyek

Sistem Manajemen Dokumen & Keuangan Proyek (DMSK) adalah aplikasi web internal milik **PT. Wijaya Kusuma Perdana (PT. WKP)** yang dirancang untuk mendokumentasikan, melacak, dan mengelola arus keuangan serta administrasi proyek secara terintegrasi. 

Aplikasi ini telah dimigrasi sepenuhnya dari arsitektur React/NodeJS ke **PHP + HTML Murni dengan Database SQLite 3** untuk menjamin instalasi yang mudah dan efisien di shared hosting (seperti RumahWeb) tanpa memerlukan VPS.

---

## 🚀 Fitur Utama

- **Dashboard Keuangan**: Visualisasi statistik proyek aktif, total anggaran PO, total tagihan masuk, kuitansi lunas, dilengkapi grafik pengeluaran bulanan dan transaksi vendor menggunakan *Chart.js*.
- **Data Proyek**: Modul CRUD data proyek pembangunan beserta rincian penanggung jawab (PIC).
- **Serah Terima (ST) & LPJ**: Pengelolaan surat penunjukan sub-kontraktor/vendor, serta form laporan pertanggungjawaban penutupan proyek (LPJ).
- **Purchase Order (PO)**: Pembuatan dokumen PO dengan rincian barang yang dinamis (bisa tambah/hapus baris barang langsung di form) serta kalkulasi otomatis PPN 11%.
- **Invoice & Kuitansi**: Pencatatan tagihan masuk dari vendor dan realisasi kuitansi pembayaran dengan fitur *inline approval* untuk melunasi invoice secara otomatis.
- **Manajer Tempat Sampah (Restore)**: Fitur keamanan data menggunakan *soft-delete* agar data yang tidak sengaja terhapus dapat dipulihkan kembali.
- **Cetak Dokumen Formal**: Semua detail dokumen (proyek, PO, kuitansi, serah terima) dirancang siap cetak (Ctrl+P) dengan kop resmi PT. WKP.

---

## 🛠️ Teknologi yang Digunakan (Tech Stack)

- **Backend / Pemrosesan**: PHP 8.x (menggunakan PDO Object)
- **Database**: SQLite 3 (berkas tunggal `db.sqlite`)
- **Tampilan Antarmuka**: HTML 5, CSS 3 (Kustom Tema Premium Merah Marun), Vanilla JS
- **Visualisasi Ikon**: Bootstrap Icons CDN
- **Grafik Finansial**: Chart.js CDN

---

## 📁 Struktur Direktori Utama

```text
├── assets/                    # Aset statis website
│   ├── style.css              # File CSS utama (desain visual & responsivitas)
│   ├── logowkp.jpg            # Logo PT. WKP
│   ├── icons.svg              # Gambar grafis SVG ikon UI
│   └── favicon.svg            # Ikon untuk tab browser
├── .htaccess                  # Konfigurasi URL bersih (Clean URLs di Apache/cPanel)
├── Database.php               # Helper koneksi & otomatisasi inisialisasi tabel SQLite
├── config.php                 # Pengaturan global path database SQLite
├── index.php                  # Entry point utama (menangani redirect login/dashboard)
├── login.php & logout.php     # Otentikasi sesi keamanan pengguna
├── dashboard.php              # Tampilan beranda & grafik Chart.js
├── projects.php               # Modul CRUD Proyek & Cetak Dokumen Proyek
├── appointments.php           # Modul CRUD Serah Terima (ST)
├── purchase-orders.php        # Modul CRUD PO & formulir barang dinamis
├── invoices.php               # Modul CRUD Tagihan / Invoice Masuk
├── payments.php               # Modul CRUD Pembayaran / Kuitansi Lunas
├── terima-barang.php          # Modul CRUD Tanda Terima Material Gudang
├── lpj-reports.php            # Modul CRUD Laporan LPJ
├── master-users.php           # Modul CRUD Manajemen Data Pengguna
├── restore.php                # Modul Pemulihan Data terhapus (Soft-Delete)
├── technical-doc.php          # Halaman Dokumentasi Teknis Sistem
└── README.md                  # Petunjuk panduan sistem ini
```

---

## 💻 Panduan Instalasi Lokal (Uji Coba PC)

Karena sistem ini menggunakan database SQLite, Anda tidak perlu mengimpor file `.sql` apa pun. Database akan terbuat secara otomatis saat website diakses.

1. Buka folder proyek `E:\dmsk 29-4-26` di terminal (CMD/PowerShell).
2. Jalankan server PHP lokal menggunakan compiler PHP XAMPP Anda:
   ```bash
   C:\xampp\php\php.exe -S localhost:8000
   ```
3. Buka browser dan akses alamat: **`http://localhost:8000`**
4. Masuk dengan akun default berikut:
   - **Email**: `admin@wkp.co.id`
   - **Password**: `admin`

---

## ☁️ Panduan Deployment ke Hosting RumahWeb (cPanel)

1. Blok/pilih seluruh file di folder utama **KECUALI** folder `.git` (jika ada).
2. Kompres semua berkas tersebut menjadi file format `.zip` (misal: `dmsk_website.zip`).
3. Masuk ke **cPanel RumahWeb** Anda.
4. Buka **File Manager**, lalu masuk ke folder **`public_html`** (atau subfolder domain target Anda).
5. Klik **Upload** dan pilih file `dmsk_website.zip`.
6. Klik kanan file `.zip` yang sudah terupload, pilih **Extract**.
7. Buka domain Anda di browser. Halaman web akan langsung aktif dan database SQLite (`db.sqlite`) otomatis terbuat.
