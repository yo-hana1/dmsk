<?php
// e:\dmsk 29-4-26\master-users.php
require_once __DIR__ . '/layout_header.php';
require_once __DIR__ . '/Database.php';

$db = Database::getConnection();

// --- 1. Aksi Tambah / Edit User ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $type = $_POST['type'] ?? '';
    $name = $_POST['name'] ?? '';
    $position = $_POST['position'] ?? '';
    $company = $_POST['company'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $status = $_POST['status'] ?? 'Aktif';

    try {
        if ($_POST['action'] === 'add') {
            $stmt = $db->prepare("INSERT INTO master_users (type, name, position, company, contact, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $name, $position, $company, $contact, $status]);
        } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE master_users SET type = ?, name = ?, position = ?, company = ?, contact = ?, status = ? WHERE id = ?");
            $stmt->execute([$type, $name, $position, $company, $contact, $status, $id]);
        }
        header("Location: master-users.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal memproses data user: " . $e->getMessage();
    }
}

// --- 2. Aksi Soft-Delete ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->prepare("UPDATE master_users SET isDeleted = 1, deletedAt = datetime('now') WHERE id = ?")->execute([$id]);
        header("Location: master-users.php");
        exit;
    } catch (PDOException $e) {
        $errorMsg = "Gagal menghapus user: " . $e->getMessage();
    }
}

// --- 3. Filter Parameter ---
$filterType = $_GET['filterType'] ?? 'All';
$search = $_GET['search'] ?? '';

// Build Query
$sql = "SELECT * FROM master_users WHERE isDeleted = 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR company LIKE ? OR position LIKE ?)";
    $searchWildcard = "%$search%";
    $params = [$searchWildcard, $searchWildcard, $searchWildcard];
}
if ($filterType !== 'All') {
    $sql .= " AND type = ?";
    $params[] = $filterType;
}
$sql .= " ORDER BY id DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usersList = [];
    $errorMsg = "Gagal memuat user: " . $e->getMessage();
}

$userTypes = ['Internal', 'Main Contractor', 'Sub Contractor', 'Vendor'];
?>

<?php if (isset($errorMsg)): ?>
    <div class="card" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.2); color: var(--danger); font-size: 0.9rem;">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Filter & Add Button -->
<div class="card" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
    <form method="GET" action="master-users.php" id="filterForm" style="display: flex; align-items: center; gap: 10px; margin: 0;">
        <select name="filterType" onchange="document.getElementById('filterForm').submit()" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ced4da; font-size: 0.85rem; min-width: 140px;">
            <option value="All">Semua Tipe</option>
            <?php foreach ($userTypes as $type): ?>
                <option value="<?= $type ?>" <?= ($filterType === $type) ? 'selected' : '' ?>><?= $type ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filterType !== 'All' || !empty($search)): ?>
            <a href="master-users.php" style="font-size: 0.8rem; color: var(--primary); font-weight: 500;">Reset</a>
        <?php endif; ?>
    </form>
    <button class="btn btn-primary" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Tambah User
    </button>
</div>

<!-- Grouped List Display -->
<div style="display: flex; flex-direction: column; gap: 30px;">
    <?php foreach ($userTypes as $type): 
        if ($filterType !== 'All' && $filterType !== $type) continue;
        
        // Filter list untuk tipe ini
        $typedUsers = array_filter($usersList, function($u) use ($type) {
            return $u['type'] === $type;
        });
    ?>
        <div>
            <h3 style="margin-bottom: 12px; font-size: 1.05rem; display: flex; align-items: center; gap: 8px; color: var(--secondary);">
                <span style="width: 4px; height: 18px; background-color: var(--primary); border-radius: 2px; display: inline-block;"></span>
                Daftar <?= htmlspecialchars($type) ?>
            </h3>
            <div class="card" style="padding: 0; overflow: hidden;">
                <div class="table-container">
                    <table class="table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Nama Lengkap</th>
                                <th style="width: 20%;">Jabatan</th>
                                <th style="width: 20%;">Perusahaan</th>
                                <th style="width: 15%;">Kontak</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 10%; text-align: center;" class="no-print">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($typedUsers) > 0): ?>
                                <?php foreach ($typedUsers as $user): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: #2d3748;"><?= htmlspecialchars($user['name']) ?></td>
                                        <td><?= htmlspecialchars($user['position'] ?: '-') ?></td>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($user['company'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($user['contact'] ?: '-') ?></td>
                                        <td>
                                            <span class="badge <?= ($user['status'] === 'Aktif') ? 'badge-success' : 'badge-danger' ?>">
                                                <?= htmlspecialchars($user['status']) ?>
                                            </span>
                                        </td>
                                        <td class="no-print">
                                            <div style="display: flex; justify-content: center; gap: 10px;">
                                                <button class="btn btn-outline btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($user)) ?>)" title="Edit">
                                                    <i class="bi bi-pencil-square" style="color: #007bff;"></i>
                                                </button>
                                                <a href="master-users.php?action=delete&id=<?= $user['id'] ?>" class="btn btn-outline btn-sm" title="Hapus" onclick="return confirm('Hapus user ini?')">
                                                    <i class="bi bi-trash3" style="color: var(--danger);"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">Data <?= htmlspecialchars($type) ?> kosong.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- MODAL TAMBAH / EDIT USER -->
<div class="modal" id="userModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Tambah User Baru</h2>
            <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
        </div>
        <form method="POST" action="master-users.php" id="userForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="userId">
            
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group">
                    <label>Tipe User <span style="color:red;">*</span></label>
                    <select name="type" id="userType" required class="form-control">
                        <option value="">-- Pilih Tipe --</option>
                        <?php foreach ($userTypes as $type): ?>
                            <option value="<?= $type ?>"><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="name" id="userName" placeholder="Nama Lengkap" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Jabatan</label>
                    <input type="text" name="position" id="userPosition" placeholder="Jabatan" class="form-control">
                </div>
                <div class="form-group">
                    <label>Perusahaan</label>
                    <input type="text" name="company" id="userCompany" placeholder="Nama Perusahaan" class="form-control">
                </div>
                <div class="form-group">
                    <label>Kontak</label>
                    <input type="text" name="contact" id="userContact" placeholder="Email atau No. Telp" class="form-control">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="userStatus" class="form-control">
                        <option value="Aktif">Aktif</option>
                        <option value="Tidak Aktif">Tidak Aktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('userModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Tambah User Baru';
        document.getElementById('formAction').value = 'add';
        document.getElementById('userId').value = '';
        document.getElementById('userForm').reset();
        openModal('userModal');
    }

    function openEditModal(user) {
        document.getElementById('modalTitle').innerText = 'Edit User';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('userId').value = user.id;
        
        document.getElementById('userType').value = user.type;
        document.getElementById('userName').value = user.name;
        document.getElementById('userPosition').value = user.position;
        document.getElementById('userCompany').value = user.company;
        document.getElementById('userContact').value = user.contact;
        document.getElementById('userStatus').value = user.status;
        
        openModal('userModal');
    }
</script>

<?php
require_once __DIR__ . '/layout_footer.php';
?>
