<?php
// e:\dmsk 29-4-26\restore.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Pulihkan Data (Restore) ---
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['type']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['type'];
    
    // Pemetaan tipe ke tabel database
    $tableMap = [
        'Project' => 'projects',
        'SerahTerima' => 'appointments',
        'PO' => 'purchase_orders',
        'Invoice' => 'invoices',
        'Pembayaran' => 'payments',
        'MasterUser' => 'master_users',
        'TerimaBarang' => 'terima_barang',
        'LPJ' => 'lpj_reports'
    ];

    $tableName = $tableMap[$type] ?? '';

    if (!empty($tableName)) {
        try {
            $stmt = $db->prepare("UPDATE `$tableName` SET isDeleted = 0, deletedAt = NULL WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: restore.php");
            exit;
        } catch (PDOException $e) {
            $errorMsg = "Gagal memulihkan data: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Tipe dokumen tidak valid.";
    }
}

// --- 2. Load Semua Data Terhapus ---
try {
    $delProjects = $db->query("SELECT * FROM projects WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $delST = $db->query("SELECT * FROM appointments WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $delPOs = $db->query("SELECT * FROM purchase_orders WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $delInvoices = $db->query("SELECT * FROM invoices WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $delPayments = $db->query("SELECT * FROM payments WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $delUsers = $db->query("SELECT * FROM master_users WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $delTB = $db->query("SELECT * FROM terima_barang WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
    $delLPJ = $db->query("SELECT * FROM lpj_reports WHERE isDeleted = 1 ORDER BY deletedAt DESC")->fetchAll(PDO::FETCH_ASSOC);

    $totalDeleted = count($delProjects) + count($delST) + count($delPOs) + 
                    count($delInvoices) + count($delPayments) + count($delUsers) + 
                    count($delTB) + count($delLPJ);
} catch (PDOException $e) {
    $totalDeleted = 0;
    $delProjects = $delST = $delPOs = $delInvoices = $delPayments = $delUsers = $delTB = $delLPJ = [];
    $errorMsg = "Gagal memuat tempat sampah: " . $e->getMessage();
}

// Fungsi pembantu render tabel terhapus
function renderDeletedTable($items, $type, $title) {
    if (count($items) === 0) return;
    ?>
    <div class="card" style="margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
            <h3 style="font-size: 1.05rem; font-weight: 600; color: var(--primary);"><i class="bi bi-trash"></i> Dokumen <?= htmlspecialchars($title) ?> Terhapus</h3>
            <span class="badge badge-danger"><?= count($items) ?> Berkas</span>
        </div>
        <div class="table-container">
            <table class="table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Nomor Dokumen / Nama</th>
                        <th>Waktu Dibuat</th>
                        <th>Waktu Dihapus</th>
                        <th style="text-align: center; width: 100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): 
                        // Tentukan label deskripsi/kode unik
                        $displayCode = $item['code'] ?? '';
                        if ($type === 'MasterUser') {
                            $displayCode = "[" . $item['type'] . "] " . $item['name'];
                        }
                    ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($displayCode) ?></td>
                            <td><?= htmlspecialchars($item['createdAt'] ?? '-') ?></td>
                            <td>
                                <span style="color: var(--danger); font-size: 0.8rem; font-weight: 500;">
                                    <i class="bi bi-clock-history"></i> <?= htmlspecialchars($item['deletedAt'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; justify-content: center;">
                                    <a href="restore.php?action=restore&type=<?= $type ?>&id=<?= $item['id'] ?>" class="btn btn-success btn-sm" title="Restore Data" onclick="return confirm('Pulihkan dokumen ini?')">
                                        <i class="bi bi-arrow-counterclockwise"></i> Pulihkan
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Informasi Halaman -->
<div class="card" style="padding: 15px 20px; margin-bottom: 20px;">
    <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Kelola dan pulihkan dokumen atau pengguna yang telah dihapus sementara (soft-delete).</p>
</div>

<?php if ($totalDeleted === 0): ?>
    <!-- Tampilan Tempat Sampah Kosong -->
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <div style="margin-bottom: 15px; color: #ced4da;">
            <i class="bi bi-trash3" style="font-size: 4rem;"></i>
        </div>
        <h3 style="font-size: 1.15rem; color: #495057; margin-bottom: 5px;">Tempat Sampah Kosong</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem;">Tidak ada data atau dokumen yang sedang dihapus sementara.</p>
    </div>
<?php else: ?>
    <!-- Render Tabel Berdasarkan Jenis Dokumen -->
    <?php renderDeletedTable($delProjects, 'Project', 'Data Proyek'); ?>
    <?php renderDeletedTable($delST, 'SerahTerima', 'Serah Terima (ST)'); ?>
    <?php renderDeletedTable($delPOs, 'PO', 'Purchase Order (PO)'); ?>
    <?php renderDeletedTable($delInvoices, 'Invoice', 'Invoice Vendor'); ?>
    <?php renderDeletedTable($delPayments, 'Pembayaran', 'Kuitansi Pembayaran'); ?>
    <?php renderDeletedTable($delTB, 'TerimaBarang', 'Terima Barang'); ?>
    <?php renderDeletedTable($delLPJ, 'LPJ', 'Laporan Penutupan (LPJ)'); ?>
    <?php renderDeletedTable($delUsers, 'MasterUser', 'Master User'); ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
