<?php
$page_title = "แก้ไขข้อมูลการปล่อยลูกปลา";
include 'templates/sidebar_header.php';

// --- ตรวจสอบสิทธิ์และ ID ---
if ($userRole !== 'admin') { $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้']; header("Location: index.php"); exit(); }
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่ได้ระบุ ID ที่ต้องการแก้ไข']; header("Location: add_fish_release.php"); exit(); }
$record_id = $_GET['id'];

// --- ดึงข้อมูลเดิม ---
$stmt = $conn->prepare("SELECT * FROM fish_releases WHERE id = ?");
$stmt->execute([$record_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$record) { $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่พบข้อมูล ID: ' . $record_id]; header("Location: add_fish_release.php"); exit(); }
$ponds_for_form = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- จัดการการ UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pond_id = $_POST['pond_id'];
    $release_date = $_POST['release_date'];
    $fish_amount = $_POST['fish_amount'];
    $total_cost = $_POST['total_cost'];

    try {
        $stmt_update = $conn->prepare("UPDATE fish_releases SET pond_id = ?, release_date = ?, fish_amount = ?, total_cost = ? WHERE id = ?");
        $stmt_update->execute([$pond_id, $release_date, $fish_amount, $total_cost, $record_id]);
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'อัปเดตข้อมูลสำเร็จ!'];
        header("Location: add_fish_release.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        header("Location: edit_fish_release.php?id=" . $record_id);
        exit();
    }
}
?>

<h1 class="gradient-text fw-bolder"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลการปล่อยลูกปลา</h1>
<p class="lead">คุณกำลังแก้ไขรายการของบ่อ <strong><?=htmlspecialchars($conn->query("SELECT name FROM ponds WHERE id = {$record['pond_id']}")->fetchColumn())?></strong> ที่ปล่อยเมื่อวันที่ <strong><?=htmlspecialchars($record['release_date'])?></strong></p>
<hr class="mb-4">


<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3"><label for="pond_id" class="form-label">บ่อปลา</label><select name="pond_id" id="pond_id" class="form-select" required><?php foreach ($ponds_for_form as $pond):?><option value="<?=$pond['id']?>" <?=($pond['id'] == $record['pond_id']) ? 'selected' : ''?>><?=htmlspecialchars($pond['name'])?></option><?php endforeach;?></select></div>
                    <div class="mb-3"><label for="release_date" class="form-label">วันที่ปล่อยปลา</label><input type="date" name="release_date" id="release_date" class="form-control" value="<?=htmlspecialchars($record['release_date'])?>" required></div>
                    <div class="mb-3"><label for="fish_amount" class="form-label">จำนวน (ตัว)</label><input type="number" name="fish_amount" id="fish_amount" class="form-control" value="<?=htmlspecialchars($record['fish_amount'])?>" required min="1"></div>
                    <div class="mb-3"><label for="total_cost" class="form-label">ต้นทุนค่าลูกปลา (บาท)</label><input type="number" step="0.01" name="total_cost" id="total_cost" class="form-control" value="<?=htmlspecialchars($record['total_cost'])?>" required min="0"></div>
                    <div class="d-flex justify-content-between">
                        <a href="add_fish_release.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> ยกเลิก</a>
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