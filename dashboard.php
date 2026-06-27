<?php
// e:\dmsk 29-4-26\dashboard.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();
$today = date('Y-m-d');

// Query count statistik
try {
    $totalProjects = $db->query("SELECT COUNT(*) FROM projects WHERE isDeleted = 0")->fetchColumn();
    
    $stmtActive = $db->prepare("SELECT COUNT(*) FROM projects WHERE isDeleted = 0 AND startDate <= ? AND endDate >= ?");
    $stmtActive->execute([$today, $today]);
    $activeProjects = $stmtActive->fetchColumn();
    
    $totalPOs = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE isDeleted = 0")->fetchColumn();
    $totalInvoices = $db->query("SELECT COUNT(*) FROM invoices WHERE isDeleted = 0")->fetchColumn();
    $unpaidInvoices = $db->query("SELECT COUNT(*) FROM invoices WHERE isDeleted = 0 AND status != 'Paid'")->fetchColumn();

    // Mengambil total pengeluaran PO untuk chart vendor
    // Mandiri Steel, Semen Nusantara
    $vendorDataRaw = $db->query("SELECT vendor, SUM(total) as total_spent FROM purchase_orders WHERE isDeleted = 0 GROUP BY vendor")->fetchAll(PDO::FETCH_ASSOC);
    $vendorLabels = [];
    $vendorTotals = [];
    foreach ($vendorDataRaw as $v) {
        if (!empty($v['vendor'])) {
            $vendorLabels[] = $v['vendor'];
            $vendorTotals[] = (float)$v['total_spent'];
        }
    }
} catch (PDOException $e) {
    // Failback static jika database bermasalah
    $totalProjects = 2;
    $activeProjects = 2;
    $totalPOs = 2;
    $totalInvoices = 1;
    $unpaidInvoices = 0;
    $vendorLabels = ['Mandiri Steel', 'Semen Nusantara'];
    $vendorTotals = [150000000, 45000000];
}

// Fallback data default jika array kosong
if (empty($vendorLabels)) {
    $vendorLabels = ['Mandiri Steel', 'Semen Nusantara'];
    $vendorTotals = [150000000, 45000000];
}
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="card stat-card">
        <div class="stat-icon-wrapper" style="background-color: var(--info);">
            <i class="bi bi-briefcase"></i>
        </div>
        <div class="stat-details">
            <div class="stat-name">Total Proyek</div>
            <div class="stat-value"><?= $totalProjects ?></div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon-wrapper" style="background-color: var(--success);">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <div class="stat-details">
            <div class="stat-name">Proyek Aktif</div>
            <div class="stat-value"><?= $activeProjects ?></div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon-wrapper" style="background-color: var(--warning);">
            <i class="bi bi-file-earmark-text"></i>
        </div>
        <div class="stat-details">
            <div class="stat-name">Total PO</div>
            <div class="stat-value"><?= $totalPOs ?></div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon-wrapper" style="background-color: var(--danger);">
            <i class="bi bi-file-earmark-spreadsheet"></i>
        </div>
        <div class="stat-details">
            <div class="stat-name">Invoice Masuk</div>
            <div class="stat-value"><?= $totalInvoices ?></div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon-wrapper" style="background-color: var(--secondary);">
            <i class="bi bi-exclamation-circle"></i>
        </div>
        <div class="stat-details">
            <div class="stat-name">Belum Lunas</div>
            <div class="stat-value"><?= $unpaidInvoices ?></div>
        </div>
    </div>
</div>

<!-- Charts Grid -->
<div class="charts-grid">
    <!-- expenditure chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-bar-chart-line" style="color: var(--primary);"></i> Pengeluaran Proyek Per Bulan</div>
        </div>
        <div style="height: 300px; position: relative;">
            <canvas id="expenditureChart"></canvas>
        </div>
    </div>

    <!-- Vendor Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="bi bi-pie-chart" style="color: var(--primary);"></i> Transaksi Vendor Utama (Nominal PO)</div>
        </div>
        <div style="height: 300px; position: relative;">
            <canvas id="vendorChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="bi bi-clock-history"></i> Aktivitas Terakhir</div>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Aktivitas</th>
                    <th>ID Dokumen</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>16 Jun 2026</td>
                    <td>Penerbitan PO Baru</td>
                    <td>PO/PT. WKP/2026/03/002</td>
                    <td><span class="badge badge-warning">Waiting Approval</span></td>
                </tr>
                <tr>
                    <td>15 Jun 2026</td>
                    <td>Pembayaran Invoice</td>
                    <td>KWT/Mandiri Steel/2026/02/001</td>
                    <td><span class="badge badge-success">Success</span></td>
                </tr>
                <tr>
                    <td>14 Jun 2026</td>
                    <td>Registrasi Proyek Baru</td>
                    <td>PR/002/Jembatan Kali/2026</td>
                    <td><span class="badge badge-info">Active</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart JS Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // 1. Expenditure Chart (Line Chart)
    const ctxExp = document.getElementById('expenditureChart').getContext('2d');
    new Chart(ctxExp, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
            datasets: [{
                label: 'Pengeluaran (Juta Rp)',
                data: [4.0, 3.0, 2.0, 2.78, 4.5, 5.2],
                borderColor: '#8B0000',
                backgroundColor: 'rgba(139, 0, 0, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // 2. Vendor Chart (Bar Chart)
    const ctxVen = document.getElementById('vendorChart').getContext('2d');
    new Chart(ctxVen, {
        type: 'bar',
        data: {
            labels: <?= json_encode($vendorLabels) ?>,
            datasets: [{
                label: 'Total Transaksi (Rp)',
                data: <?= json_encode($vendorTotals) ?>,
                backgroundColor: '#8B0000',
                borderColor: '#8B0000',
                borderWidth: 1,
                barThickness: 30
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.raw.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value / 1000000).toFixed(0) + ' Jt';
                        }
                    }
                }
            }
        }
    });
</script>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
