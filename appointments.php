<?php
// e:\dmsk 29-4-26\appointments.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Tambah Penunjukan (ST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_st') {
    $projectId = (int)$_POST['projectId'];
    $mainCon = $_POST['mainCon'] ?? '';
    $subCon = $_POST['subCon'] ?? '';
    $vendor = $_POST['vendor'] ?? '';
    $date = $_POST['date'] ?? '';

    try {
        $stmtSeq = $db->query("SELECT COUNT(*) FROM appointments");
        $seq = (int)$stmtSeq->fetchColumn() + 1;
        $yearMonth = !empty($date) ? date('Y/m', strtotime($date)) : date('Y/m');
        $code = "ST/PT. WKP/" . $yearMonth . "/" . sprintf("%03d", $seq);

        $stmt = $db->prepare("INSERT INTO appointments (code, projectId, mainCon, subCon, vendor, date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $projectId, $mainCon, $subCon, $vendor, $date]);
        
        header("Location: appointments.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menyimpan penunjukan: " . $e->getMessage();
    }
}

// --- 2. Aksi Tambah LPJ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_lpj') {
    $appointmentId = (int)$_POST['appointmentId'];
    $closingReportDate = $_POST['closingReportDate'] ?? '';

    try {
        // Ambil data proyek berdasarkan appointmentId
        $stmtApp = $db->prepare("SELECT a.*, p.name as projectName, p.owner as owner FROM appointments a JOIN projects p ON a.projectId = p.id WHERE a.id = ?");
        $stmtApp->execute([$appointmentId]);
        $app = $stmtApp->fetch(PDO::FETCH_ASSOC);

        if ($app) {
            $stmtSeq = $db->query("SELECT COUNT(*) FROM lpj_reports");
            $seq = (int)$stmtSeq->fetchColumn() + 1;
            $projectName = $app['projectName'];
            $owner = $app['owner'];
            $code = "LPJ/WKP/" . preg_replace('/\s+/', '', $projectName) . "/" . date('Y', strtotime($closingReportDate)) . "/" . sprintf("%03d", $seq);

            $stmt = $db->prepare("INSERT INTO lpj_reports (code, appointmentId, projectName, owner, closingReportDate) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$code, $appointmentId, $projectName, $owner, $closingReportDate]);
            
            header("Location: appointments.php");
            exit;
        } else {
            $errorMsg = "Referensi penunjukan serah terima tidak valid.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Gagal menyimpan LPJ: " . $e->getMessage();
    }
}

// --- 3. Aksi Hapus ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $type = $_GET['action'];
    try {
        if ($type === 'delete_st') {
            $db->prepare("UPDATE appointments SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        } elseif ($type === 'delete_lpj') {
            $db->prepare("UPDATE lpj_reports SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        }
        header("Location: appointments.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus data: " . $e->getMessage();
    }
}

// --- 4. Load Data & Filter Pencarian ---
$search = $_GET['search'] ?? '';
$searchWildcard = "%$search%";

// Load Penunjukan (ST)
try {
    $stSql = "SELECT a.*, p.name as projectName FROM appointments a JOIN projects p ON a.projectId = p.id WHERE a.isDeleted = 0 AND p.isDeleted = 0";
    $stParams = [];
    if (!empty($search)) {
        $stSql .= " AND (a.code LIKE ? OR p.name LIKE ? OR a.vendor LIKE ? OR a.mainCon LIKE ? OR a.subCon LIKE ?)";
        $stParams = array_fill(0, 5, $searchWildcard);
    }
    $stSql .= " ORDER BY a.id DESC";
    $stmtSt = $db->prepare($stSql);
    $stmtSt->execute($stParams);
    $stList = $stmtSt->fetchAll(PDO::FETCH_ASSOC);

    // Load LPJ
    $lpjSql = "SELECT l.*, a.code as appointmentCode FROM lpj_reports l JOIN appointments a ON l.appointmentId = a.id JOIN projects p ON a.projectId = p.id WHERE l.isDeleted = 0 AND a.isDeleted = 0";
    $lpjParams = [];
    if (!empty($search)) {
        $lpjSql .= " AND (l.code LIKE ? OR l.projectName LIKE ? OR l.owner LIKE ? OR a.code LIKE ?)";
        $lpjParams = array_fill(0, 4, $searchWildcard);
    }
    $lpjSql .= " ORDER BY l.id DESC";
    $stmtLpj = $db->prepare($lpjSql);
    $stmtLpj->execute($lpjParams);
    $lpjList = $stmtLpj->fetchAll(PDO::FETCH_ASSOC);

    // Load master list untuk form dropdown
    $projects = $db->query("SELECT * FROM projects WHERE isDeleted = 0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Kelompokkan master users berdasarkan tipe
    $mainContractors = $db->query("SELECT DISTINCT company FROM master_users WHERE type = 'Main Contractor' AND status = 'Aktif' AND isDeleted = 0")->fetchAll(PDO::FETCH_COLUMN);
    $subContractors = $db->query("SELECT DISTINCT company FROM master_users WHERE type = 'Sub Contractor' AND status = 'Aktif' AND isDeleted = 0")->fetchAll(PDO::FETCH_COLUMN);
    $vendors = $db->query("SELECT DISTINCT company FROM master_users WHERE type = 'Vendor' AND status = 'Aktif' AND isDeleted = 0")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $errorMsg = "Gagal memuat data dari database: " . $e->getMessage();
    $stList = [];
    $lpjList = [];
    $projects = [];
    $mainContractors = [];
    $subContractors = [];
    $vendors = [];
}
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- BAGIAN PENUNJUKAN & SERAH TERIMA -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px 20px;">
    <h2 class="card-title" style="margin: 0;"><i class="bi bi-handshake" style="color: var(--primary);"></i> Penunjukan & Serah Terima</h2>
    <button class="btn btn-primary" onclick="openModal('addSTModal')">
        <i class="bi bi-plus-lg"></i> Input Penunjukan
    </button>
</div>

<div class="card" style="margin-bottom: 35px;">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No. ST</th>
                    <th>Proyek</th>
                    <th>Main Contractor</th>
                    <th>Sub Contractor</th>
                    <th>Vendor Utama</th>
                    <th>Tgl Penunjukan</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($stList) > 0): ?>
                    <?php foreach ($stList as $st): ?>
                        <tr>
                            <td style="font-weight: 600; color: #4a5568;"><?= htmlspecialchars($st['code']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($st['projectName']) ?></td>
                            <td><?= htmlspecialchars($st['mainCon']) ?></td>
                            <td><?= htmlspecialchars($st['subCon'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($st['vendor']) ?></td>
                            <td><?= htmlspecialchars($st['date']) ?></td>
                            <td class="no-print">
                                <div style="display: flex; gap: 10px;">
                                    <a href="appointments.php?preview_st=<?= $st['id'] ?>" class="btn btn-outline btn-sm" title="Preview Dokumen ST">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <a href="appointments.php?action=delete_st&id=<?= $st['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen serah terima ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 25px;">Tidak ada data penunjukan serah terima.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- BAGIAN LAPORAN PERTANGGUNGJAWABAN (LPJ) -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 15px 20px;">
    <h2 class="card-title" style="margin: 0;"><i class="bi bi-file-earmark-bar-graph" style="color: var(--primary);"></i> Laporan Penutupan (LPJ)</h2>
    <button class="btn btn-primary" onclick="openModal('addLPJModal')">
        <i class="bi bi-plus-lg"></i> Input LPJ
    </button>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No. LPJ</th>
                    <th>Ref Serah Terima</th>
                    <th>Nama Proyek</th>
                    <th>Pemilik Proyek</th>
                    <th>Tgl Penutupan</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($lpjList) > 0): ?>
                    <?php foreach ($lpjList as $lpj): ?>
                        <tr>
                            <td style="font-weight: 600; color: #4a5568;"><?= htmlspecialchars($lpj['code']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($lpj['appointmentCode']) ?></td>
                            <td><?= htmlspecialchars($lpj['projectName']) ?></td>
                            <td><?= htmlspecialchars($lpj['owner']) ?></td>
                            <td><?= htmlspecialchars($lpj['closingReportDate']) ?></td>
                            <td class="no-print">
                                <div style="display: flex; gap: 10px;">
                                    <a href="appointments.php?preview_lpj=<?= $lpj['id'] ?>" class="btn btn-outline btn-sm" title="Preview Dokumen LPJ">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <a href="appointments.php?action=delete_lpj&id=<?= $lpj['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen LPJ ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">Belum ada laporan pertanggungjawaban (LPJ).</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL INPUT ST -->
<div class="modal" id="addSTModal">
    <div class="modal-content wide">
        <div class="modal-header">
            <h2 class="modal-title">Input Penunjukan Pihak</h2>
            <button class="modal-close" onclick="closeModal('addSTModal')">&times;</button>
        </div>
        <form method="POST" action="appointments.php">
            <input type="hidden" name="action" value="add_st">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
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
                    <label>Main Contractor</label>
                    <select name="mainCon" required class="form-control">
                        <option value="">-- Pilih Main Contractor --</option>
                        <?php foreach ($mainContractors as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sub Contractor</label>
                    <select name="subCon" class="form-control">
                        <option value="">-- Pilih Sub Contractor (Opsional) --</option>
                        <?php foreach ($subContractors as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vendor Utama</label>
                    <select name="vendor" required class="form-control">
                        <option value="">-- Pilih Vendor Utama --</option>
                        <?php foreach ($vendors as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Serah Terima / Penunjukan</label>
                    <input type="date" name="date" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSTModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Penunjukan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL INPUT LPJ -->
<div class="modal" id="addLPJModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Form Laporan Pertanggungjawaban (LPJ)</h2>
            <button class="modal-close" onclick="closeModal('addLPJModal')">&times;</button>
        </div>
        <form method="POST" action="appointments.php">
            <input type="hidden" name="action" value="add_lpj">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group">
                    <label>Referensi Serah Terima (ST)</label>
                    <select name="appointmentId" required class="form-control" id="stSelector" onchange="updateLPJDetails(this)">
                        <option value="">-- Pilih Rujukan ST --</option>
                        <?php foreach ($stList as $st): ?>
                            <option value="<?= $st['id'] ?>" data-owner="<?= htmlspecialchars($st['mainCon']) ?>"><?= htmlspecialchars($st['code']) ?> - <?= htmlspecialchars($st['projectName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tanggal Laporan Penutupan</label>
                    <input type="date" name="closingReportDate" required class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addLPJModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan LPJ</button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateLPJDetails(selectElem) {
        // Logika client-side jika ingin mengisi field secara otomatis saat drop-down dipilih
    }
</script>

<!-- MODAL PREVIEW DETAIL DOKUMEN SERAH TERIMA -->
<?php
if (isset($_GET['preview_st'])):
    $previewStId = (int)$_GET['preview_st'];
    $stmtP = $db->prepare("SELECT a.*, p.name as projectName, p.owner as projectOwner, p.details as projectDetails, p.pic as projectPic FROM appointments a JOIN projects p ON a.projectId = p.id WHERE a.id = ?");
    $stmtP->execute([$previewStId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
?>
    <div class="modal show" id="previewSTModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail Serah Terima</h2>
                <a href="appointments.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="st-document">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <img src="assets/logowkp.jpg" alt="Logo WKP" style="height: 50px; border-radius: 4px;">
                    <div style="text-align: right;">
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: bold; color: var(--primary);">PT. WIJAYA KUSUMA PERDANA</h3>
                        <p style="margin: 0; font-size: 0.75rem; color: #555;">General Contractor & Developer</p>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h4 style="margin: 0; font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">SURAT PENUNJUKAN & SERAH TERIMA PIHAK</h4>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555;">No. Register: <?= htmlspecialchars($item['code']) ?></p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 0.9rem;">
                    <tr>
                        <td style="width: 30%; font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Nama Proyek</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['projectName']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Main Contractor</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['mainCon']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Sub Contractor</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['subCon'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Vendor Supplier</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['vendor']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Tanggal Serah Terima</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['date']) ?></td>
                    </tr>
                </table>

                <div style="display: flex; justify-content: space-between; margin-top: 50px; font-size: 0.85rem;">
                    <div style="text-align: center; width: 30%;">
                        <p style="margin-bottom: 60px;">Pihak I (Main Con),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 10px;"><?= htmlspecialchars($item['mainCon']) ?></p>
                    </div>
                    <div style="text-align: center; width: 30%;">
                        <p style="margin-bottom: 60px;">Pihak II (Vendor),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 10px;"><?= htmlspecialchars($item['vendor']) ?></p>
                    </div>
                    <div style="text-align: center; width: 30%;">
                        <p style="margin-bottom: 60px;">Mengetahui (WKP),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 10px;"><?= htmlspecialchars(explode(' (', $item['projectPic'])[0] ?? 'Direktur Utama') ?></p>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Dokumen
                </button>
                <a href="appointments.php" class="btn btn-secondary">Tutup</a>
            </div>
        </div>
    </div>
<?php
    endif;
endif;
?>

<!-- MODAL PREVIEW DETAIL DOKUMEN LPJ -->
<?php
if (isset($_GET['preview_lpj'])):
    $previewLpjId = (int)$_GET['preview_lpj'];
    $stmtP = $db->prepare("SELECT l.*, a.code as appointmentCode, a.date as appointmentDate, a.mainCon as mainCon, a.vendor as vendor FROM lpj_reports l JOIN appointments a ON l.appointmentId = a.id WHERE l.id = ?");
    $stmtP->execute([$previewLpjId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
?>
    <div class="modal show" id="previewLPJModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail LPJ</h2>
                <a href="appointments.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="lpj-document">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <img src="assets/logowkp.jpg" alt="Logo WKP" style="height: 50px; border-radius: 4px;">
                    <div style="text-align: right;">
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: bold; color: var(--primary);">PT. WIJAYA KUSUMA PERDANA</h3>
                        <p style="margin: 0; font-size: 0.75rem; color: #555;">General Contractor & Developer</p>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h4 style="margin: 0; font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">LAPORAN PERTANGGUNGJAWABAN (LPJ) PENUTUPAN PROYEK</h4>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555;">No. Dokumen: <?= htmlspecialchars($item['code']) ?></p>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 0.9rem;">
                    <tr>
                        <td style="width: 30%; font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Nama Proyek</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['projectName']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Pemilik Proyek (Owner)</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['owner']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Ref. Penunjukan (ST)</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['appointmentCode']) ?> (Tgl: <?= htmlspecialchars($item['appointmentDate']) ?>)</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Main Contractor</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['mainCon']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Vendor Supplier</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['vendor']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Tanggal Laporan Penutupan</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['closingReportDate']) ?></td>
                    </tr>
                </table>

                <div style="display: flex; justify-content: space-between; margin-top: 50px; font-size: 0.85rem;">
                    <div style="text-align: center; width: 45%;">
                        <p style="margin-bottom: 60px;">Dibuat Oleh (Main Con),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 10px;"><?= htmlspecialchars($item['mainCon']) ?></p>
                        <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Kontraktor Utama</p>
                    </div>
                    <div style="text-align: center; width: 45%;">
                        <p style="margin-bottom: 60px;">Diterima Oleh (Owner),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 10px;"><?= htmlspecialchars($item['owner']) ?></p>
                        <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Pemilik Bangunan</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Dokumen
                </button>
                <a href="appointments.php" class="btn btn-secondary">Tutup</a>
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
