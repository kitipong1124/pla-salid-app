<?php
// 1. ตั้งชื่อ Title ของหน้า
$page_title = "แก้ไขข้อมูลการให้อาหาร";

// 2. เรียกใช้ Header ตัวใหม่ที่มี Sidebar
include 'templates/sidebar_header.php';

// --- การตรวจสอบสิทธิ์ และข้อมูลเบื้องต้น ---
// หน้านี้สำหรับ Admin เท่านั้น
if ($userRole !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
    header("Location: index.php");
    exit();
}

// ตรวจสอบว่ามี id ของรายการที่ต้องการแก้ไขส่งมาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่พบข้อมูลที่ต้องการแก้ไข'];
    header("Location: view_feeding.php");
    exit();
}

$record_id = $_GET['id'];

// --- ส่วนจัดการ Logic ---

// 1. ดึงข้อมูลเดิมของรายการนี้จากฐานข้อมูล
$stmt = $conn->prepare("SELECT * FROM feeding_records WHERE id = ?");
$stmt->execute([$record_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

// ถ้าไม่พบข้อมูลของ id ที่ส่งมา
if (!$record) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่พบข้อมูลการให้อาหาร ID: ' . $record_id];
    header("Location: view_feeding.php");
    exit();
}

// 2. ดึงข้อมูลบ่อทั้งหมดสำหรับสร้าง Dropdown
$stmt_ponds = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC");
$all_ponds = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);


// 3. จัดการการส่งฟอร์ม (UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pond_id = $_POST['pond_id'];
    $feed_date = $_POST['feed_date'];
    $feed_type = $_POST['feed_type'];
    $feed_amount_sacks = $_POST['feed_amount_sacks'];

    try {
        $stmt_update = $conn->prepare("UPDATE feeding_records 
                                       SET pond_id = ?, feed_date = ?, feed_type = ?, feed_amount_sacks = ? 
                                       WHERE id = ?");
        $stmt_update->execute([$pond_id, $feed_date, $feed_type, $feed_amount_sacks, $record_id]);

        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'อัปเดตข้อมูลสำเร็จ!'];
        // เมื่อสำเร็จ ให้กลับไปหน้าดูประวัติ
        header("Location: view_feeding.php");
        exit();

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการอัปเดต: ' . $e->getMessage()];
        // หากเกิด Error ให้โหลดหน้าเดิมอีกครั้งเพื่อแสดงข้อความ
        header("Location: edit_feeding.php?id=" . $record_id);
        exit();
    }
}
?>

<h1 class="gradient-text fw-bolder"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลการให้อาหาร</h1>
<p class="lead">แก้ไขรายการบันทึกการให้อาหาร ID: <?= htmlspecialchars($record_id) ?></p>
<hr class="mb-4">

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="pond_id" class="form-label">บ่อปลา</label>
                        <select name="pond_id" id="pond_id" class="form-select" required>
                            <option value="">-- กรุณาเลือกบ่อ --</option>
                            <?php foreach ($all_ponds as $pond): ?>
                                <option value="<?= $pond['id'] ?>" <?= ($pond['id'] == $record['pond_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pond['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="feed_date" class="form-label">วันที่ให้อาหาร</label>
                        <input type="date" name="feed_date" id="feed_date" class="form-control" 
                               value="<?= htmlspecialchars($record['feed_date']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="feed_type" class="form-label">ประเภทอาหาร</label>
                        <select name="feed_type" id="feed_type" class="form-control" required>
                            <option value="">-- กรุณาเลือกประเภทอาหาร --</option>
                            <?php 
                                $food_types = ["เบทาโกร 811", "เบทาโกร 812"];
                                foreach ($food_types as $type) {
                                    $selected = ($type == $record['feed_type']) ? 'selected' : '';
                                    echo "<option value=\"".htmlspecialchars($type)."\" $selected>".htmlspecialchars($type)."</option>";
                                }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="feed_amount_sacks" class="form-label">ปริมาณอาหาร (กระสอบ/ลูก)</label>
                        <input type="number" step="0.5" name="feed_amount_sacks" id="feed_amount_sacks" class="form-control" 
                               value="<?= htmlspecialchars($record['feed_amount_sacks']) ?>" required>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view_feeding.php" class="btn btn-secondary me-md-2">
                            <i class="bi bi-x-circle"></i> ยกเลิก
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save-fill"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
include 'templates/sidebar_footer.php';
?>
