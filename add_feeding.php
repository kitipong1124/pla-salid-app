<?php
// 2. เรียกใช้ Header ตัวใหม่ที่มี Sidebar
// ไฟล์นี้จะจัดการ session, config, และแสดงผล sidebar ให้เราอัตโนมัติ
session_start();
include_once __DIR__ . '/config.php';

// 3. โค้ด PHP เฉพาะของหน้านี้ (ส่วน Logic) - เหมือนเดิมทุกประการ
// --- การประมวลผลฟอร์มเมื่อมีการส่งข้อมูล (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pond_id = $_POST['pond_id'];
    $feed_date = $_POST['feed_date'];
    $feed_type = $_POST['feed_type'];
    $feed_amount_sacks = $_POST['feed_amount_sacks'];

    if (!empty($pond_id) && !empty($feed_date) && !empty($feed_type) && !empty($feed_amount_sacks)) {
        try {
            $stmt = $conn->prepare("INSERT INTO feeding_records (pond_id, feed_date, feed_type, feed_amount_sacks) VALUES (?, ?, ?, ?)");
            $stmt->execute([$pond_id, $feed_date, $feed_type, $feed_amount_sacks]);
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'บันทึกข้อมูลการให้อาหารสำเร็จ!'];
            header("Location: view_feeding.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
            header("Location: add_feeding.php");
            exit();
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง'];
        header("Location: add_feeding.php");
        exit();
    }
}
$page_title = "บันทึกการให้อาหาร";
include 'templates/sidebar_header.php';
// --- ดึงข้อมูลสำหรับใช้ในฟอร์ม (Dropdown) ---
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
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-plus-circle-fill"></i> บันทึกข้อมูลการให้อาหาร</h4>
            </div>
            <div class="card-body">
                <?php if ($userRole === 'owner' && empty($ponds_for_form)): ?>
                    <div class="alert alert-warning">คุณยังไม่ได้รับมอบหมายให้ดูแลบ่อใดๆ กรุณาติดต่อผู้ดูแลระบบ</div>
                <?php else: ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="pond_id" class="form-label">เลือกบ่อปลา</label>
                            <select name="pond_id" id="pond_id" class="form-select" required>
                                <option value="" disabled selected>-- กรุณาเลือกบ่อ --</option>
                                <?php foreach ($ponds_for_form as $pond): ?>
                                    <option value="<?= $pond['id'] ?>">
                                        <?= htmlspecialchars($pond['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="feed_date" class="form-label">วันที่ให้อาหาร</label>
                            <input type="date" name="feed_date" id="feed_date" class="form-control" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="feed_type" class="form-label">ประเภทอาหาร</label>
                            <select name="feed_type" id="feed_type" class="form-control" required>
                                <option value="" disabled selected>-- กรุณาเลือกประเภทอาหาร --</option>
                                <option value="เบทาโกร 811">เบทาโกร 811</option>
                                <option value="เบทาโกร 812">เบทาโกร 812</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="feed_amount_sacks" class="form-label">ปริมาณอาหาร (กระสอบ/ลูก)</label>
                            <input type="number" step="0.5" name="feed_amount_sacks" id="feed_amount_sacks" class="form-control" 
                                   placeholder="เช่น 10.5" required>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="view_feeding.php" class="btn btn-secondary me-md-2">
                                <i class="bi bi-card-list"></i> ดูประวัติ
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save-fill"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
include 'templates/sidebar_footer.php';
?>
