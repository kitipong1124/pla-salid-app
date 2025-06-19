<?php
// 1. ตั้งชื่อ Title ของหน้า
$page_title = "จัดการบ่อปลา";

// 2. เรียกใช้ Header ตัวใหม่ที่มี Sidebar
include 'templates/sidebar_header.php';

// --- การตรวจสอบสิทธิ์ ---
// หน้านี้สำหรับ Admin เท่านั้น
if ($userRole !== 'admin') {
    // หากไม่ใช่ Admin ให้ส่งกลับไปหน้าหลัก พร้อมข้อความแจ้งเตือน
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
    header("Location: index.php");
    exit();
}

// --- ตัวแปรเริ่มต้น ---
$editMode = false;
$editPond = ['id' => '', 'name' => '', 'size_rai' => '']; // กำหนดค่าเริ่มต้นเพื่อไม่ให้เกิด Error

// --- ส่วนจัดการ Logic (Delete, Edit, Add/Update) ---

// 1. จัดการการลบ (DELETE)
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM ponds WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'ลบบ่อสำเร็จ!'];
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage()];
    }
    header('Location: add_pond.php');
    exit();
}

// 2. จัดการการเข้าสู่โหมดแก้ไข (EDIT)
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editMode = true;
    $stmt = $conn->prepare("SELECT * FROM ponds WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editPond = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editPond) {
        // ถ้าไม่พบ ID ที่จะแก้ไข
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่พบข้อมูลบ่อที่ต้องการแก้ไข'];
        header('Location: add_pond.php');
        exit();
    }
}

// 3. จัดการการส่งฟอร์ม (ADD / UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $size = $_POST['size_rai'];
    $message = '';

    try {
        if (isset($_POST['pond_id']) && !empty($_POST['pond_id'])) {
            // โหมด Update
            $stmt = $conn->prepare("UPDATE ponds SET name = ?, size_rai = ? WHERE id = ?");
            $stmt->execute([$name, $size, $_POST['pond_id']]);
            $message = 'อัปเดตข้อมูลบ่อสำเร็จ!';
        } else {
            // โหมด Add
            $stmt = $conn->prepare("INSERT INTO ponds (name, size_rai) VALUES (?, ?)");
            $stmt->execute([$name, $size]);
            $message = 'เพิ่มบ่อใหม่สำเร็จ!';
        }
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => $message];
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header('Location: add_pond.php');
    exit();
}

// 4. ดึงข้อมูลบ่อทั้งหมดมาแสดงในตาราง (READ)
// ทำส่วนนี้หลังสุด เพื่อให้แสดงข้อมูลล่าสุดเสมอ
$stmt = $conn->query("SELECT * FROM ponds ORDER BY name ASC");
$ponds = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<title>จัดการบ่อปลา</title>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <?php if ($editMode): ?>
                        <i class="bi bi-pencil-square"></i> แก้ไขข้อมูลบ่อ
                    <?php else: ?>
                        <i class="bi bi-plus-lg"></i> เพิ่มบ่อใหม่
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="add_pond.php">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="pond_id" value="<?= htmlspecialchars($editPond['id']) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="name" class="form-label">ชื่อบ่อ</label>
                        <input type="text" name="name" id="name" class="form-control" required
                               value="<?= htmlspecialchars($editPond['name']) ?>" placeholder="เช่น บ่อ A1, บ่อลุงมี">
                    </div>
                    <div class="mb-3">
                        <label for="size_rai" class="form-label">ขนาดบ่อ (ไร่)</label>
                        <input type="number" step="0.01" name="size_rai" id="size_rai" class="form-control" required
                               value="<?= htmlspecialchars($editPond['size_rai']) ?>" placeholder="เช่น 1.5">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <?php if ($editMode): ?>
                                <i class="bi bi-save-fill"></i> บันทึกการแก้ไข
                            <?php else: ?>
                                <i class="bi bi-plus-circle-fill"></i> เพิ่มบ่อ
                            <?php endif; ?>
                        </button>
                        <?php if ($editMode): ?>
                            <a href="add_pond.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> ยกเลิกการแก้ไข</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
             <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> รายชื่อบ่อในระบบ (<?= count($ponds) ?> บ่อ)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>ชื่อบ่อ</th>
                                <th>ขนาด (ไร่)</th>
                                <th class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ponds) > 0): ?>
                                <?php foreach ($ponds as $index => $pond): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($pond['name']) ?></td>
                                        <td><?= htmlspecialchars($pond['size_rai']) ?></td>
                                        <td class="text-center">
                                            <a href="add_pond.php?edit=<?= $pond['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil-fill"></i> แก้ไข
                                            </a>
                                            <a href="add_pond.php?delete=<?= $pond['id'] ?>" class="btn btn-sm btn-danger"
                                               onclick="return confirm('คุณแน่ใจว่าต้องการลบบ่อ \'<?= htmlspecialchars($pond['name']) ?>\'? การกระทำนี้จะลบข้อมูลทั้งหมดที่เกี่ยวข้องกับบ่อนี้ด้วย')">
                                                <i class="bi bi-trash-fill"></i> ลบ
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">ยังไม่มีข้อมูลบ่อในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
include 'templates/sidebar_footer.php';
?>
