<?php
// 1. ตั้งชื่อ Title และเรียกใช้ Header
$page_title = "รายละเอียดบ่อปลา";
include 'templates/sidebar_header.php';

// --- 1. ตรวจสอบสิทธิ์และ ID ที่ส่งมา ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่ได้ระบุ ID ของบ่อ'];
    header("Location: view_ponds.php");
    exit();
}
$pond_id = (int)$_GET['id'];

// ตรวจสอบสิทธิ์การเข้าถึงบ่อนี้
if ($userRole === 'owner' && !in_array($pond_id, $_SESSION['owned_ponds'] ?? [])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'คุณไม่มีสิทธิ์เข้าถึงบ่อนี้'];
    header("Location: view_ponds.php");
    exit();
}


// --- 2. ดึงข้อมูลทั้งหมดที่เกี่ยวกับบ่อนี้ ---

// 2.1 ข้อมูลสรุปของบ่อ
$stmt_pond = $conn->prepare("SELECT * FROM ponds WHERE id = ?");
$stmt_pond->execute([$pond_id]);
$pond_data = $stmt_pond->fetch(PDO::FETCH_ASSOC);

if (!$pond_data) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'ไม่พบบ่อปลาที่คุณต้องการดู'];
    header("Location: view_ponds.php");
    exit();
}

// 2.2 ข้อมูลการปล่อยปลารอบล่าสุด
$stmt_release = $conn->prepare("SELECT * FROM fish_releases WHERE pond_id = ? ORDER BY release_date DESC LIMIT 1");
$stmt_release->execute([$pond_id]);
$release_data = $stmt_release->fetch(PDO::FETCH_ASSOC);

// 2.3 คำนวณระยะเวลาเลี้ยง
$cycle_duration_days = "N/A";
if ($release_data) {
    $start_date_obj = new DateTime($release_data['release_date']);
    $end_date_obj = new DateTime(); // วันที่ปัจจุบัน
    $interval = $start_date_obj->diff($end_date_obj);
    $cycle_duration_days = $interval->days;
}

// 2.4 ข้อมูลคุณภาพน้ำทั้งหมด (สำหรับกราฟและตาราง)
$stmt_water = $conn->prepare("SELECT * FROM water_quality WHERE pond_id = ? ORDER BY check_date ASC");
$stmt_water->execute([$pond_id]);
$water_history = $stmt_water->fetchAll(PDO::FETCH_ASSOC);
// เตรียมข้อมูลสำหรับกราฟคุณภาพน้ำ
$water_chart_labels = []; $ph_data = []; $ammonium_data = []; $nitrite_data = [];
foreach ($water_history as $rec) {
    $water_chart_labels[] = $rec['check_date'];
    $ph_data[] = $rec['ph'];
    $ammonium_data[] = $rec['ammonium'];
    $nitrite_data[] = $rec['nitrite'];
}

// 2.5 ข้อมูลการให้อาหารทั้งหมด (เตรียมข้อมูลสำหรับกราฟด้วย)
$stmt_feeding = $conn->prepare("SELECT * FROM feeding_records WHERE pond_id = ? ORDER BY feed_date ASC");
$stmt_feeding->execute([$pond_id]);
$feeding_history = $stmt_feeding->fetchAll(PDO::FETCH_ASSOC);
$total_food_sacks = 0;
// เตรียมข้อมูลสำหรับกราฟการให้อาหาร
$feeding_chart_labels = [];
$feeding_chart_data = [];
foreach($feeding_history as $rec) { 
    $total_food_sacks += $rec['feed_amount_sacks']; 
    $feeding_chart_labels[] = $rec['feed_date'];
    $feeding_chart_data[] = $rec['feed_amount_sacks'];
}

// 2.6 ข้อมูลการสุ่มขนาดปลาทั้งหมด (เตรียมข้อมูลสำหรับกราฟด้วย)
$stmt_size = $conn->prepare("SELECT * FROM fish_sizes WHERE pond_id = ? ORDER BY record_date ASC");
$stmt_size->execute([$pond_id]);
$size_history = $stmt_size->fetchAll(PDO::FETCH_ASSOC);
// เตรียมข้อมูลสำหรับกราฟขนาดปลา
$size_chart_labels = [];
$size_chart_data = [];
foreach($size_history as $rec) {
    $size_chart_labels[] = $rec['record_date'];
    $size_chart_data[] = $rec['fish_count'];
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="gradient-text fw-bolder"><i class="bi bi-water"></i> รายละเอียดบ่อ: <?= htmlspecialchars($pond_data['name']) ?></h1>
        <p class="lead">ศูนย์รวมข้อมูลทั้งหมดของบ่อนี้ เพื่อใช้ในการวิเคราะห์และติดตามผล</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"> <h6 class="card-subtitle mb-2 text-muted">ขนาดบ่อ</h6> <p class="card-title fs-5 fw-bold"><?= htmlspecialchars($pond_data['size_rai']) ?> ไร่</p> </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"> <h6 class="card-subtitle mb-2 text-muted">วันที่ปล่อยปลา</h6> <p class="card-title fs-5 fw-bold"><?= $release_data['release_date'] ?? 'N/A' ?></p> </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"> <h6 class="card-subtitle mb-2 text-muted">จำนวนที่ปล่อย</h6> <p class="card-title fs-5 fw-bold"><?= isset($release_data['fish_amount']) ? number_format($release_data['fish_amount']) : 'N/A' ?> ตัว</p> </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"> <h6 class="card-subtitle mb-2 text-muted">ระยะเวลาเลี้ยง</h6> <p class="card-title fs-5 fw-bold"><?= $cycle_duration_days ?> วัน</p> </div></div></div>
</div>


<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-thermometer-half"></i> ภาพรวมคุณภาพน้ำ</h5></div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-lg-4"><div class="border rounded p-2"><canvas id="phChart" style="height: 200px;"></canvas></div></div>
            <div class="col-lg-4"><div class="border rounded p-2"><canvas id="ammoniumChart" style="height: 200px;"></canvas></div></div>
            <div class="col-lg-4"><div class="border rounded p-2"><canvas id="nitriteChart" style="height: 200px;"></canvas></div></div>
        </div>
        <h6 class="mt-4">ประวัติการตรวจวัดทั้งหมด</h6>
        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
            <table class="table table-sm table-bordered table-hover">
                <thead class="table-light">
                    <tr class="text-center"><th>วันที่ตรวจ</th><th>pH</th><th>แอมโมเนีย</th><th>ไนไตรต์</th><th>สถานะ/ปัญหา</th></tr>
                </thead>
                <tbody class="text-center">
                    <?php if(!empty($water_history)): foreach (array_reverse($water_history) as $rec): ?>
                        <?php
                            $problems = [];
                            if ($rec['ph'] < 6.5 || $rec['ph'] > 8.5) { $problems[] = 'pH'; }
                            if ($rec['ammonium'] >= 0.5) { $problems[] = 'แอมโมเนีย'; }
                            if ($rec['nitrite'] >= 0.2) { $problems[] = 'ไนไตรต์'; }
                        ?>
                        <tr class="<?= !empty($problems) ? 'table-warning' : '' ?>">
                            <td><?= htmlspecialchars($rec['check_date']) ?></td>
                            <td><?= htmlspecialchars($rec['ph']) ?></td>
                            <td><?= htmlspecialchars($rec['ammonium']) ?></td>
                            <td><?= htmlspecialchars($rec['nitrite']) ?></td>
                            <td>
                                <?php if(empty($problems)): ?>
                                    <span class="badge bg-success">ปกติ</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ผิดปกติ: <?= implode(', ', $problems) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-muted text-center p-3">ยังไม่มีข้อมูล</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-journal-text"></i> ประวัติการให้อาหาร</h5></div>
            <div class="card-body">
                <div class="mb-4" style="height: 250px;">
                    <canvas id="feedingHistoryChart"></canvas>
                </div>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr class="text-center"><th>วันที่</th><th>ประเภทอาหาร</th><th class="text-end">ปริมาณ (กระสอบ)</th></tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($feeding_history)): ?>
                                <?php foreach (array_reverse($feeding_history) as $rec): ?>
                                    <tr>
                                        <td class="text-center"><?= htmlspecialchars($rec['feed_date']) ?></td>
                                        <td><?= htmlspecialchars($rec['feed_type']) ?></td>
                                        <td class="text-end"><?= htmlspecialchars(number_format($rec['feed_amount_sacks'], 1)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="text-center text-muted p-3">ยังไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer fw-bold text-end bg-light">อาหารสะสมทั้งหมด: <?= number_format($total_food_sacks, 1) ?> กระสอบ</div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-rulers"></i> ประวัติการสุ่มขนาดปลา</h5></div>
            <div class="card-body">
                <div class="mb-4" style="height: 250px;">
                    <canvas id="sizeHistoryChart"></canvas>
                </div>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr class="text-center"><th>วันที่สุ่ม</th><th class="text-end">ขนาด (ตัว/กก.)</th></tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($size_history)): ?>
                                <?php foreach (array_reverse($size_history) as $rec): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($rec['record_date']) ?></td>
                                    <td class="text-end"><?= htmlspecialchars($rec['fish_count']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center text-muted p-3">ยังไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const commonOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };
    
    // กราฟคุณภาพน้ำ 3 อัน
    new Chart(document.getElementById('phChart'), { type: 'line', data: { labels: <?= json_encode($water_chart_labels) ?>, datasets: [{ label: 'pH', data: <?= json_encode($ph_data) ?>, borderColor: 'rgb(54, 162, 235)', tension: 0.1 }] }, options: { ...commonOptions, scales: { y: { title: { display: true, text: 'pH' } } } } });
    new Chart(document.getElementById('ammoniumChart'), { type: 'line', data: { labels: <?= json_encode($water_chart_labels) ?>, datasets: [{ label: 'Ammonia', data: <?= json_encode($ammonium_data) ?>, borderColor: 'rgb(255, 99, 132)', tension: 0.1 }] }, options: { ...commonOptions, scales: { y: { title: { display: true, text: 'Ammonia (mg/L)' } } } } });
    new Chart(document.getElementById('nitriteChart'), { type: 'line', data: { labels: <?= json_encode($water_chart_labels) ?>, datasets: [{ label: 'Nitrite', data: <?= json_encode($nitrite_data) ?>, borderColor: 'rgb(255, 159, 64)', tension: 0.1 }] }, options: { ...commonOptions, scales: { y: { title: { display: true, text: 'Nitrite (mg/L)' } } } } });

    // กราฟประวัติการให้อาหาร
    const feedingCtx = document.getElementById('feedingHistoryChart');
    if (feedingCtx) {
        new Chart(feedingCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($feeding_chart_labels) ?>,
                datasets: [{
                    label: 'ปริมาณอาหาร (กระสอบ)',
                    data: <?= json_encode($feeding_chart_data) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }]
            },
            options: { ...commonOptions, scales: { y: { title: { display: true, text: 'ปริมาณ (กระสอบ)' } } } }
        });
    }

    // กราฟประวัติขนาดปลา
    const sizeCtx = document.getElementById('sizeHistoryChart');
    if (sizeCtx) {
        new Chart(sizeCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($size_chart_labels) ?>,
                datasets: [{
                    label: 'ขนาด (ตัว/กก.)',
                    data: <?= json_encode($size_chart_data) ?>,
                    borderColor: 'rgb(153, 102, 255)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: { 
                ...commonOptions, 
                scales: { 
                    y: { 
                        reverse: true,
                        title: { display: true, text: 'ขนาด (ตัว/กก.)' } 
                    } 
                } 
            }
        });
    }
});
</script>
<?php
include 'templates/sidebar_footer.php';
?>