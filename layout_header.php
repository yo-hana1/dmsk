<?php
// e:\dmsk 29-4-26\layout_header.php
session_start();

// Cek apakah user sudah login, jika belum arahkan ke login.php
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$currentUser = $_SESSION['user'];
$currentScript = basename($_SERVER['SCRIPT_NAME']);

// Fungsi pembantu untuk menandai menu aktif di sidebar
function isMenuActive($pageName, $currentScript) {
    return ($pageName === $currentScript) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMsk - PT. Wijaya Kusuma Perdana</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="assets/logowkp.jpg" alt="Logo WKP" class="sidebar-logo">
                <div style="display: flex; flex-direction: column; line-height: 1.2;">
                    <span class="sidebar-brand-name">PT. WKP</span>
                    <span class="sidebar-brand-sub">Wijaya Kusuma Perdana</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="sidebar-menu-item">
                        <a href="dashboard.php" class="sidebar-link <?= isMenuActive('dashboard.php', $currentScript) ?>">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="master-users.php" class="sidebar-link <?= isMenuActive('master-users.php', $currentScript) ?>">
                            <i class="bi bi-people"></i>
                            <span>Master User</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="projects.php" class="sidebar-link <?= isMenuActive('projects.php', $currentScript) ?>">
                            <i class="bi bi-briefcase"></i>
                            <span>Data Proyek</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="appointments.php" class="sidebar-link <?= isMenuActive('appointments.php', $currentScript) ?>">
                            <i class="bi bi-handshake"></i>
                            <span>Serah Terima</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="purchase-orders.php" class="sidebar-link <?= isMenuActive('purchase-orders.php', $currentScript) ?>">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Purchase Order</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="invoices.php" class="sidebar-link <?= isMenuActive('invoices.php', $currentScript) ?>">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            <span>Invoices</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="payments.php" class="sidebar-link <?= isMenuActive('payments.php', $currentScript) ?>">
                            <i class="bi bi-credit-card"></i>
                            <span>Kuitansi / Payments</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="terima-barang.php" class="sidebar-link <?= isMenuActive('terima-barang.php', $currentScript) ?>">
                            <i class="bi bi-inbox"></i>
                            <span>Terima Barang</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="lpj-reports.php" class="sidebar-link <?= isMenuActive('lpj-reports.php', $currentScript) ?>">
                            <i class="bi bi-pie-chart"></i>
                            <span>LPJ Reports</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="restore.php" class="sidebar-link <?= isMenuActive('restore.php', $currentScript) ?>">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Restore Deleted</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="technical-doc.php" class="sidebar-link <?= isMenuActive('technical-doc.php', $currentScript) ?>">
                            <i class="bi bi-book"></i>
                            <span>Technical Doc</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Sidebar overlay for mobile mobile navigation toggle -->
        <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleMobileSidebar()"></div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Top Navbar -->
            <header class="top-navbar no-print">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <button class="nav-toggle-btn" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <!-- Form pencarian global yang mengirim parameter search ke script aktif -->
                    <form method="GET" action="" class="search-container">
                        <i class="bi bi-search"></i>
                        <input type="text" name="search" class="search-input" placeholder="Pencarian data..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <?php if (isset($_GET['search']) && $_GET['search'] !== ''): ?>
                            <a href="<?= $currentScript ?>" style="margin-left: 8px; font-size: 0.8rem; color: var(--primary);">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="nav-right-items">
                    <div class="notification-badge">
                        <i class="bi bi-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user-profile">
                        <div class="user-info">
                            <div class="user-name"><?= htmlspecialchars($currentUser['name'] ?? 'Admin User') ?></div>
                            <div class="user-role"><?= htmlspecialchars($currentUser['role'] ?? 'Administrator') ?></div>
                        </div>
                        <div class="avatar">
                            <i class="bi bi-person"></i>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Body Wrapper -->
            <main class="content-body">
                <!-- Breadcrumb -->
                <div class="breadcrumb-container no-print">
                    <h1 class="page-title">
                        <?php
                        $pageTitles = [
                            'dashboard.php' => 'Dashboard',
                            'master-users.php' => 'Master User',
                            'projects.php' => 'Data Proyek',
                            'appointments.php' => 'Serah Terima',
                            'purchase-orders.php' => 'Purchase Order',
                            'invoices.php' => 'Invoices',
                            'payments.php' => 'Kuitansi / Payments',
                            'terima-barang.php' => 'Terima Barang',
                            'lpj-reports.php' => 'LPJ Reports',
                            'restore.php' => 'Restore Deleted Data',
                            'technical-doc.php' => 'Dokumentasi Teknis'
                        ];
                        echo $pageTitles[$currentScript] ?? 'Aplikasi';
                        ?>
                    </h1>
                    <div class="breadcrumb">
                        Home / <span class="active-item"><?= $pageTitles[$currentScript] ?? 'Aplikasi' ?></span>
                    </div>
                </div>
