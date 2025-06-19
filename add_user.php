<?php
// 1. ตั้งชื่อ Title ของหน้า
$page_title = "จัดการผู้ใช้งาน";

// 2. เรียกใช้ Header ตัวใหม่ที่มี Sidebar
include 'templates/sidebar_header.php';

// --- การตรวจสอบสิทธิ์ ---
// หน้านี้สำหรับ Admin เท่านั้น
if ($userRole !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
    header("Location: index.php");
    exit();
}

// --- ส่วนจัดการ Logic ---

// 1. จัดการการส่งฟอร์ม (ADD USER)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $pond_ids = $_POST['pond_ids'] ?? []; // รับค่าเป็น array

    // ตรวจสอบข้อมูล
    if (empty($username) || empty($password) || empty($role)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'กรุณากรอกชื่อผู้ใช้, รหัสผ่าน, และสิทธิ์ให้ครบถ้วน'];
        header('Location: add_user.php');
        exit();
    }

    // ตรวจสอบว่าชื่อผู้ใช้ซ้ำหรือไม่
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->execute([$username]);
    if ($stmt_check->fetch()) {
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว'];
        header('Location: add_user.php');
        exit();
    }

    // --- เริ่มการบันทึกข้อมูล (Transaction) ---
    $conn->beginTransaction();
    try {
        // 1. เพิ่มผู้ใช้ลงในตาราง users
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt_user = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt_user->execute([$username, $hashed_password, $role]);
        $new_user_id = $conn->lastInsertId();

        // 2. ถ้าเป็น owner และมีการเลือกบ่อ, ให้บันทึกลงใน user_ponds
        if ($role === 'owner' && !empty($pond_ids)) {
            $stmt_user_pond = $conn->prepare("INSERT INTO user_ponds (user_id, pond_id) VALUES (?, ?)");
            foreach ($pond_ids as $pond_id) {
                $stmt_user_pond->execute([$new_user_id, $pond_id]);
            }
        }

        // 3. ถ้าทุกอย่างสำเร็จ
        $conn->commit();
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'เพิ่มผู้ใช้ใหม่สำเร็จ!'];

    } catch (PDOException $e) {
        // 4. หากเกิดข้อผิดพลาด ให้ยกเลิกทั้งหมด
        $conn->rollBack();
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()];
    }

    header('Location: add_user.php');
    exit();
}

// --- ดึงข้อมูลสำหรับแสดงผล ---

// 2. ดึงข้อมูลบ่อทั้งหมดมาให้เลือกในฟอร์ม
$stmt_ponds = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC");
$all_ponds = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);

// 3. ดึงข้อมูลผู้ใช้ทั้งหมด พร้อมรายชื่อบ่อที่ดูแล
$stmt_users = $conn->query("
    SELECT 
        u.id, 
        u.username, 
        u.role,
        GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') AS managed_ponds
    FROM users u
    LEFT JOIN user_ponds up ON u.id = up.user_id
    LEFT JOIN ponds p ON up.pond_id = p.id
    GROUP BY u.id, u.username, u.role
    ORDER BY u.id ASC
");
$users_list = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

?>

<title>จัดการผู้ใช้งาน</title>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้ใหม่</h5>
            </div>
            <div class="card-body">
                <form method="post" action="add_user.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">รหัสผ่าน (Password)</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">สิทธิ์การเข้าถึง (Role)</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="owner" selected>Owner (เจ้าของบ่อ)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="pond-selection-div">
                        <label for="pond_ids" class="form-label">บ่อที่ดูแล (สำหรับ Owner)</label>
                        <p class="small text-muted">กด Ctrl/Cmd ค้างไว้เพื่อเลือกหลายรายการ</p>
                        <select name="pond_ids[]" id="pond_ids" class="form-select" multiple style="height: 150px;">
                            <?php foreach ($all_ponds as $pond): ?>
                                <option value="<?= $pond['id'] ?>"><?= htmlspecialchars($pond['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle-fill"></i> เพิ่มผู้ใช้</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people-fill"></i> รายชื่อผู้ใช้ในระบบ (<?= count($users_list) ?> คน)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>บ่อที่ดูแล</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users_list) > 0): ?>
                                <?php foreach ($users_list as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td>
                                            <span class="badge <?= $user['role'] === 'admin' ? 'bg-success' : 'bg-info' ?>">
                                                <?= htmlspecialchars($user['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($user['managed_ponds'] ?? '<em>(ไม่มี)</em>') ?></td>
                                        <td>
                                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil-fill"></i> แก้ไข</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted">ยังไม่มีผู้ใช้งานในระบบ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const pondSelectionDiv = document.getElementById('pond-selection-div');

    roleSelect.addEventListener('change', function() {
        if (this.value === 'admin') {
            pondSelectionDiv.style.display = 'none';
        } else {
            pondSelectionDiv.style.display = 'block';
        }
    });
});

</script>
<?php
include 'templates/sidebar_footer.php';
?>
