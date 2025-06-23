<?php
// 1. ตั้งชื่อ Title และเรียกใช้ Header ที่มี Sidebar
$page_title = "Dashboard - ภาพรวมระบบ";
include 'templates/sidebar_header.php';

// เพิ่มส่วนนี้เพื่อตั้งค่า Timezone และเตรียมข้อความวันที่
date_default_timezone_set('Asia/Bangkok');
$thai_date = "วันนี้วัน " . date('l ที่ j F Y');

// 2. โค้ด PHP Logic ทั้งหมดสำหรับดึงข้อมูล
// ==========================================

// 2.1. กำหนดบ่อที่เลือก (จาก URL) และบ่อที่ user มีสิทธิ์ดู
$selected_pond_id = isset($_GET['pond_id']) && is_numeric($_GET['pond_id']) ? (int)$_GET['pond_id'] : 'all';
$accessible_ponds = [];
$ponds_for_dropdown = [];

if ($userRole === 'admin') {
    $stmt = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC");
    $ponds_for_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $accessible_ponds = array_column($ponds_for_dropdown, 'id');
} else {
    $owned_ponds_ids = $_SESSION['owned_ponds'] ?? [];
    if (!empty($owned_ponds_ids)) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds_ids), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM ponds WHERE id IN ($placeholders) ORDER BY name ASC");
        $stmt->execute($owned_ponds_ids);
        $ponds_for_dropdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $accessible_ponds = $owned_ponds_ids;
}

// 2.2. เตรียม WHERE clause สำหรับกรองข้อมูลใน SQL
$where_clause = '';
$params = [];
if ($selected_pond_id !== 'all') {
    if (in_array($selected_pond_id, $accessible_ponds)) {
        $where_clause = "WHERE pond_id = ?";
        $params = [$selected_pond_id];
    } else {
        $selected_pond_id = 'all';
    }
}
if ($selected_pond_id === 'all' && !empty($accessible_ponds)) {
    $placeholders = implode(',', array_fill(0, count($accessible_ponds), '?'));
    $where_clause = "WHERE pond_id IN ($placeholders)";
    $params = $accessible_ponds;
}

// 2.3. ดึงข้อมูลสำหรับ Stat Cards ทั้ง 5 ใบ
$total_ponds_count = count($accessible_ponds);
$total_fish_amount = 0;
$latest_water_quality = ['status' => 'ไม่มีข้อมูล', 'class' => 'text-muted'];
$food_prices = ['เบทาโกร 811' => 625, 'เบทาโกร 812' => 645];
$food_summary = [];
foreach ($food_prices as $type => $price) {
    $food_summary[$type] = ['sacks' => 0, 'cost' => 0];
}

if (!empty($params)) {
    // ปลาในบ่อ
    $sql_fish = "SELECT SUM(fish_amount) as total FROM fish_releases " . str_replace('pond_id', 'fish_releases.pond_id', $where_clause);
    $stmt_fish = $conn->prepare($sql_fish);
    $stmt_fish->execute($params);
    $total_fish_amount = $stmt_fish->fetchColumn() ?: 0;

    // คุณภาพน้ำ
    $ponds_with_status = [];
    $ponds_needing_inspection = 0;
    $overall_status = ['status' => 'ไม่มีข้อมูล', 'class' => 'text-muted', 'detail' => ''];

    if (!empty($accessible_ponds)) {
        $placeholders = implode(',', array_fill(0, count($accessible_ponds), '?'));
        // ดึงข้อมูลทั้ง ph, ammonium, nitrite
        $sql_water = "
            SELECT pond_id, pond_name, ph, ammonium, nitrite, check_date
            FROM (
                SELECT
                    wq.pond_id, p.name AS pond_name, wq.ph, wq.ammonium, wq.nitrite, wq.check_date,
                    ROW_NUMBER() OVER(PARTITION BY wq.pond_id ORDER BY wq.check_date DESC, wq.id DESC) as rn
                FROM water_quality wq JOIN ponds p ON wq.pond_id = p.id
                WHERE wq.pond_id IN ($placeholders)
            ) AS subquery
            WHERE rn = 1
        ";
        $stmt_water = $conn->prepare($sql_water);
        $stmt_water->execute(array_values($accessible_ponds));
        $latest_water_data_per_pond = $stmt_water->fetchAll(PDO::FETCH_ASSOC);

        // วนลูปเพื่อสร้าง array สถานะของแต่ละบ่อ (เวอร์ชันแก้ไข UI)
        foreach ($latest_water_data_per_pond as $data) {
            $problems = [];
            // เช็คเงื่อนไขแต่ละข้อ
            if ($data['ph'] < 6.5 || $data['ph'] > 8.5) { $problems[] = 'pH'; }
            if ($data['ammonium'] >= 0.5) { $problems[] = 'แอมโมเนีย'; }
            if ($data['nitrite'] >= 0.2) { $problems[] = 'ไนไตรต์'; }

            $badge_text = '';
            $status_class = '';
            $problem_string = '';

            if (empty($problems)) {
                $badge_text = 'ปกติ';
                $status_class = 'text-success';
            } else {
                $badge_text = 'ต้องตรวจสอบ';
                $status_class = 'text-warning';
                $ponds_needing_inspection++;
                // สร้างข้อความสรุปปัญหา
                $problem_string = 'ปัญหา: ' . implode(', ', $problems);
            }
            
            $ponds_with_status[$data['pond_name']] = [
                'badge_text' => $badge_text, 
                'class' => $status_class,
                'problems' => $problem_string,
                'ph' => $data['ph'], 
                'ammonium' => $data['ammonium'], 
                'nitrite' => $data['nitrite']
            ];
        }

        // กำหนดสถานะภาพรวม
        if (count($latest_water_data_per_pond) > 0) {
            if ($ponds_needing_inspection > 0) {
                $overall_status['status'] = 'ต้องตรวจสอบ';
                $overall_status['detail'] = $ponds_needing_inspection . ' บ่อ';
            } else {
                $overall_status['status'] = 'ทุกบ่อปกติ';
                $overall_status['class'] = 'text-success';
            }
        }
    }

        // กำหนดสถานะภาพรวม
        if (count($latest_water_data_per_pond) > 0) {
            if ($ponds_needing_inspection > 0) {
                $overall_status['status'] = 'ต้องตรวจสอบ';
                $overall_status['class'] = 'text-warning';
                $overall_status['detail'] = $ponds_needing_inspection . ' บ่อ';
            } else {
                $overall_status['status'] = 'ทุกบ่อปกติ';
                $overall_status['class'] = 'text-success';
            }
        }
    
    // สรุปการใช้อาหารแต่ละประเภท
    $sql_food_summary = "SELECT feed_type, SUM(feed_amount_sacks) as total_sacks FROM feeding_records " . str_replace('pond_id', 'feeding_records.pond_id', $where_clause) . " GROUP BY feed_type";
    $stmt_food_summary = $conn->prepare($sql_food_summary);
    $stmt_food_summary->execute($params);
    $food_data = $stmt_food_summary->fetchAll(PDO::FETCH_ASSOC);
    foreach ($food_data as $data) {
        $type = $data['feed_type'];
        if (isset($food_prices[$type])) {
            $food_summary[$type]['sacks'] = $data['total_sacks'];
            $food_summary[$type]['cost'] = $data['total_sacks'] * $food_prices[$type];
        }
    }
    $total_sacks_accumulated = ($food_summary['เบทาโกร 811']['sacks'] ?? 0) + ($food_summary['เบทาโกร 812']['sacks'] ?? 0);
    $total_cost_accumulated = ($food_summary['เบทาโกร 811']['cost'] ?? 0) + ($food_summary['เบทาโกร 812']['cost'] ?? 0);
}

// 4. ดึงข้อมูลสำหรับกราฟ "ปริมาณอาหาร" (เวอร์ชันหลายเส้น)
$food_chart_labels = []; $food_chart_datasets = []; $days_range = [];
for ($i = 6; $i >= 0; $i--) { $date = date('Y-m-d', strtotime("-$i days")); $food_chart_labels[] = date('D, M j', strtotime($date)); $days_range[] = $date; }
if (!empty($params)) {
    $sql_food_chart = "SELECT p.name as pond_name, DATE(fr.feed_date) as day, SUM(fr.feed_amount_sacks) as total_food FROM feeding_records fr JOIN ponds p ON fr.pond_id = p.id WHERE fr.feed_date >= ? " . (empty($where_clause) ? '' : ' AND ' . str_replace('WHERE ', '', str_replace('pond_id', 'fr.pond_id', $where_clause))) . " GROUP BY p.name, day ORDER BY day ASC";
    $chart_params = array_merge([date('Y-m-d', strtotime('-6 days'))], $params);
    $stmt_food_chart = $conn->prepare($sql_food_chart); $stmt_food_chart->execute($chart_params);
    $food_data_raw = $stmt_food_chart->fetchAll(PDO::FETCH_ASSOC);
    $ponds_food_data = [];
    foreach($food_data_raw as $row) { $ponds_food_data[$row['pond_name']][$row['day']] = $row['total_food']; }
    $color_index = 0; $colors = ['rgb(255, 99, 132)', 'rgb(75, 192, 192)', 'rgb(255, 205, 86)', 'rgb(201, 203, 207)', 'rgb(54, 162, 235)'];
    foreach ($ponds_food_data as $pond_name => $daily_data) {
        $data_points = []; foreach ($days_range as $date) { $data_points[] = $daily_data[$date] ?? 0; }
        $food_chart_datasets[] = [ 'label' => $pond_name, 'data' => $data_points, 'borderColor' => $colors[$color_index % count($colors)], 'backgroundColor' => substr($colors[$color_index % count($colors)], 0, -1) . ', 0.2)', 'fill' => true, 'tension' => 0.2 ];
        $color_index++;
    }
}


// 2.5. ดึงข้อมูลสำหรับกราฟการเจริญเติบโตแบบหลายเส้น
$growth_chart_labels = [];
$growth_chart_datasets = [];
if (!empty($params)) {
    $sql_growth = "SELECT p.name as pond_name, DATE_FORMAT(fs.record_date, '%Y-%m') as month, AVG(fs.fish_count) as avg_size FROM fish_sizes fs JOIN ponds p ON fs.pond_id = p.id " . str_replace('pond_id', 'fs.pond_id', $where_clause) . " GROUP BY p.name, month ORDER BY month ASC, p.name ASC";
    $stmt_growth = $conn->prepare($sql_growth); $stmt_growth->execute($params); $growth_data_raw = $stmt_growth->fetchAll(PDO::FETCH_ASSOC);
    $all_months = []; $ponds_data = [];
    foreach($growth_data_raw as $row) { $ponds_data[$row['pond_name']][$row['month']] = $row['avg_size']; if (!in_array($row['month'], $all_months)) { $all_months[] = $row['month']; } }
    sort($all_months); $growth_chart_labels = $all_months;
    $color_index = 0; $colors = ['rgb(54, 162, 235)', 'rgb(255, 99, 132)', 'rgb(75, 192, 192)', 'rgb(255, 159, 64)', 'rgb(153, 102, 255)'];
    foreach ($ponds_data as $pond_name => $monthly_data) {
        $data_points = []; foreach ($all_months as $month) { $data_points[] = $monthly_data[$month] ?? null; }
        $growth_chart_datasets[] = [ 'label' => $pond_name, 'data' => $data_points, 'borderColor' => $colors[$color_index % count($colors)], 'tension' => 0.1, 'fill' => false ];
        $color_index++;
    }
}
?>

<!-- หัวเรื่อง -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="h3 mb-1 fw-bolder gradient-text">
                    <i class="bi bi-bar-chart-line-fill"></i> แผงควบคุมหลัก (Dashboard)</h1>
                <p class="mb-0 text-body-secondary">
                    ยินดีต้อนรับ, <strong><?= htmlspecialchars($username) ?></strong>! <?= $thai_date ?>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label for="pond_id" class="form-label mb-0 text-nowrap">
                    <i class="bi bi-filter"></i> กรองข้อมูล:
                </label>
                <form method="get" id="pondFilterForm" class="m-0">
                    <select name="pond_id" class="form-select form-select-sm" onchange="this.form.submit();" style="min-width: 200px;">
                        <option value="all">-- ทุกบ่อในดูแล --</option>
                        <?php foreach ($ponds_for_dropdown as $pond): ?>
                            <option value="<?= $pond['id'] ?>" <?= ($selected_pond_id == $pond['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pond['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- การ์ดทั้งหมดรวมอยู่ใน row เดียว -->
<div class="row g-4 mb-4">

    <div class="col-md-6 col-xl-3">
        <div class="card text-bg-primary shadow-sm rounded-3">
            <div class="card-body position-relative">
                <h5 class="card-title">จำนวนบ่อในดูแล</h5>
                <p class="fs-4 fw-bold mb-0"><?= $total_ponds_count ?></p>
                <i class="bi bi-grid-3x3-gap-fill fs-2 position-absolute top-50 end-0 translate-middle-y me-3 text-white-50"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card text-bg-info shadow-sm rounded-3">
            <div class="card-body position-relative">
                <h5 class="card-title">ปลาในบ่อ (ประมาณ)</h5>
                <p class="fs-4 fw-bold mb-0"><?= number_format($total_fish_amount) ?> ตัว</p>
                <i class="bi bi-inboxes-fill fs-2 position-absolute top-50 end-0 translate-middle-y me-3 text-white-50"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card text-bg-success shadow-sm rounded-3">
            <a class="text-white text-decoration-none" data-bs-toggle="collapse" href="#foodDetailCollapse" role="button" style="cursor: pointer;">
                <div class="card-body position-relative">
                    <h5 class="card-title d-flex justify-content-between align-items-center">
                        อาหารและต้นทุนรวม <i class="bi bi-chevron-down small"></i>
                    </h5>
                    <p class="fs-5 fw-bold mb-0"><?= number_format($total_sacks_accumulated, 1) ?> กระสอบ</p>
                    <small class="text-white-50">มูลค่า <?= number_format($total_cost_accumulated) ?> บาท</small>
                    <i class="bi bi-coin fs-2 position-absolute top-50 end-0 translate-middle-y me-3 text-white-50"></i>
                </div>
            </a>
            <div class="collapse" id="foodDetailCollapse">
                <ul class="list-group list-group-flush card-collapse-scrollable">
                    <?php foreach ($food_summary as $food_name => $data): ?>
                        <li class="list-group-item bg-light d-flex justify-content-between align-items-center">
                            <span><?= htmlspecialchars($food_name) ?></span>
                            <span class="badge bg-success rounded-pill">
                                <?= number_format($data['sacks'], 1) ?> กระสอบ (<?= number_format($data['cost']) ?> บาท)
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card <?= ($overall_status['status'] === 'ต้องตรวจสอบ') ? 'text-bg-warning' : (($overall_status['status'] === 'ทุกบ่อปกติ') ? 'text-bg-success' : 'text-bg-secondary') ?> shadow-sm rounded-3">
            <a class="text-white text-decoration-none" data-bs-toggle="collapse" href="#waterDetailCollapse" role="button" style="cursor: pointer;">
                <div class="card-body position-relative">
                    <h5 class="card-title d-flex justify-content-between align-items-center">
                        คุณภาพน้ำภาพรวม <i class="bi bi-chevron-down small"></i>
                    </h5>
                    <p class="fs-4 fw-bold mb-0"><?= $overall_status['status'] ?></p>
                    <small class="text-white-50"><?= $overall_status['detail'] ?></small>
                    <i class="bi bi-check-circle-fill fs-2 position-absolute top-50 end-0 translate-middle-y me-3 text-white-50"></i>
                </div>
            </a>
            <div class="collapse" id="waterDetailCollapse">
                <ul class="list-group list-group-flush card-collapse-scrollable">
                    <?php if (count($ponds_for_dropdown) > 0): ?>
                        <?php foreach ($ponds_for_dropdown as $pond): ?>
                            <?php 
                                $status_info = $ponds_with_status[$pond['name']] ?? ['badge_text' => 'ไม่มีข้อมูล', 'class' => 'text-muted', 'problems' => ''];
                            ?>
                            <li class="list-group-item bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?= htmlspecialchars($pond['name']) ?></strong>
                                    <span class="badge rounded-pill <?= str_replace('text', 'bg', $status_info['class']) ?>">
                                        <?= htmlspecialchars($status_info['badge_text']) ?>
                                    </span>
                                </div>
                                <?php if ($status_info['badge_text'] !== 'ไม่มีข้อมูล'): ?>
                                <div class="fs-sm text-muted mt-1" style="font-size: 0.8rem;">
                                    pH: <?= $status_info['ph'] ?? '-' ?> | 
                                    NH₃: <?= $status_info['ammonium'] ?? '-' ?> | 
                                    NO₂: <?= $status_info['nitrite'] ?? '-' ?>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($status_info['problems'])): ?>
                                <div class="fs-sm text-danger mt-1" style="font-size: 0.8rem;">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <?= htmlspecialchars($status_info['problems']) ?>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item bg-light text-center text-muted">ไม่มีข้อมูลบ่อ</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>


<br><div class="row g-4">
    <div class="col-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-graph-up-arrow me-2"></i> กราฟเปรียบเทียบการเจริญเติบโตของปลา (รายเดือน)</div>
            <div class="card-body" style="height: 400px;"><canvas id="fishGrowthChart"></canvas></div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-bar-chart-line-fill me-2"></i> ปริมาณอาหารที่ใช้ (7 วันล่าสุด)</div>
            <div class="card-body" style="height: 400px;"><canvas id="foodChart"></canvas></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- กราฟที่ 1: ปริมาณอาหาร (เวอร์ชันหลายเส้น) ---
    const foodCtx = document.getElementById('foodChart');
    if (foodCtx) {
        new Chart(foodCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($food_chart_labels) ?>,
                
                // *** จุดที่แก้ไข: เปลี่ยนมาใช้ $food_chart_datasets ***
                // เพื่อให้รับข้อมูลแบบหลายเส้น (หลายบ่อ) ที่เราเตรียมไว้ใน PHP
                datasets: <?= json_encode($food_chart_datasets) ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'ปริมาณอาหาร (กระสอบ)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    }

    // --- กราฟที่ 2: การเจริญเติบโต (โค้ดส่วนนี้ถูกต้องอยู่แล้ว) ---
    const growthCtx = document.getElementById('fishGrowthChart');
    if (growthCtx) {
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($growth_chart_labels) ?>,
                datasets: <?= json_encode($growth_chart_datasets) ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        reverse: true,
                        title: {
                            display: true,
                            text: 'จำนวนตัวต่อกิโลกรัม (ค่าน้อย = ปลาตัวใหญ่)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'เดือน'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
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
