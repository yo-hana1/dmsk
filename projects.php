<?php
// e:\dmsk 29-4-26\projects.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// 1. Aksi Soft-Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $db->prepare("UPDATE projects SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: projects.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus proyek: " . $e->getMessage();
    }
}

// 2. Aksi Simpan Proyek Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = $_POST['name'] ?? '';
    $owner = $_POST['owner'] ?? '';
    $type = $_POST['type'] ?? '';
    $location = $_POST['location'] ?? '';
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $details = $_POST['details'] ?? '';
    $pic = $_POST['pic'] ?? '';
    $status = 'Active';

    try {
        // Ambil ID berikutnya untuk penulisan kode unik
        $stmtSeq = $db->query("SELECT MAX(id) FROM projects");
        $nextId = (int)$stmtSeq->fetchColumn() + 1;
        $year = !empty($startDate) ? date('Y', strtotime($startDate)) : date('Y');
        $code = "PR/" . sprintf("%03d", $nextId) . "/" . $name . "/" . $year;

        $stmt = $db->prepare("INSERT INTO projects (code, name, type, location, startDate, endDate, status, details, owner, pic) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $type, $location, $startDate, $endDate, $status, $details, $owner, $pic]);
        
        header("Location: projects.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menambahkan proyek: " . $e->getMessage();
    }
}

// 3. Filter Parameter
$filterYear = $_GET['filterYear'] ?? '';
$filterType = $_GET['filterType'] ?? '';
$search = $_GET['search'] ?? '';

// Build Query
$sql = "SELECT * FROM projects WHERE isDeleted = 0";
$params = [];

if (!empty($filterYear)) {
    $sql .= " AND strftime('%Y', startDate) = ?";
    $params[] = $filterYear;
}
if (!empty($filterType)) {
    $sql .= " AND type = ?";
    $params[] = $filterType;
}
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR owner LIKE ? OR location LIKE ? OR code LIKE ?)";
    $searchWildcard = "%$search%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$sql .= " ORDER BY id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $projectsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projectsList = [];
    $errorMsg = "Gagal memuat proyek: " . $e->getMessage();
}

$projectTypes = ['Gedung', 'Rumah', 'Jembatan', 'Renovasi', 'Jalan', 'Lainnya'];
$picOptions = [
    'Budi Wirawan (Direktur PT. WKP)',
    'Anto Susilo (Manajer PT. WKP)',
    'Dedi Purnomo (Konsultan PT. WKP)'
];

// Helper Hitung Status
function getProjectStatus($p) {
    $today = date('Y-m-d');
    if (empty($p['startDate']) || empty($p['endDate'])) {
        return ['label' => 'Unknown', 'class' => 'badge-secondary'];
    }
    if ($today < $p['startDate']) {
        return ['label' => 'Waiting', 'class' => 'badge-warning'];
    } elseif ($today > $p['endDate']) {
        return ['label' => 'Done', 'class' => 'badge-danger'];
    } else {
        return ['label' => 'Active', 'class' => 'badge-success'];
    }
}
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Filter & Tambah Proyek -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; padding: 15px 20px;">
    <form method="GET" action="projects.php" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;" id="filterForm">
        <div style="display: flex; alignItems: center; gap: 8px; color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">
            <i class="bi bi-funnel"></i>
            <span>Filter:</span>
        </div>
        
        <select name="filterYear" onchange="document.getElementById('filterForm').submit()" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ced4da; font-size: 0.85rem; min-width: 130px;">
            <option value="">Semua Tahun</option>
            <?php
            for ($y = 2035; $y >= 2026; $y--) {
                $selected = ($filterYear == $y) ? 'selected' : '';
                echo "<option value='$y' $selected>$y</option>";
            }
            ?>
        </select>

        <select name="filterType" onchange="document.getElementById('filterForm').submit()" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ced4da; font-size: 0.85rem; min-width: 130px;">
            <option value="">Semua Jenis</option>
            <?php foreach ($projectTypes as $t): ?>
                <option value="<?= $t ?>" <?= ($filterType === $t) ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>

        <?php if (!empty($filterYear) || !empty($filterType) || !empty($search)): ?>
            <a href="projects.php" style="font-size: 0.8rem; color: var(--primary); font-weight: 500;">Reset</a>
        <?php endif; ?>
    </form>

    <button class="btn btn-primary" onclick="openModal('addProjectModal')">
        <i class="bi bi-plus-lg"></i> Tambah Proyek
    </button>
</div>

<!-- Table Data -->
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>No. Proyek</th>
                    <th>Nama Proyek</th>
                    <th>Pemilik</th>
                    <th>Jenis</th>
                    <th>Lokasi</th>
                    <th>Tgl Mulai</th>
                    <th>Status</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($projectsList) > 0): ?>
                    <?php foreach ($projectsList as $p): 
                        $status = getProjectStatus($p);
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: #4a5568;"><?= htmlspecialchars($p['code']) ?></td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['owner'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($p['type']) ?></td>
                            <td><?= htmlspecialchars($p['location']) ?></td>
                            <td><?= htmlspecialchars($p['startDate']) ?></td>
                            <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                            <td class="no-print">
                                <div style="display: flex; gap: 10px;">
                                    <a href="projects.php?preview_id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Preview Dokumen">
                                        <i class="bi bi-eye" style="color: #007bff;"></i>
                                    </a>
                                    <a href="projects.php?action=delete&id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus dokumen proyek ini?')">
                                        <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">Tidak ada proyek yang ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH PROYEK -->
<div class="modal" id="addProjectModal">
    <div class="modal-content wide">
        <div class="modal-header">
            <h2 class="modal-title">Tambah Proyek Baru</h2>
            <button class="modal-close" onclick="closeModal('addProjectModal')">&times;</button>
        </div>
        <form method="POST" action="projects.php">
            <input type="hidden" name="action" value="add">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Nama Proyek</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Pemilik (Owner)</label>
                    <input type="text" name="owner" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Jenis Pembangunan</label>
                    <select name="type" required class="form-control">
                        <option value="">Pilih Jenis</option>
                        <?php foreach ($projectTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lokasi</label>
                    <input type="text" name="location" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="startDate" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai</label>
                    <input type="date" name="endDate" required class="form-control">
                </div>
                <div class="form-group">
                    <label>PIC Proyek (PT. WKP)</label>
                    <select name="pic" required class="form-control">
                        <option value="">Pilih PIC</option>
                        <?php foreach ($picOptions as $pic): ?>
                            <option value="<?= $pic ?>"><?= $pic ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Detail Kebutuhan / Keterangan</label>
                    <textarea name="details" rows="3" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Proyek</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PREVIEW DETAIL DOKUMEN -->
<?php
if (isset($_GET['preview_id'])):
    $previewId = (int)$_GET['preview_id'];
    $stmtP = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmtP->execute([$previewId]);
    $item = $stmtP->fetch(PDO::FETCH_ASSOC);

    if ($item):
?>
    <div class="modal show" id="previewModal">
        <div class="modal-content wide">
            <div class="modal-header no-print">
                <h2 class="modal-title">Dokumen Detail Proyek</h2>
                <a href="projects.php" class="modal-close" style="font-size: 1.5rem;">&times;</a>
            </div>
            
            <div class="document-view" id="project-document">
                <!-- Header Dokumen Cetak -->
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <img src="assets/logowkp.jpg" alt="Logo WKP" style="height: 50px; border-radius: 4px;">
                    <div style="text-align: right;">
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: bold; color: var(--primary);">PT. WIJAYA KUSUMA PERDANA</h3>
                        <p style="margin: 0; font-size: 0.75rem; color: #555;">General Contractor & Developer</p>
                    </div>
                </div>

                <!-- Judul Dokumen -->
                <div style="text-align: center; margin-bottom: 30px;">
                    <h4 style="margin: 0; font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">KARTU DOKUMEN DATA PROYEK</h4>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; color: #555;">No. Register: <?= htmlspecialchars($item['code']) ?></p>
                </div>

                <!-- Rincian Dokumen -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                    <tr>
                        <td style="width: 30%; font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Nama Proyek</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['name']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Pemilik (Owner)</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['owner']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Jenis Pembangunan</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['type']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Lokasi Proyek</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['location']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">Durasi Proyek</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['startDate']) ?> s/d <?= htmlspecialchars($item['endDate']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee;">PIC Penanggung Jawab</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">: <?= htmlspecialchars($item['pic'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; padding: 8px 0; border-bottom: 1px solid #eee; vertical-align: top;">Detail Kebutuhan</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #eee; white-space: pre-line;">: <?= htmlspecialchars($item['details'] ?? '-') ?></td>
                    </tr>
                </table>

                <!-- Tanda Tangan Dokumen -->
                <div style="display: flex; justify-content: space-between; margin-top: 50px; font-size: 0.9rem;">
                    <div style="text-align: center; width: 40%;">
                        <p style="margin-bottom: 60px;">Disiapkan Oleh,</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;">Divisi Administrasi Proyek</p>
                        <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Staf Administrasi</p>
                    </div>
                    <div style="text-align: center; width: 40%;">
                        <p style="margin-bottom: 60px;">Disetujui Oleh,</p>
                        <p style="font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding: 0 15px;"><?= htmlspecialchars(explode(' (', $item['pic'])[0]) ?></p>
                        <p style="font-size: 0.75rem; color: #777; margin: 2px 0 0 0;">Penanggung Jawab Proyek</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer no-print">
                <button type="button" class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Cetak Dokumen
                </button>
                <a href="projects.php" class="btn btn-secondary">Tutup</a>
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
