<?php
// 1. ตั้งชื่อ Title และเรียกใช้ Header
$page_title = "เลือกดูรายละเอียดบ่อ";
include 'templates/sidebar_header.php';

// 2. ดึงข้อมูลบ่อตามสิทธิ์ของผู้ใช้
$ponds_to_display = [];
if ($userRole === 'admin') {
    // Admin เห็นทุกบ่อ
    $stmt = $conn->query("SELECT * FROM ponds ORDER BY name ASC");
    $ponds_to_display = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else { // Owner เห็นเฉพาะบ่อตัวเอง
    $owned_ponds_ids = $_SESSION['owned_ponds'] ?? [];
    if (!empty($owned_ponds_ids)) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds_ids), '?'));
        $stmt = $conn->prepare("SELECT * FROM ponds WHERE id IN ($placeholders) ORDER BY name ASC");
        $stmt->execute($owned_ponds_ids);
        $ponds_to_display = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<h1 class="gradient-text fw-bolder"><i class="bi bi-collection-fill"></i> ภาพรวมบ่อเลี้ยง</h1>
<p class="lead">เลือกบ่อปลาที่คุณต้องการดูรายละเอียดเชิงลึก</p>
<hr class="mb-4">

<?php if (!empty($ponds_to_display)): ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($ponds_to_display as $pond): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-droplet-fill text-primary"></i> <?= htmlspecialchars($pond['name']) ?></h5>
                        <p class="card-text">
                            ขนาด: <?= htmlspecialchars($pond['size_rai']) ?> ไร่
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-end">
                        <a href="pond_detail.php?id=<?= $pond['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-search"></i> ดูรายละเอียด
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        ไม่พบบ่อปลาในความดูแลของคุณ
    </div>
<?php endif; ?>
<?php
include 'templates/sidebar_footer.php';
?>