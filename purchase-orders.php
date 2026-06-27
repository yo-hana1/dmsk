<?php
// e:\dmsk 29-4-26\purchase-orders.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Tambah PO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $projectId = (int)$_POST['projectId'];
    $vendor = $_POST['vendor'] ?? '';
    $date = $_POST['date'] ?? '';
    
    // Ambil array detail item
    $itemNames = $_POST['item_name'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];

    // Hitung total nominal PO
    $total = 0;
    $itemsData = [];
    for ($i = 0; $i < count($itemNames); $i++) {
        if (!empty($itemNames[$i])) {
            $qty = (int)$itemQtys[$i];
            $price = (float)$itemPrices[$i];
            $total += ($qty * $price);
            $itemsData[] = [
                'name' => $itemNames[$i],
                'qty' => $qty,
                'price' => $price
            ];
        }
    }

    try {
        $db->beginTransaction();

        $stmtSeq = $db->query("SELECT COUNT(*) FROM purchase_orders");
        $seq = (int)$stmtSeq->fetchColumn() + 1;
        $yearMonth = !empty($date) ? date('Y/m', strtotime($date)) : date('Y/m');
        $code = "PO/PT. WKP/" . $yearMonth . "/" . sprintf("%03d", $seq);

        // Simpan header PO
        $stmtPO = $db->prepare("INSERT INTO purchase_orders (code, projectId, vendor, date, total, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmtPO->execute([$code, $projectId, $vendor, $date, $total]);
        $poId = $db->lastInsertId();

        // Simpan baris item PO
        $stmtItem = $db->prepare("INSERT INTO purchase_order_items (poId, name, qty, price) VALUES (?, ?, ?, ?)");
        foreach ($itemsData as $item) {
            $stmtItem->execute([$poId, $item['name'], $item['qty'], $item['price']]);
        }

        $db->commit();
        header("Location: purchase-orders.php");
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $errorMsg = "Gagal menyimpan Purchase Order: " . $e->getMessage();
    }
}

// --- 2. Aksi Persetujuan (Approve/Reject) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    try {
        if ($action === 'approve') {
            $db->prepare("UPDATE purchase_orders SET status = 'Approved' WHERE id = ?")->execute([$id]);
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE purchase_orders SET status = 'Rejected' WHERE id = ?")->execute([$id]);
        } elseif ($action === 'delete') {
            $db->prepare("UPDATE purchase_orders SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        }
        header("Location: purchase-orders.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal memperbarui data PO: " . $e->getMessage();
    }
}

// --- 3. Load Data & Filter Pencarian ---
$search = $_GET['search'] ?? '';
$sql = "SELECT po.*, p.name as projectName FROM purchase_orders po JOIN projects p ON po.projectId = p.id WHERE po.isDeleted = 0 AND p.isDeleted = 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (po.code LIKE ? OR p.name LIKE ? OR po.vendor LIKE ?)";
    $searchWildcard = "%$search%";
    $params = [$searchWildcard, $searchWildcard, $searchWildcard];
}
$sql .= " ORDER BY po.id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $poList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil master data untuk form
    $projects = $db->query("SELECT * FROM projects WHERE isDeleted = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $vendors = $db->query("SELECT DISTINCT company FROM master_users WHERE type = 'Vendor' AND status = 'Aktif' AND isDeleted = 0")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $poList = [];
    $projects = [];
    $vendors = [];
    $errorMsg = "Gagal mengambil data dari database: " . $e->getMessage();
}
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Header Menu -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; margin-bottom: 20px;">
    <h2 class="card-title" style="margin:0;"><i class="bi bi-file-earmark-text" style="color: var(--primary);"></i> Daftar Purchase Order</h2>
    <button class="btn btn-primary" onclick="openModal('addPOModal')">
        <i class="bi bi-plus-lg"></i> Buat PO Baru
    </button>
</div>

<!-- Table Data PO -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No. PO</th>
                    <th>Tanggal</th>
                    <th>Proyek</th>
                    <th>Vendor</th>
                    <th>Total Nominal</th>
                    <th>Status</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($poList) > 0): ?>
                    <?php foreach ($poList as $po): ?>
                        <tr>
                            <td style="font-weight: 600; color: #4a5568;"><?= htmlspecialchars($po['code']) ?></td>
                            <td><?= htmlspecialchars($po['date']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($po['projectName']) ?></td>
                            <td><?= htmlspecialchars($po['vendor']) ?></td>
                            <td>Rp <?= number_format($po['total'], 0, ',', '.') ?></td>
                            <td>
                                <?php
                                $statusClass = 'badge-secondary';
                                if ($po['status'] === 'Approved') $statusClass = 'badge-success';
                                if ($po['status'] === 'Pending') $statusClass = 'badge-warning';
                                if ($po['status'] === 'Rejected') $statusClass = 'badge-danger';
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= $po['status'] ?></span>
                            </td>
                            <td class="no-print">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <a href="purchase-orders.php?preview_id=<?= $po['id'] ?>" class="btn btn-outline btn-sm" title="Preview Dokumen PO">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <?php if ($po['status'] === 'Pending'): ?>
                                        <a href="purchase-orders.php?action=approve&id=<?= $po['id'] ?>" class="btn btn-success btn-sm" title="Setujui" onclick="return confirm('Approve PO ini?')">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </a>
                                        <a href="purchase-orders.php?action=reject&id=<?= $po['id'] ?>" class="btn btn-danger btn-sm" title="Tolak" onclick="return confirm('Tolak PO ini?')">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </a>
                                    <?php endif; ?>
                                    <a href="purchase-orders.php?action=delete&id=<?= $po['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen PO ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">Tidak ada Purchase Order yang ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH PO BARU -->
<div class="modal" id="addPOModal">
    <div class="modal-content wide">
        <div class="modal-header">
            <h2 class="modal-title">Buat Purchase Order Baru</h2>
            <button class="modal-close" onclick="closeModal('addPOModal')">&times;</button>
        </div>
        <form method="POST" action="purchase-orders.php">
            <input type="hidden" name="action" value="add">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>Pilih Proyek</label>
                    <select name="projectId" required class="form-control">
                        <option value="">-- Pilih Proyek --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Vendor</label>
                    <select name="vendor" required class="form-control">
                        <option value="">-- Pilih Vendor --</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal PO</label>
                    <input type="date" name="date" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <!-- Dynamic Items Table -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h4 style="font-size: 0.95rem; font-weight: 600; color: #495057;"><i class="bi bi-list-stars"></i> Rincian Barang</h4>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addPOItemRow()">
                        <i class="bi bi-plus-circle"></i> Tambah Baris
                    </button>
                </div>
                
                <div style="border: 1px solid #ced4da; border-radius: 4px; padding: 10px; background-color: #f8f9fa;">
                    <div id="poItemsContainer" style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                        <!-- Baris Item Pertama -->
                        <div class="po-item-row" style="display: grid; grid-template-columns: 2fr 1fr 1.5fr 40px; gap: 10px; margin-bottom: 10px;">
                            <input type="text" name="item_name[]" placeholder="Nama Item / Deskripsi" required class="form-control">
                            <input type="number" name="item_qty[]" placeholder="Qty" required class="form-control" oninput="calculatePOFormTotal()">
                            <input type="number" name="item_price[]" placeholder="Harga per unit" required class="form-control" oninput="calculatePOFormTotal()">
                            <button type="button" class="btn btn-outline" style="border:none; color: var(--danger); justify-content: center;" onclick="removePOItemRow(this)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; font-weight: bold; font-size: 1rem; margin-top: 15px; color: #212529;">
                    Total Est: Rp <span id="poFormTotalLabel">0</span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPOModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan PO</button>
            </div>
        </form>
    </div>
</div>

<script>
    function addPOItemRow() {
        const container = document.getElementById('poItemsContainer');
        const row = document.createElement('div');
        row.className = 'po-item-row';
        row.style = 'display: grid; grid-template-columns: 2fr 1fr 1.5fr 40px; gap: 10px; margin-bottom: 10px;';
        row.innerHTML = `
            <input type="text" name="item_name[]" placeholder="Nama Item / Deskripsi" required class="form-control">
            <input type="number" name="item_qty[]" placeholder="Qty" required class="form-control" oninput="calculatePOFormTotal()">
            <input type="number" name="item_price[]" placeholder="Harga per unit" required class="form-control" oninput="calculatePOFormTotal()">
            <button type="button" class="btn btn-outline" style="border:none; color: var(--danger); justify-content: center;" onclick="removePOItemRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        `;
        container.appendChild(row);
        calculatePOFormTotal();
    }

    function removePOItemRow(btn) {
        const container = document.getElementById('poItemsContainer');
        if (container.children.length > 1) {
            btn.closest('.po-item-row').remove();
            calculatePOFormTotal();
        } else {
            alert('Minimal harus menyertakan 1 baris rincian barang.');
        }
    }

    function calculatePOFormTotal() {
        let total = 0;
        const rows = document.querySelectorAll('.po-item-row');
        rows.forEach(row => {
            const qtyInput = row.querySelector('input[name="item_qty[]"]');
            const priceInput = row.querySelector('input[name="item_price[]"]');
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            total += (qty * price);
        });
        document.getElementById('poFormTotalLabel').innerText = total.toLocaleString('id-ID');
    }
</script>

<!-- MODAL PREVIEW DETAIL DOKUMEN PURCHASE ORDER -->
<?php
if (isset($_GET['preview_id'])):
    $previewId = (int)$_GET['preview_id'];
    $stmtP = $db->prepare("SELECT po.*, p.name as projectName, p.code as projectCode, p.location as projectLocation FROM purchase_orders po JOIN projects p ON po.projectId = p.id WHERE po.id = ?");
    $stmtP->execute([$previewId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
        // Ambil item PO
        $stmtItems = $db->prepare("SELECT * FROM purchase_order_items WHERE poId = ?");
        $stmtItems->execute([$previewId]);
        $poItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        $subTotal = (float)$item['total'];
        $ppn = $subTotal * 0.11;
        $grandTotal = $subTotal + $ppn;
?>
    <div class="modal show" id="previewPOModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail Purchase Order</h2>
                <a href="purchase-orders.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="po-document" style="font-family: serif; position: relative;">
                <!-- Watermark Logo -->
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.05; pointer-events: none; z-index: 0; text-align: center;">
                    <img src="assets/logowkp.jpg" alt="Watermark" style="width: 280px; filter: grayscale(100%);">
                </div>

                <div style="position: relative; z-index: 1;">
                    <!-- Letterhead (Kop) -->
                    <div style="display: flex; align-items: center; border-bottom: 4px double #333; padding-bottom: 15px; margin-bottom: 20px;">
                        <img src="assets/logowkp.jpg" alt="Logo WKP" style="height: 60px; margin-right: 20px; border-radius: 4px;">
                        <div style="flex: 1;">
                            <h1 style="margin: 0; font-size: 1.35rem; font-weight: bold; letter-spacing: 0.5px;">PT. WIJAYA KUSUMA PERDANA</h1>
                            <p style="margin: 5px 0 0 0; font-size: 0.75rem; color: #555; font-family: sans-serif; line-height: 1.3;">
                                General Contractor & Construction Management<br />
                                Jl. Jendral Sudirman No. 123, Jakarta, Indonesia<br />
                                Telp: (021) 555-0123 | Email: info@wkp-construction.id
                            </p>
                        </div>
                    </div>

                    <!-- Judul PO -->
                    <div style="text-align: center; margin: 25px 0;">
                        <h2 style="font-size: 1.45rem; font-weight: bold; border: 2px solid #333; display: inline-block; padding: 4px 20px; margin-bottom: 5px;">PURCHASE ORDER</h2>
                        <p style="font-size: 0.85rem; font-family: sans-serif; margin: 0;">Ref Proyek: <?= htmlspecialchars($item['projectCode']) ?></p>
                    </div>

                    <!-- Header Info Table -->
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #333; font-size: 0.85rem; font-family: sans-serif;">
                        <tbody>
                            <tr>
                                <td style="width: 60%; border: 1px solid #333; padding: 10px; vertical-align: top; line-height: 1.4;">
                                    <div style="font-weight: bold; margin-bottom: 3px; color: #555;">Kepada Yth. :</div>
                                    <div style="font-weight: bold; font-size: 0.95rem;"><?= htmlspecialchars($item['vendor']) ?></div>
                                    <div style="color: #666; margin-top: 4px;">
                                        Penyedia Barang / Material Terpilih<br />
                                        Alamat Vendor Utama Mitra PT. WKP
                                    </div>
                                </td>
                                <td style="width: 40%; border: 1px solid #333; padding: 10px; vertical-align: top; line-height: 1.5;">
                                    <div><strong>Tanggal PO:</strong> <?= date('d F Y', strtotime($item['date'])) ?></div>
                                    <div><strong>No. PO:</strong> <?= htmlspecialchars($item['code']) ?></div>
                                    <div><strong>Status:</strong> <?= htmlspecialchars($item['status']) ?></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Items Detail Table -->
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 0.85rem;">
                        <thead>
                            <tr style="background-color: #f2f2f2;">
                                <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 40px;">No.</th>
                                <th style="border: 1px solid #333; padding: 8px; text-align: left;">Deskripsi Barang / Jasa</th>
                                <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 60px;">Qty</th>
                                <th style="border: 1px solid #333; padding: 8px; text-align: center; width: 60px;">Unit</th>
                                <th style="border: 1px solid #333; padding: 8px; text-align: right; width: 130px;">Harga Satuan (Rp)</th>
                                <th style="border: 1px solid #333; padding: 8px; text-align: right; width: 140px;">Jumlah Total (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($poItems as $row): 
                                $rowTotal = (float)$row['qty'] * (float)$row['price'];
                            ?>
                                <tr>
                                    <td style="border: 1px solid #333; padding: 8px; text-align: center;"><?= $no++ ?></td>
                                    <td style="border: 1px solid #333; padding: 8px;"><?= htmlspecialchars($row['name']) ?></td>
                                    <td style="border: 1px solid #333; padding: 8px; text-align: center;"><?= $row['qty'] ?></td>
                                    <td style="border: 1px solid #333; padding: 8px; text-align: center;">Pcs / Unit</td>
                                    <td style="border: 1px solid #333; padding: 8px; text-align: right;"><?= number_format($row['price'], 0, ',', '.') ?></td>
                                    <td style="border: 1px solid #333; padding: 8px; text-align: right;"><?= number_format($rowTotal, 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Kalkulasi Pajak -->
                            <tr>
                                <td colspan="4" rowspan="3" style="border: 1px solid #333; padding: 10px; vertical-align: top; background-color: #fff; font-size: 0.8rem; font-family: sans-serif; color: #666; line-height: 1.4;">
                                    <strong>Ketentuan Pengiriman & Pembayaran:</strong><br>
                                    1. Material dikirim langsung ke lokasi proyek: <strong><?= htmlspecialchars($item['projectLocation']) ?></strong><br>
                                    2. Pembayaran ditransfer ke rekening vendor resmi yang disepakati.<br>
                                    3. Melampirkan surat jalan asli saat serah terima material di lokasi.
                                </td>
                                <td style="border: 1px solid #333; padding: 8px; fontWeight: bold; text-align: right; font-family: sans-serif;">Subtotal</td>
                                <td style="border: 1px solid #333; padding: 8px; text-align: right; font-weight: bold;"><?= number_format($subTotal, 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #333; padding: 8px; fontWeight: bold; text-align: right; font-family: sans-serif;">PPN 11%</td>
                                <td style="border: 1px solid #333; padding: 8px; text-align: right;"><?= number_format($ppn, 0, ',', '.') ?></td>
                            </tr>
                            <tr style="background-color: #e2e8f0; font-weight: bold;">
                                <td style="border: 1px solid #333; padding: 8px; text-align: right; font-family: sans-serif;">Total Tagihan</td>
                                <td style="border: 1px solid #333; padding: 8px; text-align: right; color: var(--primary);"><?= number_format($grandTotal, 0, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Tanda Tangan Hormat Kami -->
                    <div style="display: flex; justify-content: space-between; margin-top: 40px; font-size: 0.85rem; font-family: sans-serif;">
                        <div style="text-align: center; width: 40%;">
                            <p style="margin-bottom: 60px;">Disetujui Oleh (Vendor),</p>
                            <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;"><?= htmlspecialchars($item['vendor']) ?></p>
                            <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Perwakilan Pihak Vendor</p>
                        </div>
                        <div style="text-align: center; width: 40%;">
                            <p style="margin-bottom: 60px;">Hormat Kami,</p>
                            <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;">Bagian Keuangan & Material</p>
                            <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">PT. WIJAYA KUSUMA PERDANA</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak PO (Cetak PDF)
                </button>
                <a href="purchase-orders.php" class="btn btn-secondary">Tutup</a>
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
