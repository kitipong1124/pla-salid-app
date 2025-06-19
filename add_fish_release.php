<?php
// 1. ตั้งชื่อ Title ของหน้า
$page_title = "บันทึกข้อมูลการปล่อยลูกปลา";

// 2. เรียกใช้ Header ตัวใหม่ที่มี Sidebar
include 'templates/sidebar_header.php';

// --- การตรวจสอบสิทธิ์ ---
if ($userRole !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้'];
    header("Location: index.php");
    exit();
}
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM fish_releases WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'ลบข้อมูลสำเร็จ!'];
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage()];
    }
    header('Location: add_fish_release.php');
    exit();
}

// --- การประมวลผลฟอร์มเมื่อมีการส่งข้อมูล (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pond_id = $_POST['pond_id'];
    $release_date = $_POST['release_date'];
    $fish_amount = $_POST['fish_amount'];
    // *** ส่วนที่เพิ่มเข้ามา: รับค่า total_cost จากฟอร์ม ***
    $total_cost = $_POST['total_cost'];

    if (!empty($pond_id) && !empty($release_date) && !empty($fish_amount) && $fish_amount > 0 && isset($total_cost)) {
        try {
            // *** ส่วนที่แก้ไข: เพิ่ม total_cost เข้าไปในคำสั่ง INSERT ***
            $stmt = $conn->prepare("INSERT INTO fish_releases (pond_id, release_date, fish_amount, total_cost) VALUES (?, ?, ?, ?)");
            $stmt->execute([$pond_id, $release_date, $fish_amount, $total_cost]);
    
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'บันทึกข้อมูลการปล่อยลูกปลาสำเร็จ!'];
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง'];
    }
    
    header("Location: add_fish_release.php");
    exit();
}

// --- ดึงข้อมูลสำหรับใช้ในฟอร์ม และตาราง ---
$ponds_for_form = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// *** ส่วนที่แก้ไข: เพิ่ม total_cost เข้ามาใน SELECT เพื่อแสดงในตาราง ***
$stmt_records = $conn->query("
    SELECT fr.*, p.name as pond_name 
    FROM fish_releases fr
    JOIN ponds p ON fr.pond_id = p.id
    ORDER BY fr.release_date DESC, fr.id DESC
");
$release_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="bi bi-box-arrow-in-down"></i> บันทึกข้อมูลการปล่อยลูกปลา</h4>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="pond_id" class="form-label">เลือกบ่อที่จะปล่อยปลา</label>
                        <select name="pond_id" id="pond_id" class="form-select" required>
                            <option value="" disabled selected>-- กรุณาเลือกบ่อ --</option>
                            <?php foreach ($ponds_for_form as $pond): ?>
                                <option value="<?= $pond['id'] ?>"><?= htmlspecialchars($pond['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="release_date" class="form-label">วันที่ปล่อยปลา</label>
                            <input type="date" name="release_date" id="release_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="fish_amount" class="form-label">จำนวนลูกปลา (ตัว)</label>
                            <input type="number" name="fish_amount" id="fish_amount" class="form-control" placeholder="เช่น 50000" required min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="total_cost" class="form-label">ต้นทุนค่าลูกปลารวม (บาท)</label>
                            <input type="number" step="0.01" name="total_cost" id="total_cost" class="form-control" placeholder="เช่น 50000.00" required min="0">
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill"></i> บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">ประวัติการปล่อยลูกปลา</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light"><tr class="text-center"><th>วันที่ปล่อย</th><th>ชื่อบ่อ</th><th class="text-end">จำนวน (ตัว)</th><th class="text-end">ต้นทุน (บาท)</th><th>จัดการ</th></tr></thead>
                        <tbody>
                            <?php if(!empty($release_records)): foreach ($release_records as $rec): ?>
                                <tr>
                                    <td class="text-center"><?=htmlspecialchars($rec['release_date'])?></td>
                                    <td><?=htmlspecialchars($rec['pond_name'])?></td>
                                    <td class="text-end"><?=number_format($rec['fish_amount'])?></td>
                                    <td class="text-end"><?=number_format($rec['total_cost'], 2)?></td>
                                    <td class="text-center">
                                        <a href="edit_fish_release.php?id=<?=$rec['id']?>" class="btn btn-warning btn-sm" title="แก้ไข"><i class="bi bi-pencil-fill"></i></a>
                                        <a href="add_fish_release.php?delete_id=<?=$rec['id']?>" class="btn btn-danger btn-sm" title="ลบ" onclick="return confirm('คุณแน่ใจว่าต้องการลบข้อมูลนี้?')"><i class="bi bi-trash-fill"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" class="text-center text-muted p-3">ยังไม่มีข้อมูล</td></tr>
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
