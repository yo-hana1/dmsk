<?php
// e:\dmsk 29-4-26\terima-barang.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Tambah Terima Barang ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $poId = (int)$_POST['poId'];
    $date = $_POST['date'] ?? '';
    $pic = $_POST['pic'] ?? 'Budi Santoso (Manajer Gudang)';

    try {
        $stmtPO = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
        $stmtPO->execute([$poId]);
        $po = $stmtPO->fetch(PDO::FETCH_ASSOC);

        if ($po) {
            $vendor = $po['vendor'];
            
            $stmtSeq = $db->query("SELECT COUNT(*) FROM terima_barang");
            $seq = (int)$stmtSeq->fetchColumn() + 1;
            $code = "TB/" . preg_replace('/\s+/', '', $vendor) . "/" . date('Y/m', strtotime($date)) . "/" . sprintf("%03d", $seq);

            $stmt = $db->prepare("INSERT INTO terima_barang (code, poId, vendor, date, pic, status, fileType, fileSize) VALUES (?, ?, ?, ?, ?, 'Received', 'PDF', '1.5 MB')");
            $stmt->execute([$code, $poId, $vendor, $date, $pic]);

            header("Location: terima-barang.php");
            exit;
        } else {
            $errorMsg = "Referensi PO tidak valid.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Gagal menyimpan data terima barang: " . $e->getMessage();
    }
}

// --- 2. Aksi Hapus ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->prepare("UPDATE terima_barang SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        header("Location: terima-barang.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus terima barang: " . $e->getMessage();
    }
}

// --- 3. Load Data & Filter Pencarian ---
$search = $_GET['search'] ?? '';
$sql = "SELECT tb.*, po.code as poCode FROM terima_barang tb JOIN purchase_orders po ON tb.poId = po.id WHERE tb.isDeleted = 0 AND po.isDeleted = 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (tb.code LIKE ? OR po.code LIKE ? OR tb.vendor LIKE ?)";
    $searchWildcard = "%$search%";
    $params = [$searchWildcard, $searchWildcard, $searchWildcard];
}
$sql .= " ORDER BY tb.id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tbList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil PO yang eligible (Approved, Lunas, dan Belum di-input Terima Barang sebelumnya,
    // atau untuk kemudahan demo, tampilkan semua PO yang disetujui & lunas)
    $eligiblePOs = $db->query("
        SELECT po.* 
        FROM purchase_orders po
        JOIN invoices inv ON inv.poId = po.id
        JOIN payments pay ON pay.invoiceId = inv.id
        WHERE po.status = 'Approved'
          AND po.isDeleted = 0
          AND inv.status = 'Paid'
          AND inv.isDeleted = 0
          AND pay.status = 'Approved'
          AND pay.isDeleted = 0
        ORDER BY po.code DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tbList = [];
    $eligiblePOs = [];
    $errorMsg = "Gagal memuat data dari database: " . $e->getMessage();
}
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Header Menu -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; margin-bottom: 20px;">
    <h2 class="card-title" style="margin:0;"><i class="bi bi-inbox" style="color: var(--primary);"></i> Daftar Terima Barang</h2>
    <button class="btn btn-primary" onclick="openModal('addTBModal')">
        <i class="bi bi-plus-lg"></i> Input Terima Barang
    </button>
</div>

<!-- Table Data Terima Barang -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Terima Barang</th>
                    <th>No. PO Ref</th>
                    <th>Vendor</th>
                    <th>Tanggal Diterima</th>
                    <th>PIC (Gudang)</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($tbList) > 0): ?>
                    <?php foreach ($tbList as $tb): ?>
                        <tr>
                            <td style="font-weight: 600; color: #4a5568;"><?= htmlspecialchars($tb['code']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($tb['poCode']) ?></td>
                            <td><?= htmlspecialchars($tb['vendor']) ?></td>
                            <td><?= htmlspecialchars($tb['date']) ?></td>
                            <td><?= htmlspecialchars($tb['pic']) ?></td>
                            <td class="no-print">
                                <div style="display: flex; gap: 10px;">
                                    <a href="terima-barang.php?preview_id=<?= $tb['id'] ?>" class="btn btn-outline btn-sm" title="Preview Dokumen Terima Barang">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <a href="terima-barang.php?action=delete&id=<?= $tb['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen Terima Barang ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada data terima barang.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL INPUT TERIMA BARANG -->
<div class="modal" id="addTBModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Form Input Terima Barang</h2>
            <button class="modal-close" onclick="closeModal('addTBModal')">&times;</button>
        </div>
        <form method="POST" action="terima-barang.php">
            <input type="hidden" name="action" value="add">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group">
                    <label>Pilih PO Referensi (Yang Sudah Lunas)</label>
                    <select name="poId" required class="form-control">
                        <option value="">-- Pilih PO --</option>
                        <?php foreach ($eligiblePOs as $po): ?>
                            <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['code']) ?> - <?= htmlspecialchars($po['vendor']) ?> (Lunas)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Terima Barang</label>
                    <input type="date" name="date" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>PIC Gudang / Pemeriksa</label>
                    <input type="text" name="pic" required class="form-control" value="Budi Santoso (Manajer Gudang)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTBModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Penerimaan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PREVIEW DETAIL DOKUMEN TERIMA BARANG -->
<?php
if (isset($_GET['preview_id'])):
    $previewId = (int)$_GET['preview_id'];
    $stmtP = $db->prepare("
        SELECT tb.*, po.code as poCode, po.date as poDate, p.name as projectName, p.code as projectCode 
        FROM terima_barang tb 
        JOIN purchase_orders po ON tb.poId = po.id 
        JOIN projects p ON po.projectId = p.id 
        WHERE tb.id = ?
    ");
    $stmtP->execute([$previewId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
        // Ambil item PO untuk dicantumkan sebagai barang yang diterima
        $stmtItems = $db->prepare("SELECT * FROM purchase_order_items WHERE poId = ?");
        $stmtItems->execute([$item['poId']]);
        $receivedItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>
    <div class="modal show" id="previewTBModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail Penerimaan Barang</h2>
                <a href="terima-barang.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="tb-document" style="font-family: serif; position: relative;">
                <div style="display: flex; align-items: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <img src="assets/logowkp.jpg" alt="Logo WKP" style="height: 50px; border-radius: 4px; margin-right: 20px;">
                    <div style="flex: 1;">
                        <h3 style="margin: 0; font-size: 1.25rem; font-weight: bold; color: var(--primary);">PT. WIJAYA KUSUMA PERDANA</h3>
                        <p style="margin: 0; font-size: 0.75rem; color: #555;">General Contractor & Developer</p>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h4 style="margin: 0; font-size: 1.35rem; font-weight: bold; text-decoration: underline; letter-spacing: 1px;">SURAT TANDA TERIMA BARANG (GUDANG)</h4>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555; font-family: sans-serif;">No. Bukti TB: <?= htmlspecialchars($item['code']) ?></p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 0.85rem; font-family: sans-serif; line-height: 1.5;">
                    <tr>
                        <td style="width: 25%; font-weight: bold; padding: 5px 0;">Nama Proyek</td>
                        <td>: <?= htmlspecialchars($item['projectName']) ?> (Ref: <?= htmlspecialchars($item['projectCode']) ?>)</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 5px 0;">Dikirim Oleh (Vendor)</td>
                        <td>: <?= htmlspecialchars($item['vendor']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 5px 0;">No. Ref PO</td>
                        <td>: <?= htmlspecialchars($item['poCode']) ?> (Tgl PO: <?= date('d/m/Y', strtotime($item['poDate'])) ?>)</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 5px 0;">Tanggal Diterima</td>
                        <td>: <?= date('d F Y', strtotime($item['date'])) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 5px 0;">PIC Gudang / Penerima</td>
                        <td>: <?= htmlspecialchars($item['pic']) ?></td>
                    </tr>
                </table>

                <!-- Daftar Barang Yang Diterima -->
                <h5 style="font-size: 0.9rem; font-family: sans-serif; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #333; padding-bottom: 5px;">Rincian Barang yang Diterima di Gudang:</h5>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 0.85rem;">
                    <thead>
                        <tr style="background-color: #f2f2f2; border: 1px solid #333;">
                            <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 40px;">No.</th>
                            <th style="border: 1px solid #333; padding: 8px; text-align: left;">Nama Material / Barang</th>
                            <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 80px;">Qty PO</th>
                            <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 100px;">Qty Diterima</th>
                            <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 100px;">Kondisi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($receivedItems as $row): 
                        ?>
                            <tr>
                                <td style="border: 1px solid #333; padding: 8px; text-align: center;"><?= $no++ ?></td>
                                <td style="border: 1px solid #333; padding: 8px;"><?= htmlspecialchars($row['name']) ?></td>
                                <td style="border: 1px solid #333; padding: 8px; text-align: center;"><?= $row['qty'] ?> Unit</td>
                                <td style="border: 1px solid #333; padding: 8px; text-align: center; font-weight: bold; color: var(--success);"><?= $row['qty'] ?> Unit</td>
                                <td style="border: 1px solid #333; padding: 8px; text-align: center;">Lengkap / Baik</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; margin-top: 50px; font-size: 0.85rem; font-family: sans-serif;">
                    <div style="text-align: center; width: 40%;">
                        <p style="margin-bottom: 60px;">Diserahkan Oleh (Vendor),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;">Kurir / Pengirim Pihak Vendor</p>
                    </div>
                    <div style="text-align: center; width: 40%;">
                        <p style="margin-bottom: 60px;">Diterima Oleh (WKP Gudang),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;"><?= htmlspecialchars(explode(' (', $item['pic'])[0]) ?></p>
                        <p style="font-size: 0.75rem; color: #777;">Petugas Gudang Proyek</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Tanda Terima
                </button>
                <a href="terima-barang.php" class="btn btn-secondary">Tutup</a>
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
