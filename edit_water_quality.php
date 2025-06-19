<?php
// 1. ตั้งชื่อ Title และเรียกใช้ Header
$page_title = "แก้ไขข้อมูลคุณภาพน้ำ";
include 'templates/sidebar_header.php';

// --- ตรวจสอบสิทธิ์และ ID ที่ส่งมา ---
if ($userRole !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
    header("Location: index.php");
    exit();
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่ได้ระบุ ID ที่ต้องการแก้ไข'];
    header("Location: water_Q.php");
    exit();
}
$record_id = $_GET['id'];

// --- ดึงข้อมูลเดิมมาแสดงในฟอร์ม ---
$stmt = $conn->prepare("SELECT * FROM water_quality WHERE id = ?");
$stmt->execute([$record_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่พบข้อมูล ID: ' . $record_id];
    header("Location: water_Q.php");
    exit();
}

// ดึงข้อมูลบ่อทั้งหมดสำหรับ Dropdown
$ponds_for_form = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- จัดการการส่งฟอร์ม (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pond_id = $_POST['pond_id'];
    $check_date = $_POST['check_date'];
    $ph = $_POST['ph'];
    $ammonium = $_POST['ammonium'];
    $nitrite = $_POST['nitrite'];

    try {
        $stmt_update = $conn->prepare("UPDATE water_quality 
                                       SET pond_id = ?, check_date = ?, ph = ?, ammonium = ?, nitrite = ? 
                                       WHERE id = ?");
        $stmt_update->execute([$pond_id, $check_date, $ph, $ammonium, $nitrite, $record_id]);

        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'อัปเดตข้อมูลสำเร็จ!'];
        header("Location: water_Q.php"); // กลับไปหน้าแสดงประวัติ
        exit();
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการอัปเดต: ' . $e->getMessage()];
        header("Location: edit_water_quality.php?id=" . $record_id);
        exit();
    }
}
?>
<h1 class="gradient-text fw-bolder"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลสภาพน้ำ</h1>
<p class="lead">คุณกำลังแก้ไขรายการที่บันทึกไว้เมื่อวันที่: <?= htmlspecialchars($record_id) ?></p>
<hr class="mb-4">

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="pond_id" class="form-label">บ่อปลา</label>
                        <select name="pond_id" id="pond_id" class="form-select" required>
                            <?php foreach ($ponds_for_form as $pond): ?>
                                <option value="<?= $pond['id'] ?>" <?= ($pond['id'] == $record['pond_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pond['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="check_date" class="form-label">วันที่ตรวจวัด</label>
                        <input type="date" name="check_date" id="check_date" class="form-control" value="<?= htmlspecialchars($record['check_date']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label for="ph" class="form-label">ค่า pH</label><input type="number" step="0.1" name="ph" id="ph" class="form-control" value="<?= htmlspecialchars($record['ph']) ?>" required></div>
                        <div class="col-md-4 mb-3"><label for="ammonium" class="form-label">แอมโมเนีย (mg/L)</label><input type="number" step="0.01" name="ammonium" id="ammonium" class="form-control" value="<?= htmlspecialchars($record['ammonium']) ?>" required></div>
                        <div class="col-md-4 mb-3"><label for="nitrite" class="form-label">ไนไตรต์ (mg/L)</label><input type="number" step="0.01" name="nitrite" id="nitrite" class="form-control" value="<?= htmlspecialchars($record['nitrite']) ?>" required></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="water_Q.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> ยกเลิก</a>
                        <button type="submit" class="btn btn-warning"><i class="bi bi-save-fill"></i> บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
include 'templates/sidebar_footer.php';
?>