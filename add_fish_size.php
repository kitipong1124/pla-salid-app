<?php
// 1. ตั้งชื่อ Title ของหน้า
$page_title = "บันทึกและดูข้อมูลขนาดปลา";

// 2. เรียกใช้ Header ตัวใหม่ที่มี Sidebar
include 'templates/sidebar_header.php';

// --- ส่วนจัดการ Logic ---
// *** ส่วนที่เพิ่มเข้ามา: จัดการการลบข้อมูล (สำหรับ Admin) ***
if (isset($_GET['delete_id']) && $userRole === 'admin') {
    $deleteId = $_GET['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM fish_sizes WHERE id = ?");
        $stmt->execute([$deleteId]);
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'ลบข้อมูลสำเร็จ!'];
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage()];
    }
    // Redirect เพื่อล้างค่า GET ออกจาก URL
    header('Location: add_fish_size.php');
    exit();
}
// 1. จัดการการส่งฟอร์ม (ADD)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pond_id = $_POST['pond_id'];
    $record_date = $_POST['record_date'];
    $fish_count = $_POST['fish_count'];

    if (!empty($pond_id) && !empty($record_date) && isset($fish_count)) {
        try {
            $stmt = $conn->prepare("INSERT INTO fish_sizes (pond_id, record_date, fish_count) VALUES (?, ?, ?)");
            $stmt->execute([$pond_id, $record_date, $fish_count]);
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'บันทึกข้อมูลขนาดปลาสำเร็จ!'];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'กรุณากรอกข้อมูลให้ครบถ้วน'];
    }
    // Redirect กลับไปที่หน้าเดิมเพื่อป้องกันการส่งฟอร์มซ้ำ
    header('Location: add_fish_size.php');
    exit();
}

// --- ดึงข้อมูลสำหรับแสดงผล ---

// 2. ดึงข้อมูลบ่อสำหรับใช้ในฟอร์ม Dropdown ทั้งสองฟอร์ม
$ponds_for_form = [];
if ($userRole === 'admin') {
    $stmt_ponds = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC");
    $ponds_for_form = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);
} else { // Owner
    $owned_ponds = $_SESSION['owned_ponds'] ?? [];
    if (count($owned_ponds) > 0) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds), '?'));
        $stmt_ponds = $conn->prepare("SELECT id, name FROM ponds WHERE id IN ($placeholders) ORDER BY name ASC");
        $stmt_ponds->execute($owned_ponds);
        $ponds_for_form = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);
    }
}


// *** ส่วนที่แก้ไข: 3. รับค่าจากฟอร์มกรองและสร้างเงื่อนไข SQL ***
$filter_pond_id = $_GET['pond_filter'] ?? 'all';
$filter_month = $_GET['month_filter'] ?? '';

$where_conditions = [];
$params = [];

// กำหนดเงื่อนไขพื้นฐานตามสิทธิ์ผู้ใช้
if ($userRole === 'owner') {
    $owned_ponds = $_SESSION['owned_ponds'] ?? [];
    if (!empty($owned_ponds)) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds), '?'));
        $where_conditions[] = "r.pond_id IN ($placeholders)";
        $params = array_merge($params, array_values($owned_ponds));
    } else {
        $where_conditions[] = "1=0"; // ถ้า owner ไม่มีบ่อ, ไม่ต้องแสดงข้อมูลใดๆ
    }
}

// เพิ่มเงื่อนไขจากฟอร์มกรอง "บ่อ"
if ($filter_pond_id !== 'all' && is_numeric($filter_pond_id)) {
    if ($userRole === 'admin' || in_array($filter_pond_id, $_SESSION['owned_ponds'] ?? [])) {
        $where_conditions[] = "r.pond_id = ?";
        $params[] = $filter_pond_id;
    }
}

// เพิ่มเงื่อนไขจากฟอร์มกรอง "เดือน/ปี"
if (!empty($filter_month)) {
    $where_conditions[] = "DATE_FORMAT(r.record_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}

// สร้าง WHERE clause สุดท้าย
$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// 4. ดึงข้อมูลประวัติการบันทึกโดยใช้เงื่อนไขที่สร้างขึ้น
$sql_records = "
    SELECT r.*, p.name AS pond_name 
    FROM fish_sizes r
    JOIN ponds p ON r.pond_id = p.id 
    $where_sql
    ORDER BY r.record_date DESC, r.id DESC
";
$stmt_records = $conn->prepare($sql_records);
$stmt_records->execute($params);
$records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-rulers"></i> บันทึกการสุ่มขนาดปลา</h4>
            </div>
            <div class="card-body">
                <?php if ($userRole === 'owner' && empty($ponds_for_form)): ?>
                    <div class="alert alert-warning">คุณยังไม่ได้รับมอบหมายให้ดูแลบ่อใดๆ กรุณาติดต่อผู้ดูแลระบบ</div>
                <?php else: ?>
                    <form method="post" action="add_fish_size.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pond_id" class="form-label">เลือกบ่อปลา</label>
                                <select name="pond_id" id="pond_id" class="form-select" required>
                                    <option value="" disabled selected>-- กรุณาเลือกบ่อ --</option>
                                    <?php foreach ($ponds_for_form as $pond): ?>
                                        <option value="<?= $pond['id'] ?>"><?= htmlspecialchars($pond['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="record_date" class="form-label">วันที่สุ่มตัวอย่าง</label>
                                <input type="date" name="record_date" id="record_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="fish_count" class="form-label">จำนวนปลาต่อกิโลกรัม (ตัว/กก.)</label>
                            <input type="number" step="0.1" name="fish_count" id="fish_count" class="form-control" placeholder="เช่น 15" required>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill"></i> บันทึกข้อมูล</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-table"></i> ประวัติการบันทึก</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row gx-2 gy-2 align-items-center mb-4 p-3 bg-light rounded border">
                    <div class="col-sm-5">
                        <label for="pond_filter" class="form-label fw-bold">กรองตามบ่อ:</label>
                        <select name="pond_filter" id="pond_filter" class="form-select">
                            <option value="all">-- ทุกบ่อในความดูแล --</option>
                            <?php foreach ($ponds_for_form as $pond): ?>
                                <option value="<?= $pond['id'] ?>" <?= ($pond['id'] == $filter_pond_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pond['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label for="month_filter" class="form-label fw-bold">กรองตามเดือน/ปี:</label>
                        <input type="month" name="month_filter" id="month_filter" class="form-control"
                               value="<?= htmlspecialchars($filter_month) ?>">
                    </div>
                    <div class="col-sm-3 align-self-end">
                        <div class="d-grid d-sm-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> กรองข้อมูล</button>
                            <a href="add_fish_size.php" class="btn btn-outline-secondary">ล้าง</a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th>วันที่บันทึก</th>
                                <th>ชื่อบ่อ</th>
                                <th class="text-end">ขนาด (ตัว/กก.)</th>
                                <?php if ($userRole === 'admin'): ?>
                                    <th>การจัดการ</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($records) > 0): ?>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <td class="text-center"><?= htmlspecialchars($row['record_date']) ?></td>
                                        <td><?= htmlspecialchars($row['pond_name']) ?></td>
                                        <td class="text-end"><?= htmlspecialchars($row['fish_count']) ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                        <td class="text-center">
                                            <a href="edit_fish_size.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm" title="แก้ไข">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <a href="add_fish_size.php?delete_id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" title="ลบ" onclick="return confirm('คุณแน่ใจว่าต้องการลบข้อมูลนี้?');">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= $userRole === 'admin' ? '4' : '3' ?>" class="text-center text-muted p-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
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
