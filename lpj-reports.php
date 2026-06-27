<?php
// e:\dmsk 29-4-26\lpj-reports.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Tambah LPJ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $appointmentId = (int)$_POST['appointmentId'];
    $closingReportDate = $_POST['closingReportDate'] ?? '';

    try {
        // Ambil detail serah terima terkait
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
            
            header("Location: lpj-reports.php");
            exit;
        } else {
            $errorMsg = "Referensi penunjukan serah terima tidak valid.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Gagal menyimpan LPJ: " . $e->getMessage();
    }
}

// --- 2. Aksi Hapus ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->prepare("UPDATE lpj_reports SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        header("Location: lpj-reports.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus LPJ: " . $e->getMessage();
    }
}

// --- 3. Load Data & Filter Pencarian ---
$search = $_GET['search'] ?? '';
$searchWildcard = "%$search%";

try {
    $lpjSql = "SELECT l.*, a.code as appointmentCode FROM lpj_reports l JOIN appointments a ON l.appointmentId = a.id WHERE l.isDeleted = 0 AND a.isDeleted = 0";
    $lpjParams = [];

    if (!empty($search)) {
        $lpjSql .= " AND (l.code LIKE ? OR l.projectName LIKE ? OR l.owner LIKE ? OR a.code LIKE ?)";
        $lpjParams = array_fill(0, 4, $searchWildcard);
    }
    $lpjSql .= " ORDER BY l.id DESC";
    $stmtLpj = $db->prepare($lpjSql);
    $stmtLpj->execute($lpjParams);
    $lpjList = $stmtLpj->fetchAll(PDO::FETCH_ASSOC);

    // Ambil semua Serah Terima (ST) untuk dropdown form
    $eligibleAppointments = $db->query("
        SELECT a.*, p.name as projectName 
        FROM appointments a 
        JOIN projects p ON a.projectId = p.id 
        WHERE a.isDeleted = 0 
          AND p.isDeleted = 0
        ORDER BY a.code DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lpjList = [];
    $eligibleAppointments = [];
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
    <h2 class="card-title" style="margin:0;"><i class="bi bi-pie-chart" style="color: var(--primary);"></i> Laporan Penutupan (LPJ)</h2>
    <button class="btn btn-primary" onclick="openModal('addLPJModal')">
        <i class="bi bi-plus-lg"></i> Input LPJ Baru
    </button>
</div>

<!-- Table Data LPJ -->
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
                            <td style="font-weight: 600;"><?= htmlspecialchars($lpj['projectName']) ?></td>
                            <td><?= htmlspecialchars($lpj['owner']) ?></td>
                            <td><?= htmlspecialchars($lpj['closingReportDate']) ?></td>
                            <td class="no-print">
                                <div style="display: flex; gap: 10px;">
                                    <a href="lpj-reports.php?preview_id=<?= $lpj['id'] ?>" class="btn btn-outline btn-sm" title="Preview Dokumen LPJ">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <a href="lpj-reports.php?action=delete&id=<?= $lpj['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen LPJ ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">Belum ada laporan pertanggungjawaban (LPJ) proyek yang di-input.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL INPUT LPJ -->
<div class="modal" id="addLPJModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Form Laporan Pertanggungjawaban (LPJ)</h2>
            <button class="modal-close" onclick="closeModal('addLPJModal')">&times;</button>
        </div>
        <form method="POST" action="lpj-reports.php">
            <input type="hidden" name="action" value="add">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group">
                    <label>Referensi Serah Terima (ST)</label>
                    <select name="appointmentId" required class="form-control">
                        <option value="">-- Pilih Rujukan ST --</option>
                        <?php foreach ($eligibleAppointments as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['code']) ?> - <?= htmlspecialchars($st['projectName']) ?></option>
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

<!-- MODAL PREVIEW DETAIL DOKUMEN LPJ -->
<?php
if (isset($_GET['preview_id'])):
    $previewLpjId = (int)$_GET['preview_id'];
    $stmtP = $db->prepare("
        SELECT l.*, a.code as appointmentCode, a.date as appointmentDate, a.mainCon as mainCon, a.vendor as vendor 
        FROM lpj_reports l 
        JOIN appointments a ON l.appointmentId = a.id 
        WHERE l.id = ?
    ");
    $stmtP->execute([$previewLpjId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
?>
    <div class="modal show" id="previewLPJModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail LPJ</h2>
                <a href="lpj-reports.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="lpj-document" style="font-family: serif; position: relative;">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <img src="assets/logowkp.jpg" alt="Logo WKP" style="height: 50px; border-radius: 4px; margin-right: 20px;">
                    <div style="text-align: right;">
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: bold; color: var(--primary);">PT. WIJAYA KUSUMA PERDANA</h3>
                        <p style="margin: 0; font-size: 0.75rem; color: #555;">General Contractor & Developer</p>
                    </div>
                </div>

                <div style="text-align: center; margin-bottom: 30px;">
                    <h4 style="margin: 0; font-size: 1.35rem; font-weight: bold; text-decoration: underline; letter-spacing: 1px;">LAPORAN PERTANGGUNGJAWABAN (LPJ) PENUTUPAN PROYEK</h4>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555; font-family: sans-serif;">No. Dokumen: <?= htmlspecialchars($item['code']) ?></p>
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

                <div style="display: flex; justify-content: space-between; margin-top: 50px; font-size: 0.85rem; font-family: sans-serif;">
                    <div style="text-align: center; width: 45%;">
                        <p style="margin-bottom: 60px;">Dibuat Oleh (Main Con),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;"><?= htmlspecialchars($item['mainCon']) ?></p>
                        <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Kontraktor Utama</p>
                    </div>
                    <div style="text-align: center; width: 45%;">
                        <p style="margin-bottom: 60px;">Diterima Oleh (Owner),</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;"><?= htmlspecialchars($item['owner']) ?></p>
                        <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Pemilik Bangunan</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak LPJ
                </button>
                <a href="lpj-reports.php" class="btn btn-secondary">Tutup</a>
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
