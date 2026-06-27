<?php
// e:\dmsk 29-4-26\index.php – Entry Point Utama Aplikasi DMSK
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

// Inisialisasi koneksi database SQLite (sekaligus membuat skema tabel dan seed data awal jika kosong)
try {
    $db = Database::getConnection();
} catch (Exception $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

session_start();

// Jika pengguna sudah login, arahkan ke dashboard.php
// Jika belum, arahkan ke login.php
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
?>
