<?php
// php-dmsk/Database.php – Helper untuk SQLite menggunakan PDO
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $pdo = null;

    // Mendapatkan koneksi ke database SQLite
    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $dbFile = SQLITE_DB_PATH;
            $dbDir = dirname($dbFile);
            
            // Buat folder database jika belum ada
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }

            $firstTime = !file_exists($dbFile);

            try {
                self::$pdo = new PDO('sqlite:' . $dbFile);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->exec('PRAGMA foreign_keys = ON;'); // Aktifkan foreign key constraint

                // Inisialisasi skema tabel jika baru dibuat
                if ($firstTime) {
                    self::initializeSchema();
                }
            } catch (PDOException $e) {
                die('Koneksi database SQLite gagal: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    // Inisialisasi skema tabel SQLite & seed data awal
    private static function initializeSchema(): void {
        $pdo = self::$pdo;

        // 1. Projects Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            name TEXT NOT NULL,
            type TEXT,
            location TEXT,
            startDate TEXT,
            endDate TEXT,
            status TEXT,
            details TEXT,
            owner TEXT,
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            accessMode TEXT,
            fileType TEXT,
            fileSize TEXT,
            pic TEXT,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL
        )");

        // 2. Appointments / Serah Terima Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            projectId INTEGER NOT NULL,
            mainCon TEXT,
            subCon TEXT,
            vendor TEXT,
            date TEXT,
            owner TEXT,
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            accessMode TEXT,
            fileType TEXT,
            fileSize TEXT,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL,
            FOREIGN KEY (projectId) REFERENCES projects(id) ON DELETE CASCADE
        )");

        // 3. Purchase Orders Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            projectId INTEGER NOT NULL,
            vendor TEXT,
            date TEXT,
            total REAL,
            status TEXT,
            owner TEXT,
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            accessMode TEXT,
            fileType TEXT,
            fileSize TEXT,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL,
            FOREIGN KEY (projectId) REFERENCES projects(id) ON DELETE CASCADE
        )");

        // 4. Purchase Order Items Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            poId INTEGER NOT NULL,
            name TEXT NOT NULL,
            qty INTEGER NOT NULL,
            price REAL NOT NULL,
            FOREIGN KEY (poId) REFERENCES purchase_orders(id) ON DELETE CASCADE
        )");

        // 5. Terima Barang Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS terima_barang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            poId INTEGER NOT NULL,
            vendor TEXT,
            date TEXT,
            pic TEXT,
            status TEXT,
            fileType TEXT,
            fileSize TEXT,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL,
            FOREIGN KEY (poId) REFERENCES purchase_orders(id) ON DELETE CASCADE
        )");

        // 6. LPJ Reports Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS lpj_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            appointmentId INTEGER NOT NULL,
            projectName TEXT,
            owner TEXT,
            closingReportDate TEXT,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL,
            FOREIGN KEY (appointmentId) REFERENCES appointments(id) ON DELETE CASCADE
        )");

        // 7. Invoices Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            poId INTEGER NOT NULL,
            vendor TEXT,
            date TEXT,
            amount REAL,
            status TEXT,
            owner TEXT,
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            accessMode TEXT,
            fileType TEXT,
            fileSize TEXT,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL,
            FOREIGN KEY (poId) REFERENCES purchase_orders(id) ON DELETE CASCADE
        )");

        // 8. Payments Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            invoiceId INTEGER NOT NULL,
            vendor TEXT,
            date TEXT,
            amount REAL,
            method TEXT,
            status TEXT,
            owner TEXT,
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            accessMode TEXT,
            fileType TEXT,
            fileSize TEXT,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL,
            FOREIGN KEY (invoiceId) REFERENCES invoices(id) ON DELETE CASCADE
        )");

        // 9. Master Users Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS master_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT,
            name TEXT,
            position TEXT,
            company TEXT,
            contact TEXT,
            status TEXT,
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            isDeleted INTEGER DEFAULT 0,
            deletedAt TEXT DEFAULT NULL
        )");

        // --- Seed Data Awal ---
        
        // Projects
        $pdo->exec("INSERT OR IGNORE INTO projects (id, code, name, type, location, startDate, endDate, status, details, owner, createdAt, accessMode, fileType, fileSize, pic) VALUES
        (1, 'PR/001/Gedung Pusat/2026', 'Gedung Pusat', 'Gedung', 'Jakarta', '2026-01-10', '2026-12-31', 'Active', 'Pembangunan gedung 10 lantai', 'System Administrator', '2026-01-10 09:00:00', 'Full Access', 'PDF', '1.2 MB', 'Budi Wirawan (Direktur PT. WKP)'),
        (2, 'PR/002/Jembatan Kali/2026', 'Jembatan Kali', 'Jembatan', 'Semarang', '2026-02-15', '2026-08-15', 'Active', 'Renovasi jembatan utama', 'System Administrator', '2026-02-15 10:30:00', 'Full Access', 'PDF', '850 KB', 'Anto Susilo (Manajer PT. WKP)')");

        // Appointments
        $pdo->exec("INSERT OR IGNORE INTO appointments (id, code, projectId, mainCon, subCon, vendor, date, owner, createdAt, accessMode, fileType, fileSize) VALUES
        (1, 'ST/PT. WKP/2026/01/001', 1, 'PT. Bangun Jaya', 'CV. Mandiri Teknik', 'Mandiri Steel', '2026-01-15', 'System Administrator', '2026-01-15 14:45:00', 'Read / Write', 'DOCX', '450 KB'),
        (2, 'ST/PT. WKP/2026/02/002', 2, 'PT. Infrastruktur Utama', 'CV. Karya Mandiri', 'Semen Nusantara', '2026-02-22', 'System Administrator', '2026-02-22 11:20:00', 'Read / Write', 'DOCX', '420 KB')");

        // Purchase Orders
        $pdo->exec("INSERT OR IGNORE INTO purchase_orders (id, code, projectId, vendor, date, total, status, owner, createdAt, accessMode, fileType, fileSize) VALUES
        (1, 'PO/PT. WKP/2026/02/001', 1, 'Mandiri Steel', '2026-02-01', 150000000.00, 'Approved', 'System Administrator', '2026-02-01 16:15:00', 'Full Access', 'PDF', '2.1 MB'),
        (2, 'PO/PT. WKP/2026/03/002', 2, 'Semen Nusantara', '2026-03-05', 45000000.00, 'Pending', 'System Administrator', '2026-03-05 09:10:00', 'Read Only', 'PDF', '1.8 MB')");

        // Purchase Order Items
        $pdo->exec("INSERT OR IGNORE INTO purchase_order_items (id, poId, name, qty, price) VALUES
        (1, 1, 'Semen', 1000, 60000.00),
        (2, 1, 'Besi Beton', 500, 180000.00),
        (3, 2, 'Pasir', 50, 900000.00)");

        // Invoices
        $pdo->exec("INSERT OR IGNORE INTO invoices (id, code, poId, vendor, date, amount, status, owner, createdAt, accessMode, fileType, fileSize) VALUES
        (1, 'INV/Mandiri Steel/2026/02/101', 1, 'Mandiri Steel', '2026-02-15', 150000000.00, 'Paid', 'System Administrator', '2026-02-15 13:00:00', 'Full Access', 'PDF', '1.5 MB')");

        // Payments
        $pdo->exec("INSERT OR IGNORE INTO payments (id, code, invoiceId, vendor, date, amount, method, status, owner, createdAt, accessMode, fileType, fileSize) VALUES
        (1, 'KWT/Mandiri Steel/2026/02/001', 1, 'Mandiri Steel', '2026-02-20', 150000000.00, 'Transfer', 'Approved', 'System Administrator', '2026-02-20 10:00:00', 'Full Access', 'PDF', '1.1 MB')");

        // Master Users
        $pdo->exec("INSERT OR IGNORE INTO master_users (id, type, name, position, company, contact, status) VALUES
        (1, 'Internal', 'Budi Wirawan', 'Direktur', 'PT. WKP', '-', 'Aktif'),
        (2, 'Internal', 'Anto Suliso', 'Manajer', 'PT. WKP', '-', 'Aktif'),
        (3, 'Internal', 'Dedi Purnomo', 'Konsultan', 'PT. WKP', '-', 'Aktif'),
        (4, 'Internal', 'System Administrator', 'Admin', 'PT. WKP', '-', 'Aktif'),
        (5, 'Main Contractor', '-', '-', 'PT. Bangun Jaya', '-', 'Aktif'),
        (6, 'Main Contractor', '-', '-', 'PT Wijaya Kusuma Perdana', '-', 'Aktif'),
        (7, 'Main Contractor', '-', '-', 'PT. Infrastruktur Utama', '-', 'Aktif'),
        (8, 'Sub Contractor', '-', '-', 'CV. Mandiri Teknik', '-', 'Aktif'),
        (9, 'Sub Contractor', '-', '-', 'CV. Karya Mandiri', '-', 'Aktif'),
        (10, 'Vendor', '-', '-', 'Mandiri Steel', '-', 'Aktif'),
        (11, 'Vendor', '-', '-', 'Semen Nusantara', '-', 'Aktif'),
        (12, 'Vendor', '-', '-', 'CV. Terang Sejahtera', '-', 'Aktif'),
        (13, 'Vendor', '-', '-', 'PT. Cerah Merona', '-', 'Aktif')");
    }
}
?>
