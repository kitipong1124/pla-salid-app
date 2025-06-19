<?php
// 1. ตั้งชื่อ Title และเรียกใช้ Header
$page_title = "แก้ไขข้อมูลผู้ใช้";
include 'templates/sidebar_header.php';

// --- 1. ตรวจสอบสิทธิ์และ ID ที่ส่งมา --
if ($userRole !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
    header("Location: index.php");
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่ได้ระบุ ID ผู้ใช้ที่ต้องการแก้ไข'];
    header("Location: add_user.php");
    exit();
}
$edit_user_id = $_GET['id'];

// --- 2. ดึงข้อมูลผู้ใช้เดิมเพื่อมาแสดงในฟอร์ม ---
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->execute([$edit_user_id]);
$user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_to_edit) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่พบผู้ใช้ ID: ' . $edit_user_id];
    header("Location: add_user.php");
    exit();
}

// --- 3. ดึงข้อมูลบ่อทั้งหมด และบ่อที่ผู้ใช้นี้ดูแลอยู่ ---
$all_ponds = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt_owned_ponds = $conn->prepare("SELECT pond_id FROM user_ponds WHERE user_id = ?");
$stmt_owned_ponds->execute([$edit_user_id]);
// ใช้ fetchAll(PDO::FETCH_COLUMN) เพื่อให้ได้ array ของ id ล้วนๆ เช่น [1, 5, 8]
$owned_pond_ids = $stmt_owned_ponds->fetchAll(PDO::FETCH_COLUMN);


// --- 4. จัดการการส่งฟอร์ม (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_role = $_POST['role'];
    $new_pond_ids = $_POST['pond_ids'] ?? [];

    $conn->beginTransaction();
    try {
        // 4.1 อัปเดต Role
        $stmt_update_role = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt_update_role->execute([$new_role, $edit_user_id]);

        // 4.2 อัปเดตรหัสผ่าน (ถ้ามีการกรอก)
        if (!empty($_POST['password'])) {
            $new_hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt_update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_update_pass->execute([$new_hashed_password, $edit_user_id]);
        }
        
        // 4.3 อัปเดตบ่อที่ดูแล (ลบของเก่าทั้งหมด แล้วเพิ่มของใหม่)
        // ลบของเก่าก่อน
        $stmt_delete_ponds = $conn->prepare("DELETE FROM user_ponds WHERE user_id = ?");
        $stmt_delete_ponds->execute([$edit_user_id]);

        // เพิ่มของใหม่เข้าไป ถ้าเป็น Owner และมีการเลือกบ่อ
        if ($new_role === 'owner' && !empty($new_pond_ids)) {
            $stmt_insert_ponds = $conn->prepare("INSERT INTO user_ponds (user_id, pond_id) VALUES (?, ?)");
            foreach ($new_pond_ids as $pond_id) {
                $stmt_insert_ponds->execute([$edit_user_id, $pond_id]);
            }
        }
        
        // ถ้าทุกอย่างสำเร็จ
        $conn->commit();
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'อัปเดตข้อมูลผู้ใช้สำเร็จ!'];
        header("Location: add_user.php");
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        header("Location: edit_user.php?id=" . $edit_user_id);
        exit();
    }
}

?>
<h1 class="gradient-text fw-bolder"><i class="bi bi-person-gear"></i> แก้ไขข้อมูลการปล่อยลูกปลา</h1>
<p class="lead">คุณกำลังแก้ไขข้อมูลของ: <strong><?= htmlspecialchars($user_to_edit['username']) ?></strong></p>
<hr>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user_to_edit['username']) ?>" disabled readonly>
                        <div class="form-text">ไม่สามารถแก้ไขชื่อผู้ใช้ได้</div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="กรอกเพื่อเปลี่ยนรหัสผ่าน (ถ้าไม่ต้องการเปลี่ยนให้เว้นว่าง)">
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">สิทธิ์การเข้าถึง (Role)</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="owner" <?= ($user_to_edit['role'] === 'owner') ? 'selected' : '' ?>>Owner (เจ้าของบ่อ)</option>
                            <option value="admin" <?= ($user_to_edit['role'] === 'admin') ? 'selected' : '' ?>>Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="pond-selection-div">
                        <label for="pond_ids" class="form-label">บ่อที่ดูแล (สำหรับ Owner)</label>
                        <p class="small text-muted">กด Ctrl/Cmd ค้างไว้เพื่อเลือกหลายรายการ</p>
                        <select name="pond_ids[]" id="pond_ids" class="form-select" multiple style="height: 200px;">
                            <?php foreach ($all_ponds as $pond): ?>
                                <?php // ตรวจสอบว่า pond id นี้อยู่ในรายการที่ user ดูแลหรือไม่ เพื่อทำ selected ?>
                                <option value="<?= $pond['id'] ?>" <?= in_array($pond['id'], $owned_pond_ids) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pond['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="add_user.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> ยกเลิก</a>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-save-fill"></i> บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const pondSelectionDiv = document.getElementById('pond-selection-div');

    function togglePondSelection() {
        pondSelectionDiv.style.display = (roleSelect.value === 'admin') ? 'none' : 'block';
    }
    
    // เรียกใช้ตอนโหลดหน้า
    togglePondSelection();
    // เรียกใช้เมื่อมีการเปลี่ยนแปลง
    roleSelect.addEventListener('change', togglePondSelection);
});
</script>
<?php
include 'templates/sidebar_footer.php';
?>