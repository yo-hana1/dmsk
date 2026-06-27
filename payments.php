<?php
// e:\dmsk 29-4-26\payments.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Persetujuan Pembayaran ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    try {
        if ($action === 'approve') {
            $db->beginTransaction();
            
            // Ambil invoiceId
            $stmtP = $db->prepare("SELECT invoiceId FROM payments WHERE id = ?");
            $stmtP->execute([$id]);
            $pay = $stmtP->fetch(PDO::FETCH_ASSOC);

            // Update status kuitansi pembayaran menjadi Approved
            $db->prepare("UPDATE payments SET status = 'Approved' WHERE id = ?")->execute([$id]);

            // Update status invoice menjadi Paid
            if ($pay && !empty($pay['invoiceId'])) {
                $db->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ?")->execute([$pay['invoiceId']]);
            }

            $db->commit();
        } 
        elseif ($action === 'reject') {
            $db->prepare("UPDATE payments SET status = 'Rejected' WHERE id = ?")->execute([$id]);
        } 
        elseif ($action === 'delete') {
            $db->prepare("UPDATE payments SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        }

        header("Location: payments.php");
        exit;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $errorMsg = "Gagal memperbarui status kuitansi: " . $e->getMessage();
    }
}

// --- 2. Load Data & Filter Pencarian ---
$search = $_GET['search'] ?? '';
$sql = "SELECT p.*, inv.code as invoiceCode FROM payments p LEFT JOIN invoices inv ON p.invoiceId = inv.id WHERE p.isDeleted = 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.code LIKE ? OR p.vendor LIKE ? OR inv.code LIKE ?)";
    $searchWildcard = "%$search%";
    $params = [$searchWildcard, $searchWildcard, $searchWildcard];
}
$sql .= " ORDER BY p.id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $paymentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $paymentsList = [];
    $errorMsg = "Gagal mengambil data kuitansi: " . $e->getMessage();
}
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Header Menu -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; margin-bottom: 20px;">
    <h2 class="card-title" style="margin:0;"><i class="bi bi-credit-card" style="color: var(--primary);"></i> Daftar Kuitansi Pembayaran</h2>
</div>

<!-- Table Data Pembayaran -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Kuitansi</th>
                    <th>No. Ref Invoice</th>
                    <th>Vendor</th>
                    <th>Nominal</th>
                    <th>Metode</th>
                    <th>Tgl Bayar</th>
                    <th>Status</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($paymentsList) > 0): ?>
                    <?php foreach ($paymentsList as $p): ?>
                        <tr>
                            <td style="font-weight: 600; color: #4a5568;"><?= htmlspecialchars($p['code']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($p['invoiceCode'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['vendor']) ?></td>
                            <td>Rp <?= number_format($p['amount'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($p['method']) ?></td>
                            <td><?= htmlspecialchars($p['date']) ?></td>
                            <td>
                                <?php
                                $statusClass = 'badge-secondary';
                                if ($p['status'] === 'Approved') $statusClass = 'badge-success';
                                if ($p['status'] === 'Pending') $statusClass = 'badge-warning';
                                if ($p['status'] === 'Rejected') $statusClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($p['status'] ?: 'Approved') ?></span>
                            </td>
                            <td class="no-print">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <a href="payments.php?preview_id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Preview Kuitansi">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <?php if ($p['status'] === 'Pending'): ?>
                                        <a href="payments.php?action=approve&id=<?= $p['id'] ?>" class="btn btn-success btn-sm" title="Setujui Pembayaran" onclick="return confirm('Approve kuitansi pembayaran ini?')">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </a>
                                        <a href="payments.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" title="Tolak Pembayaran" onclick="return confirm('Tolak kuitansi pembayaran ini?')">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </a>
                                    <?php endif; ?>
                                    <a href="payments.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen pembayaran ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">Tidak ada kuitansi pembayaran yang ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL PREVIEW DETAIL DOKUMEN KUITANSI -->
<?php
if (isset($_GET['preview_id'])):
    $previewId = (int)$_GET['preview_id'];
    $stmtP = $db->prepare("SELECT p.*, inv.code as invoiceCode, inv.date as invoiceDate FROM payments p LEFT JOIN invoices inv ON p.invoiceId = inv.id WHERE p.id = ?");
    $stmtP->execute([$previewId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
?>
    <div class="modal show" id="previewPaymentModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail Kuitansi</h2>
                <a href="payments.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="kwitansi-document" style="font-family: serif; position: relative;">
                <div style="display: flex; align-items: center; border-bottom: 4px double #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <img src="assets/logowkp.jpg" alt="Logo WKP" style="height: 60px; margin-right: 20px; border-radius: 4px;">
                    <div style="flex: 1;">
                        <h3 style="margin: 0; font-size: 1.3rem; font-weight: bold; letter-spacing: 0.5px;">PT. WIJAYA KUSUMA PERDANA</h3>
                        <p style="margin: 5px 0 0 0; font-size: 0.75rem; color: #555; font-family: sans-serif; line-height: 1.3;">
                            General Contractor & Construction Management<br />
                            Jl. Jendral Sudirman No. 123, Jakarta, Indonesia
                        </p>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h4 style="margin: 0; font-size: 1.35rem; font-weight: bold; text-decoration: underline; letter-spacing: 1px;">KUITANSI REALISASI PEMBAYARAN</h4>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555; font-family: sans-serif;">No. Bukti: <?= htmlspecialchars($item['code']) ?></p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 0.9rem;">
                    <tr>
                        <td style="width: 30%; font-weight: bold; padding: 12px 0; border-bottom: 1px solid #ddd; vertical-align: top;">Sudah Diterima Dari</td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #ddd;">: <strong>PT. WIJAYA KUSUMA PERDANA</strong></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 12px 0; border-bottom: 1px solid #ddd; vertical-align: top;">Kepada Pihak (Vendor)</td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #ddd;">: <?= htmlspecialchars($item['vendor']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 12px 0; border-bottom: 1px solid #ddd; vertical-align: top;">Jumlah Uang</td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #ddd;">: Rp <?= number_format($item['amount'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 12px 0; border-bottom: 1px solid #ddd; vertical-align: top;">Untuk Pembayaran</td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #ddd; line-height: 1.4;">
                            : Pelunasan dokumen tagihan Invoice No. <strong><?= htmlspecialchars($item['invoiceCode'] ?: 'N/A') ?></strong> (Tgl: <?= date('d/m/Y', strtotime($item['invoiceDate'] ?: $item['date'])) ?>) melalui metode <strong><?= htmlspecialchars($item['method']) ?></strong>.
                        </td>
                    </tr>
                </table>

                <!-- Info Nominal Pembayaran Terbilang / Badge -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
                    <div style="background-color: #e2e8f0; font-size: 1.1rem; font-weight: bold; padding: 10px 20px; border: 1px solid #333; display: inline-block;">
                        Rp <?= number_format($item['amount'], 0, ',', '.') ?>
                    </div>
                    <div style="font-size: 0.85rem; color: #555; font-family: sans-serif;">
                        Jakarta, <?= date('d F Y', strtotime($item['date'])) ?>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 50px; font-size: 0.85rem; font-family: sans-serif;">
                    <div style="text-align: center; width: 40%;">
                        <p style="margin-bottom: 60px;">Penerima Pembayaran (Vendor),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;"><?= htmlspecialchars($item['vendor']) ?></p>
                        <p style="font-size: 0.75rem; color: #777;">Kasir / Bagian Keuangan</p>
                    </div>
                    <div style="text-align: center; width: 40%;">
                        <p style="margin-bottom: 60px;">Disetujui Oleh (WKP),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;">Manajer Keuangan Proyek</p>
                        <p style="font-size: 0.75rem; color: #777;">PT. Wijaya Kusuma Perdana</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Kuitansi
                </button>
                <a href="payments.php" class="btn btn-secondary">Tutup</a>
            </div>
        </div>
    </div>
<?php
    endif;
endif;
?>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
