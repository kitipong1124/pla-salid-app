<?php
// 1. ตั้งชื่อ Title ของหน้า
$page_title = "ประวัติการให้อาหาร";

// 2. เรียกใช้ Header ที่มี Sidebar
include 'templates/sidebar_header.php';

// --- โค้ด PHP ทั้งหมดของคุณ (ส่วน Logic) ยังคงเหมือนเดิมทุกประการ ---
// --- การจัดการการลบข้อมูล (สำหรับ Admin) ---
if (isset($_GET['delete_id']) && $userRole === 'admin') {
    $deleteId = $_GET['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM feeding_records WHERE id = ?");
        $stmt->execute([$deleteId]);
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'ลบข้อมูลการให้อาหารสำเร็จ!'];
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage()];
    }
    header("Location: view_feeding.php");
    exit();
}

// --- ดึงข้อมูลพร้อม Filter ---
$ponds_for_form = [];
if ($userRole === 'admin') {
    $stmt_ponds = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC");
    $ponds_for_form = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);
} else { 
    $owned_ponds = $_SESSION['owned_ponds'] ?? [];
    if (count($owned_ponds) > 0) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds), '?'));
        $stmt_ponds = $conn->prepare("SELECT id, name FROM ponds WHERE id IN ($placeholders) ORDER BY name ASC");
        $stmt_ponds->execute($owned_ponds);
        $ponds_for_form = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);
    }
}

$filter_pond_id = $_GET['pond_filter'] ?? 'all';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$where_conditions = [];
$params = [];
if ($userRole === 'owner') {
    $owned_ponds = $_SESSION['owned_ponds'] ?? [];
    if (!empty($owned_ponds)) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds), '?'));
        $where_conditions[] = "fr.pond_id IN ($placeholders)";
        $params = array_merge($params, array_values($owned_ponds));
    } else { $where_conditions[] = "1=0"; }
}
if ($filter_pond_id !== 'all' && is_numeric($filter_pond_id)) {
    if ($userRole === 'admin' || in_array($filter_pond_id, $_SESSION['owned_ponds'] ?? [])) {
        $where_conditions[] = "fr.pond_id = ?";
        $params[] = $filter_pond_id;
    }
}
if (!empty($filter_start_date)) { $where_conditions[] = "fr.feed_date >= ?"; $params[] = $filter_start_date; }
if (!empty($filter_end_date)) { $where_conditions[] = "fr.feed_date <= ?"; $params[] = $filter_end_date; }
$where_sql = "";
if (!empty($where_conditions)) { $where_sql = "WHERE " . implode(" AND ", $where_conditions); }
$sql_records = "SELECT fr.*, p.name AS pond_name FROM feeding_records fr JOIN ponds p ON fr.pond_id = p.id $where_sql ORDER BY fr.feed_date DESC, fr.id DESC";
$stmt_records = $conn->prepare($sql_records);
$stmt_records->execute($params);
$records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">

        <div class="d-flex justify-content-end mb-3">
            <a href="add_feeding.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> เพิ่มข้อมูลใหม่</a>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="bi bi-card-list"></i> ประวัติการให้อาหารปลา</h4>
            </div>
            <div class="card-body">
                <form method="get" class="row gx-3 gy-2 align-items-center mb-4 p-3 bg-light rounded border">
                    <div class="col-sm-4">
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
                    <div class="col-sm-3">
                        <label for="start_date" class="form-label fw-bold">ตั้งแต่วันที่:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($filter_start_date) ?>">
                    </div>
                    <div class="col-sm-3">
                        <label for="end_date" class="form-label fw-bold">ถึงวันที่:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($filter_end_date) ?>">
                    </div>
                    <div class="col-sm-2 align-self-end">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> กรอง</button>
                        </div>
                    </div>
                </form>

                <div class="text-end mb-3">
                    <a href="view_feeding.php" class="btn btn-outline-secondary btn-sm" title="ล้างค่าการกรอง">ล้างตัวกรอง</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>#</th>
                                <th>บ่อปลา</th>
                                <th>วันที่ให้อาหาร</th>
                                <th>ประเภทอาหาร</th>
                                <th>ปริมาณ (กระสอบ)</th>
                                <?php if ($userRole === 'admin'): ?>
                                    <th>การจัดการ</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="text-center">
                            <?php if (count($records) > 0): ?>
                                <?php foreach ($records as $index => $row): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($row['pond_name']) ?></td>
                                        <td><?= htmlspecialchars($row['feed_date']) ?></td>
                                        <td><?= htmlspecialchars($row['feed_type']) ?></td>
                                        <td class="text-end"><?= htmlspecialchars(number_format($row['feed_amount_sacks'], 1)) ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                            <td>
                                                <a href="edit_feeding.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil-fill"></i> แก้ไข</a>
                                                <a href="view_feeding.php?delete_id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?');"><i class="bi bi-trash-fill"></i> ลบ</a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= $userRole === 'admin' ? '6' : '5' ?>" class="text-muted text-center p-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
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

