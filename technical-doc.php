<?php
// e:\dmsk 29-4-26\technical-doc.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();
?>

<!-- Scope Style for Technical Document View and Printing -->
<style>
  .technical-doc-container {
    display: flex;
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    overflow: hidden;
    min-height: 70vh;
  }
  .doc-sidebar {
    width: 250px;
    background: white;
    border-right: 1px solid var(--border-color);
    padding: 20px;
    position: sticky;
    top: 60px;
    height: calc(100vh - 120px);
    overflow-y: auto;
  }
  .doc-content {
    flex: 1;
    padding: 40px;
    background: white;
    font-family: 'Georgia', serif;
    line-height: 1.8;
    color: #1e293b;
  }
  .doc-section {
    margin-bottom: 45px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 30px;
  }
  .doc-section h2 {
    font-family: sans-serif;
    color: var(--primary);
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 15px;
  }
  .doc-section h3 {
    font-family: sans-serif;
    font-size: 1.05rem;
    font-weight: 600;
    margin-top: 20px;
    margin-bottom: 8px;
    color: var(--secondary);
  }
  .doc-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    font-family: sans-serif;
    font-size: 0.8rem;
  }
  .doc-table th, .doc-table td {
    border: 1px solid #cbd5e1;
    padding: 8px 12px;
    text-align: left;
  }
  .doc-table th {
    background-color: #f8fafc;
  }
  
  .tree-node {
    font-family: monospace;
    font-size: 0.85rem;
    margin-left: 20px;
    color: #0f172a;
    line-height: 1.5;
  }

  @media print {
    body * {
      visibility: hidden !important;
    }
    .technical-doc-container, .technical-doc-container * {
      visibility: visible !important;
    }
    .technical-doc-container {
      position: absolute !important;
      left: 0 !important;
      top: 0 !important;
      width: 100% !important;
      border: none !important;
      display: block !important;
    }
    .doc-sidebar, .no-print {
      display: none !important;
    }
    .doc-content {
      padding: 0 !important;
    }
    .doc-section {
      page-break-after: always !important;
    }
  }
</style>

<!-- Floating Print Button (no-print) -->
<div class="no-print" style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
    <button class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Ekspor Dokumentasi ke PDF
    </button>
</div>

<div class="technical-doc-container">
    <!-- TOC Sidebar -->
    <div class="doc-sidebar no-print">
        <h4 style="font-size: 0.9rem; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; color: var(--secondary);">Daftar Isi</h4>
        <ul style="display: flex; flex-direction: column; gap: 8px; font-size: 0.85rem;">
            <li><a href="#tech-stack" style="color: var(--primary); font-weight: 500;"><i class="bi bi-code-slash"></i> 1. Teknologi Sistem</a></li>
            <li><a href="#architecture" style="color: var(--primary); font-weight: 500;"><i class="bi bi-diagram-3"></i> 2. Arsitektur Relasi</a></li>
            <li><a href="#database" style="color: var(--primary); font-weight: 500;"><i class="bi bi-database"></i> 3. Struktur Database</a></li>
            <li><a href="#folders" style="color: var(--primary); font-weight: 500;"><i class="bi bi-folder2-open"></i> 4. Struktur Folder</a></li>
            <li><a href="#features" style="color: var(--primary); font-weight: 500;"><i class="bi bi-star"></i> 5. Fitur Unggulan</a></li>
            <li><a href="#setup" style="color: var(--primary); font-weight: 500;"><i class="bi bi-gear"></i> 6. Panduan Instalasi</a></li>
        </ul>
    </div>

    <!-- Doc Content Pane -->
    <div class="doc-content">
        <!-- 1. TECH STACK -->
        <div class="doc-section" id="tech-stack">
            <h2>1. Teknologi yang Digunakan (Tech Stack)</h2>
            <p>Sistem DMSK menggunakan arsitektur modular terpusat berbasis PHP dan database relasional ringan SQLite untuk kemudahan instalasi di hosting cPanel RumahWeb tanpa memerlukan lisensi VPS/NodeJS tambahan.</p>
            
            <h3>Teknologi Utama:</h3>
            <ul>
                <li><strong>PHP 8.x</strong>: Bahasa pemrosesan server-side utama yang menangani data templating, autentikasi sesi, dan eksekusi query relasional.</li>
                <li><strong>SQLite 3</strong>: Database file-based yang sangat efisien, cepat, dan tidak memerlukan server database terpisah. Database disimpan dalam satu berkas berkstensi <code>.sqlite</code>.</li>
                <li><strong>Bootstrap Icons</strong>: Library ikon berbasis font web untuk representasi visual menu dan aksi tombol.</li>
                <li><strong>Chart.js (CDN)</strong>: Library visualisasi grafik batang dan garis untuk analisis pengeluaran proyek serta data transaksi vendor utama di halaman dashboard.</li>
            </ul>
        </div>

        <!-- 2. ARCHITECTURE -->
        <div class="doc-section" id="architecture">
            <h2>2. Arsitektur Hubungan Komunikasi</h2>
            <p>Sistem ini beroperasi dengan model server-rendered klasik di mana setiap permintaan halaman diproses di server PHP sebelum dikirimkan ke browser pengguna sebagai HTML biasa. Koneksi ke SQLite ditangani menggunakan PHP Data Objects (PDO) untuk perlindungan maksimal terhadap serangan SQL Injection menggunakan prepared statements.</p>
            <div style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 0.8rem; line-height: 1.5;">
                [Browser Pengguna] ─── (HTTP Request) ───> [WebServer Apache (cPanel)] ───> [PHP Engine] ───> [PDO SQLite Driver] ───> [db.sqlite File]
            </div>
        </div>

        <!-- 3. DATABASE SCHEMA -->
        <div class="doc-section" id="database">
            <h2>3. Struktur Database (Skema Relasional)</h2>
            <p>Database SQLite diinisialisasi secara otomatis oleh kelas pembantu <code>Database.php</code> jika berkas database belum tersedia. Berikut adalah tabel utama sistem:</p>
            
            <h3>Tabel: <code>projects</code> (Data Proyek)</h3>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Kolom</th>
                        <th>Tipe</th>
                        <th>Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>id</strong> (PK)</td><td>INTEGER</td><td>ID Auto Increment utama.</td></tr>
                    <tr><td><strong>code</strong></td><td>TEXT</td><td>Nomor kode proyek unik.</td></tr>
                    <tr><td><strong>name</strong></td><td>TEXT</td><td>Nama proyek bangunan.</td></tr>
                    <tr><td><strong>type</strong></td><td>TEXT</td><td>Kategori proyek (Gedung/Rumah/Jembatan/Renovasi).</td></tr>
                    <tr><td><strong>pic</strong></td><td>TEXT</td><td>Nama PIC penanggung jawab proyek.</td></tr>
                    <tr><td><strong>isDeleted</strong></td><td>INTEGER</td><td>Flag hapus sementara (0 = Aktif, 1 = Dihapus).</td></tr>
                </tbody>
            </table>

            <h3>Tabel: <code>purchase_orders</code> (Purchase Order)</h3>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Kolom</th>
                        <th>Tipe</th>
                        <th>Deskripsi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>id</strong> (PK)</td><td>INTEGER</td><td>ID PO Auto Increment.</td></tr>
                    <tr><td><strong>code</strong></td><td>TEXT</td><td>Nomor dokumen PO.</td></tr>
                    <tr><td><strong>projectId</strong> (FK)</td><td>INTEGER</td><td>Terhubung ke projects.id.</td></tr>
                    <tr><td><strong>vendor</strong></td><td>TEXT</td><td>Nama vendor penerima PO.</td></tr>
                    <tr><td><strong>total</strong></td><td>REAL</td><td>Total nominal belanja PO.</td></tr>
                    <tr><td><strong>status</strong></td><td>TEXT</td><td>Status persetujuan (Pending/Approved/Rejected).</td></tr>
                </tbody>
            </table>
        </div>

        <!-- 4. FOLDER STRUCTURE -->
        <div class="doc-section" id="folders">
            <h2>4. Struktur Direktori Proyek</h2>
            <p>Berikut adalah struktur tata letak file proyek DMSK setelah migrasi bersih:</p>
            <div class="tree-node">
                e:\dmsk 29-4-26\<br>
                ├── assets/<br>
                │   ├── style.css (Custom theme stylesheet)<br>
                │   ├── favicon.svg (Browser tab icon)<br>
                │   ├── icons.svg (UI graphics & vector icons)<br>
                │   └── logowkp.jpg (PT. WKP Logo)<br>
                ├── backup_node/ (Folder arsip/backup kode React & NodeJS lama)<br>
                ├── .htaccess (Apache rewriting rules)<br>
                ├── Database.php (SQLite Connection & Initializer Helper)<br>
                ├── config.php (SQLite path config)<br>
                ├── dashboard.php (Dashboard & charts)<br>
                ├── projects.php (Proyek CRUD & Document Previews)<br>
                ├── appointments.php (Serah Terima CRUD)<br>
                ├── purchase-orders.php (PO CRUD & Dynamic row entries)<br>
                ├── invoices.php (Invoice tagihan & Payment trigger)<br>
                ├── payments.php (Kuitansi Pembayaran & Status approver)<br>
                ├── terima-barang.php (Warehouse Terima Barang CRUD)<br>
                ├── lpj-reports.php (LPJ penutupan proyek CRUD)<br>
                ├── master-users.php (User management)<br>
                ├── restore.php (Soft-delete trash manager)<br>
                ├── router.php (Local development CLI server router)<br>
                ├── login.php (Autentikasi sesi)<br>
                └── logout.php (Penghancur sesi)<br>
            </div>
        </div>

        <!-- 5. FEATURES -->
        <div class="doc-section" id="features">
            <h2>5. Fitur Unggulan Sistem</h2>
            <ul>
                <li><strong>Auto-Inisialisasi Database</strong>: Sistem secara otomatis membuat file <code>db.sqlite</code>, membuat seluruh struktur tabel, dan menyuntikkan data seed bawaan saat pertama kali halaman web diakses.</li>
                <li><strong>Soft Delete & Restore Manager</strong>: Data yang dihapus tidak langsung hilang dari penyimpanan fisik, melainkan masuk ke Tempat Sampah (Restore) agar dapat dipulihkan kapan saja oleh pengguna.</li>
                <li><strong>Cetak Dokumen Terformat (PDF)</strong>: Dilengkapi dengan modal pratinjau dokumen formal (PO, Invoice, Kuitansi, Serah Terima) lengkap dengan tanda tangan, kop surat resmi, dan filter sembunyikan antarmuka saat dicetak (Ctrl+P).</li>
            </ul>
        </div>

        <!-- 6. SETUP GUIDE -->
        <div class="doc-section" id="setup">
            <h2>6. Panduan Pemasangan (Setup Guide)</h2>
            <h3>Uji Coba Lokal:</h3>
            <ol>
                <li>Buka folder proyek di terminal Windows (PowerShell/CMD).</li>
                <li>Jalankan server PHP bawaan: <code>php -S localhost:8000 router.php</code></li>
                <li>Akses alamat <code>http://localhost:8000</code> di browser Anda.</li>
            </ol>
            
            <h3>Pemasangan di Hosting RumahWeb:</h3>
            <ol>
                <li>Kecualikan folder <code>backup_node/</code>, lalu kompres seluruh file lainnya ke format <code>.zip</code>.</li>
                <li>Upload file zip tersebut lewat **File Manager cPanel** ke folder target (misalnya <code>public_html</code>).</li>
                <li>Ekstrak berkas zip.</li>
                <li>Buka domain Anda di browser. Database akan langsung aktif secara otomatis!</li>
            </ol>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
