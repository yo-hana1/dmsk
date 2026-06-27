<?php
// e:\dmsk 29-4-26\invoices.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Tambah Invoice ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $poId = (int)$_POST['poId'];
    $date = $_POST['date'] ?? '';

    try {
        // Ambil data PO terkait
        $stmtPO = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
        $stmtPO->execute([$poId]);
        $po = $stmtPO->fetch(PDO::FETCH_ASSOC);

        if ($po) {
            $vendor = $po['vendor'];
            $amount = (float)$po['total'];
            
            $stmtSeq = $db->query("SELECT COUNT(*) FROM invoices");
            $seq = (int)$stmtSeq->fetchColumn() + 1;
            $code = "INV/" . preg_replace('/\s+/', '', $vendor) . "/" . date('Y/m', strtotime($date)) . "/" . sprintf("%03d", $seq);

            $stmt = $db->prepare("INSERT INTO invoices (code, poId, vendor, date, amount, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$code, $poId, $vendor, $date, $amount]);

            header("Location: invoices.php");
            exit;
        } else {
            $errorMsg = "Referensi PO tidak ditemukan.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Gagal menyimpan invoice: " . $e->getMessage();
    }
}

// --- 2. Aksi Bayar Invoice Langsung ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    $invoiceId = (int)$_POST['invoiceId'];
    $method = $_POST['method'] ?? 'Transfer';
    $date = $_POST['date'] ?? '';

    try {
        $db->beginTransaction();

        // Ambil data Invoice terkait
        $stmtInv = $db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmtInv->execute([$invoiceId]);
        $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);

        if ($inv) {
            $vendor = $inv['vendor'];
            $amount = (float)$inv['amount'];

            $stmtSeq = $db->query("SELECT COUNT(*) FROM payments");
            $seq = (int)$stmtSeq->fetchColumn() + 1;
            $code = "KWT/" . preg_replace('/\s+/', '', $vendor) . "/" . sprintf("%03d", $seq) . "/" . date('m/Y', strtotime($date));

            // Simpan Kuitansi Pembayaran
            $stmtPay = $db->prepare("INSERT INTO payments (code, invoiceId, vendor, date, amount, method, status) VALUES (?, ?, ?, ?, ?, ?, 'Approved')");
            $stmtPay->execute([$code, $invoiceId, $vendor, $date, $amount, $method]);

            // Update Status Invoice menjadi Paid (Lunas)
            $stmtUp = $db->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ?");
            $stmtUp->execute([$invoiceId]);

            $db->commit();
            header("Location: invoices.php");
            exit;
        } else {
            $errorMsg = "Data invoice tidak valid.";
        }
    } catch (PDOException $e) {
        $db->rollBack();
        $errorMsg = "Gagal memproses pembayaran: " . $e->getMessage();
    }
}

// --- 3. Aksi Hapus ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->prepare("UPDATE invoices SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        header("Location: invoices.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus invoice: " . $e->getMessage();
    }
}

// --- 4. Load Data & Filter Pencarian ---
$search = $_GET['search'] ?? '';
$sql = "SELECT inv.*, po.code as poCode FROM invoices inv JOIN purchase_orders po ON inv.poId = po.id WHERE inv.isDeleted = 0 AND po.isDeleted = 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (inv.code LIKE ? OR po.code LIKE ? OR inv.vendor LIKE ?)";
    $searchWildcard = "%$search%";
    $params = [$searchWildcard, $searchWildcard, $searchWildcard];
}
$sql .= " ORDER BY inv.id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $invoicesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Load PO yang disetujui untuk form dropdown
    $approvedPOs = $db->query("SELECT * FROM purchase_orders WHERE status = 'Approved' AND isDeleted = 0 ORDER BY code DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invoicesList = [];
    $approvedPOs = [];
    $errorMsg = "Gagal memuat data: " . $e->getMessage();
}
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Header Menu -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; margin-bottom: 20px;">
    <h2 class="card-title" style="margin:0;"><i class="bi bi-file-earmark-spreadsheet" style="color: var(--primary);"></i> Daftar Invoice Vendor</h2>
    <button class="btn btn-primary" onclick="openModal('addInvoiceModal')">
        <i class="bi bi-plus-lg"></i> Input Invoice Baru
    </button>
</div>

<!-- Table Data Invoice -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>No. PO Ref</th>
                    <th>Vendor</th>
                    <th>Nominal</th>
                    <th>Tgl Invoice</th>
                    <th>Status</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($invoicesList) > 0): ?>
                    <?php foreach ($invoicesList as $inv): ?>
                        <tr>
                            <td style="font-weight: 600; color: #4a5568;"><?= htmlspecialchars($inv['code']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($inv['poCode']) ?></td>
                            <td><?= htmlspecialchars($inv['vendor']) ?></td>
                            <td>Rp <?= number_format($inv['amount'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($inv['date']) ?></td>
                            <td>
                                <span class="badge <?= ($inv['status'] === 'Paid') ? 'badge-success' : 'badge-warning' ?>">
                                    <?= htmlspecialchars($inv['status']) ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <?php if ($inv['status'] !== 'Paid'): ?>
                                        <button class="btn btn-success btn-sm" onclick="triggerPaymentModal(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['code']) ?>', '<?= htmlspecialchars($inv['vendor']) ?>', <?= $inv['amount'] ?>)">
                                            <i class="bi bi-credit-card"></i> Bayar
                                        </button>
                                    <?php endif; ?>
                                    <a href="invoices.php?preview_id=<?= $inv['id'] ?>" class="btn btn-outline btn-sm" title="Preview Dokumen Invoice">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <a href="invoices.php?action=delete&id=<?= $inv['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen invoice ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">Tidak ada Invoice yang ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL INPUT INVOICE -->
<div class="modal" id="addInvoiceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Input Invoice Masuk</h2>
            <button class="modal-close" onclick="closeModal('addInvoiceModal')">&times;</button>
        </div>
        <form method="POST" action="invoices.php">
            <input type="hidden" name="action" value="add">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group">
                    <label>Pilih PO Referensi</label>
                    <select name="poId" required class="form-control">
                        <option value="">-- Pilih PO --</option>
                        <?php foreach ($approvedPOs as $po): ?>
                            <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['code']) ?> - <?= htmlspecialchars($po['vendor']) ?> (Rp <?= number_format($po['total'], 0, ',', '.') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Dokumen Invoice</label>
                    <input type="date" name="date" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addInvoiceModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REALISASI PEMBAYARAN -->
<div class="modal" id="paymentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Form Realisasi Pembayaran</h2>
            <button class="modal-close" onclick="closeModal('paymentModal')">&times;</button>
        </div>
        <form method="POST" action="invoices.php">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="invoiceId" id="payInvoiceId">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group">
                    <label>Referensi Invoice</label>
                    <input type="text" id="payInvoiceCode" class="form-control" style="background-color: #f8f9fa;" readonly>
                </div>
                <div class="form-group">
                    <label>Nama Vendor</label>
                    <input type="text" id="payVendorName" class="form-control" style="background-color: #f8f9fa;" readonly>
                </div>
                <div class="form-group">
                    <label>Nominal Pembayaran</label>
                    <input type="text" id="payInvoiceAmount" class="form-control" style="background-color: #f8f9fa;" readonly>
                </div>
                <div class="form-group">
                    <label>Metode Pembayaran</label>
                    <select name="method" required class="form-control">
                        <option value="Transfer">Transfer Bank</option>
                        <option value="Tunai">Tunai / Kas Kecil</option>
                        <option value="Cek">Cek / Giro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Bayar</label>
                    <input type="date" name="date" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Proses Bayar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function triggerPaymentModal(id, code, vendor, amount) {
        document.getElementById('payInvoiceId').value = id;
        document.getElementById('payInvoiceCode').value = code;
        document.getElementById('payVendorName').value = vendor;
        document.getElementById('payInvoiceAmount').value = 'Rp ' + amount.toLocaleString('id-ID');
        openModal('paymentModal');
    }
</script>

<!-- MODAL PREVIEW DETAIL DOKUMEN INVOICE -->
<?php
if (isset($_GET['preview_id'])):
    $previewId = (int)$_GET['preview_id'];
    $stmtP = $db->prepare("SELECT inv.*, po.code as poCode, po.date as poDate, p.name as projectName FROM invoices inv JOIN purchase_orders po ON inv.poId = po.id JOIN projects p ON po.projectId = p.id WHERE inv.id = ?");
    $stmtP->execute([$previewId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
?>
    <div class="modal show" id="previewInvoiceModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail Invoice Vendor</h2>
                <a href="invoices.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="invoice-document" style="font-family: serif; position: relative;">
                <div style="display: flex; align-items: center; border-bottom: 2px dashed #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <i class="bi bi-receipt" style="font-size: 2.2rem; margin-right: 15px; color: var(--primary);"></i>
                    <div style="flex: 1;">
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: bold;"><?= htmlspecialchars($item['vendor']) ?></h3>
                        <p style="margin: 3px 0 0 0; font-size: 0.75rem; color: #666; font-family: sans-serif;">Penyedia Material Resmi Mitra PT. WKP</p>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 1.2rem; font-weight: bold; text-transform: uppercase; color: #555;">I N V O I C E</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 25px; font-size: 0.85rem; font-family: sans-serif; line-height: 1.5;">
                    <div>
                        <strong>Ditagihkan Kepada:</strong><br>
                        PT. Wijaya Kusuma Perdana<br>
                        Departemen Akuntansi & Keuangan Proyek<br>
                        Proyek: <strong><?= htmlspecialchars($item['projectName']) ?></strong>
                    </div>
                    <div style="text-align: right;">
                        <strong>Nomor Invoice:</strong> <?= htmlspecialchars($item['code']) ?><br>
                        <strong>Tanggal:</strong> <?= date('d F Y', strtotime($item['date'])) ?><br>
                        <strong>Ref PO:</strong> <?= htmlspecialchars($item['poCode']) ?> (Tgl: <?= date('d/m/Y', strtotime($item['poDate'])) ?>)
                    </div>
                </div>

                <!-- Detail Tagihan -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #333; border-top: 2px solid #333;">
                            <th style="padding: 10px; text-align: left;">Deskripsi Penagihan</th>
                            <th style="padding: 10px; text-align: right; width: 200px;">Total Nominal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 15px 10px; line-height: 1.4;">
                                Penagihan atas pengadaan material / jasa kontraktor sesuai rincian pada Purchase Order referensi <strong><?= htmlspecialchars($item['poCode']) ?></strong>.
                            </td>
                            <td style="padding: 15px 10px; text-align: right; font-weight: bold; font-size: 0.95rem; vertical-align: top;">
                                <?= number_format($item['amount'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <tr style="border-top: 1px solid #ddd; font-weight: bold; background-color: #f8f9fa; font-size: 0.95rem;">
                            <td style="padding: 12px 10px; text-align: right;">Total yang Harus Dibayar:</td>
                            <td style="padding: 12px 10px; text-align: right; color: var(--primary);"><?= number_format($item['amount'], 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; margin-top: 50px; font-size: 0.85rem; font-family: sans-serif;">
                    <div style="width: 50%;">
                        <div style="border: 1px solid #ced4da; padding: 10px; border-radius: 4px; line-height: 1.4; color: #555;">
                            <strong>Metode Pembayaran:</strong><br>
                            Pembayaran tagihan ini ditransfer langsung ke rekening bank vendor yang terdaftar resmi di PT. WKP. Mohon cantumkan nomor rujukan invoice saat transfer.
                        </div>
                    </div>
                    <div style="text-align: center; width: 40%;">
                        <p style="margin-bottom: 60px;">Hormat Kami (Vendor),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;"><?= htmlspecialchars($item['vendor']) ?></p>
                        <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Penanggung Jawab Keuangan</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Invoice
                </button>
                <a href="invoices.php" class="btn btn-secondary">Tutup</a>
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
