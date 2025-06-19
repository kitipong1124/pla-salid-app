<?php
// 1. ตั้งชื่อ Title ของหน้า
$page_title = "บันทึกและดูข้อมูลสภาพน้ำ";

// 2. เรียกใช้ Header ตัวใหม่ที่มี Sidebar
include 'templates/sidebar_header.php';

// --- ส่วนจัดการ Logic ---
// *** ส่วนที่เพิ่มเข้ามา: จัดการการลบข้อมูล (สำหรับ Admin) ***
if (isset($_GET['delete_id']) && $userRole === 'admin') {
    $deleteId = $_GET['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM water_quality WHERE id = ?");
        $stmt->execute([$deleteId]);
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'ลบข้อมูลสำเร็จ!'];
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()];
    }
    // Redirect เพื่อล้างค่า GET ออกจาก URL
    header("Location: water_Q.php");
    exit();
}
// 1. จัดการการส่งฟอร์มบันทึกข้อมูล (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pond_id = $_POST['pond_id'];
    $check_date = $_POST['check_date'];
    $ph = $_POST['ph'];
    $ammonium = $_POST['ammonium'];
    $nitrite = $_POST['nitrite'];

    if (!empty($pond_id) && !empty($check_date) && isset($ph) && isset($ammonium) && isset($nitrite)) {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO water_quality (pond_id, check_date, ph, ammonium, nitrite) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$pond_id, $check_date, $ph, $ammonium, $nitrite]);
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'บันทึกข้อมูลสภาพน้ำสำเร็จ!'];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง'];
    }
    header("Location: water_Q.php");
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

// *** ส่วนที่เพิ่มเข้ามา: 3. รับค่าจากฟอร์มกรองและสร้างเงื่อนไข SQL ***
$filter_pond_id = $_GET['pond_filter'] ?? 'all';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

$where_conditions = [];
$params = [];

// กำหนดเงื่อนไขพื้นฐานตามสิทธิ์ผู้ใช้
if ($userRole === 'owner') {
    $owned_ponds = $_SESSION['owned_ponds'] ?? [];
    if (!empty($owned_ponds)) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds), '?'));
        $where_conditions[] = "w.pond_id IN ($placeholders)";
        $params = array_merge($params, array_values($owned_ponds));
    } else {
        $where_conditions[] = "1=0"; 
    }
}

// เพิ่มเงื่อนไขจากฟอร์มกรอง
if ($filter_pond_id !== 'all' && is_numeric($filter_pond_id)) {
    if ($userRole === 'admin' || in_array($filter_pond_id, $_SESSION['owned_ponds'] ?? [])) {
        $where_conditions[] = "w.pond_id = ?";
        $params[] = $filter_pond_id;
    }
}
if (!empty($filter_start_date)) {
    $where_conditions[] = "w.check_date >= ?";
    $params[] = $filter_start_date;
}
if (!empty($filter_end_date)) {
    $where_conditions[] = "w.check_date <= ?";
    $params[] = $filter_end_date;
}

// สร้าง WHERE clause สุดท้าย
$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// 4. ดึงข้อมูลประวัติการบันทึกโดยใช้เงื่อนไขที่สร้างขึ้น
$sql_records = "
    SELECT w.*, p.name AS pond_name 
    FROM water_quality w
    JOIN ponds p ON w.pond_id = p.id 
    $where_sql
    ORDER BY w.check_date DESC, w.id DESC
";
$stmt_records = $conn->prepare($sql_records);
$stmt_records->execute($params);
$records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
    
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0"><i class="bi bi-pencil-alt"></i> บันทึกข้อมูลสภาพน้ำ</h4>
            </div>
            <div class="card-body">
                <?php if ($userRole === 'owner' && empty($ponds_for_form)): ?>
                    <div class="alert alert-warning">คุณยังไม่ได้รับมอบหมายให้ดูแลบ่อใดๆ กรุณาติดต่อผู้ดูแลระบบ</div>
                <?php else: ?>
                    <form method="post" action="water_Q.php">
                        <div class="mb-3">
                            <label for="pond_id" class="form-label">เลือกบ่อปลา</label>
                            <select name="pond_id" id="pond_id" class="form-select" required>
                                <option value="" disabled selected>-- กรุณาเลือกบ่อ --</option>
                                <?php foreach ($ponds_for_form as $pond): ?>
                                    <option value="<?= $pond['id'] ?>"><?= htmlspecialchars($pond['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="check_date" class="form-label">วันที่ตรวจวัด</label>
                            <input type="date" name="check_date" id="check_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label for="ph" class="form-label">ค่า pH</label><input type="number" step="0.1" name="ph" id="ph" class="form-control" placeholder="เช่น 7.5" required></div>
                            <div class="col-md-4 mb-3"><label for="ammonium" class="form-label">แอมโมเนีย (mg/L)</label><input type="number" step="0.01" name="ammonium" id="ammonium" class="form-control" placeholder="เช่น 0.25" required></div>
                            <div class="col-md-4 mb-3"><label for="nitrite" class="form-label">ไนไตรต์ (mg/L)</label><input type="number" step="0.01" name="nitrite" id="nitrite" class="form-control" placeholder="เช่น 0.1" required></div>
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
                <h5 class="mb-0"><i class="bi bi-table"></i> ประวัติการบันทึกสภาพน้ำ</h5>
            </div>
            <div class="card-body">
                <form method="get" class="row gx-3 gy-2 align-items-end mb-4 p-3 bg-light rounded border">
                    <div class="col-sm-12 col-md-4">
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
                    <div class="col-sm-6 col-md-3">
                        <label for="start_date" class="form-label fw-bold">ตั้งแต่วันที่:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($filter_start_date) ?>">
                    </div>
                    <div class="col-sm-6 col-md-3">
                         <label for="end_date" class="form-label fw-bold">ถึงวันที่:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($filter_end_date) ?>">
                    </div>
                    <div class="col-sm-12 col-md-2">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> กรอง</button>
                            <a href="view_feeding.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center">ล้าง</a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>วันที่ตรวจ</th>
                                <th>ชื่อบ่อ</th>
                                <th>pH</th>
                                <th>แอมโมเนีย</th>
                                <th>ไนไตรต์</th>
                                <?php if ($userRole === 'admin'): ?>
                                    <th>การจัดการ</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="text-center">
                            <?php if (count($records) > 0): ?>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['check_date']) ?></td>
                                        <td><?= htmlspecialchars($row['pond_name']) ?></td>
                                        <td><?= htmlspecialchars($row['ph']) ?></td>
                                        <td><?= htmlspecialchars($row['ammonium']) ?></td>
                                        <td><?= htmlspecialchars($row['nitrite']) ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                        <td>
                                            <a href="edit_water_quality.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm" title="แก้ไข">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <a href="water_Q.php?delete_id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" title="ลบ" onclick="return confirm('คุณแน่ใจว่าต้องการลบข้อมูลนี้?');">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= $userRole === 'admin' ? '6' : '5' ?>" class="text-center text-muted p-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>
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