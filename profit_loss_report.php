<?php
// 1. ตั้งชื่อ Title และเรียกใช้ Header
$page_title = "รายงานสรุปผลกำไร-ขาดทุน";
include 'templates/sidebar_header.php';

// --- ดึงข้อมูลสำหรับฟอร์ม Dropdown ---
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

// --- ส่วนประมวลผลและคำนวณ (เมื่อมีการกด "คำนวณ") ---
$show_report = false; // ตัวแปรสำหรับควบคุมการแสดงผลรายงาน
$report_data = [];    // ตัวแปรสำหรับเก็บข้อมูลทั้งหมดที่จะแสดง

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['pond_id']) && !empty($_GET['pond_id'])) {
    
    // 2.1. รับค่า Input จากฟอร์ม
    $pond_id = (int)$_GET['pond_id'];
    $harvest_date = $_GET['harvest_date'];
    $harvest_weight_kg = (float)$_GET['harvest_weight_kg'];
    $harvest_fish_size = (float)$_GET['harvest_fish_size'];
    $price_per_kg = (float)$_GET['price_per_kg'];
    $other_expenses = (float)$_GET['other_expenses'];
    $annual_rent = (float)$_GET['annual_rent'];

    // 2.2. ดึงข้อมูลที่จำเป็นจาก Database
    // ดึงข้อมูลบ่อ
    $stmt_pond = $conn->prepare("SELECT * FROM ponds WHERE id = ?");
    $stmt_pond->execute([$pond_id]);
    $pond_data = $stmt_pond->fetch(PDO::FETCH_ASSOC);

    // ดึงข้อมูลการปล่อยปลา (สมมติว่าใช้ล็อตล่าสุด)
    $stmt_release = $conn->prepare("SELECT * FROM fish_releases WHERE pond_id = ? ORDER BY release_date DESC LIMIT 1");
    $stmt_release->execute([$pond_id]);
    $release_data = $stmt_release->fetch(PDO::FETCH_ASSOC);

    if ($pond_data && $release_data) {
        $show_report = true;
        
        // --- 2.3. เริ่มการคำนวณ ---
        
        // คำนวณระยะเวลาเลี้ยง
        $start_date_obj = new DateTime($release_data['release_date']);
        $end_date_obj = new DateTime($harvest_date);
        $interval = $start_date_obj->diff($end_date_obj);
        $cycle_duration_days = $interval->days;

        // ดึงข้อมูลการใช้อาหารทั้งหมดในรอบการเลี้ยงนี้
        $stmt_food = $conn->prepare("SELECT feed_type, SUM(feed_amount_sacks) as total_sacks FROM feeding_records WHERE pond_id = ? AND feed_date BETWEEN ? AND ? GROUP BY feed_type");
        $stmt_food->execute([$pond_id, $release_data['release_date'], $harvest_date]);
        $food_usage_data = $stmt_food->fetchAll(PDO::FETCH_ASSOC);
        
        // คำนวณต้นทุนอาหาร
        $food_prices = ['เบทาโกร 811' => 625, 'เบทาโกร 812' => 645];
        $food_summary = [];
        $total_food_sacks = 0;
        $total_food_cost = 0;

        foreach ($food_prices as $type => $price) { $food_summary[$type] = ['sacks' => 0, 'cost' => 0]; }

        foreach ($food_usage_data as $data) {
            $type = $data['feed_type'];
            if (isset($food_prices[$type])) {
                $sacks = $data['total_sacks'];
                $cost = $sacks * $food_prices[$type];
                $food_summary[$type]['sacks'] = $sacks;
                $food_summary[$type]['cost'] = $cost;
                $total_food_sacks += $sacks;
                $total_food_cost += $cost;
            }
        }
        
        // คำนวณค่าเช่าตามสัดส่วน
        $prorated_rent_cost = ($annual_rent > 0) ? ($annual_rent / 365) * $cycle_duration_days : 0;
        
        // คำนวณรายรับและต้นทุนทั้งหมด
        $total_revenue = $harvest_weight_kg * $price_per_kg;
        $total_expenses = $total_food_cost + $release_data['total_cost'] + $prorated_rent_cost + $other_expenses;
        $net_profit_loss = $total_revenue - $total_expenses;

        // คำนวณ KPIs
        $estimated_harvested_fish_count = ($harvest_fish_size > 0) ? $harvest_weight_kg * $harvest_fish_size : 0;
        $survival_rate = ($release_data['fish_amount'] > 0) ? ($estimated_harvested_fish_count / $release_data['fish_amount']) * 100 : 0;
        $cost_per_kg = ($harvest_weight_kg > 0) ? $total_expenses / $harvest_weight_kg : 0;
        $gross_fcr = ($harvest_weight_kg > 0) ? ($total_food_sacks * 20) / $harvest_weight_kg : 0; // FCR ภาพรวม
        $fcg = ($harvest_weight_kg > 0) ? $total_food_cost / $harvest_weight_kg : 0; // ต้นทุนอาหารต่อ กก.
        $density = ($pond_data['size_rai'] > 0) ? $harvest_weight_kg / $pond_data['size_rai'] : 0;
        $profit_per_kg = ($harvest_weight_kg > 0) ? $net_profit_loss / $harvest_weight_kg : 0;
        
        // จัดเก็บข้อมูลทั้งหมดเพื่อนำไปแสดงผล
        $report_data = compact(
            'pond_data', 'release_data', 'harvest_date', 'cycle_duration_days', 
            'harvest_weight_kg', 'price_per_kg', 'total_revenue',
            'food_summary', 'total_food_sacks', 'total_food_cost',
            'prorated_rent_cost', 'other_expenses', 'total_expenses',
            'net_profit_loss', 'estimated_harvested_fish_count', 'survival_rate',
            'cost_per_kg', 'gross_fcr', 'fcg', 'density', 'profit_per_kg'
        );
    } else {
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'ไม่พบข้อมูลการปล่อยปลาของบ่อที่เลือก หรือข้อมูลไม่ครบถ้วน'];
    }
}
?>

<h1 class="gradient-text fw-bolder"><i class="bi bi-cash-coin"></i> รายงานสรุปผลกำไร-ขาดทุน</h1>
<p class="lead">เลือกบ่อและกรอกข้อมูลการเก็บเกี่ยวเพื่อคำนวณผลประกอบการ</p>
<hr class="mb-4">

<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">กรอกข้อมูลการเก็บเกี่ยว</h5></div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-6"><label for="pond_id" class="form-label">เลือกบ่อปลา</label><select name="pond_id" id="pond_id" class="form-select" required><option value="" disabled selected>-- เลือกบ่อ --</option><?php foreach ($ponds_for_form as $pond):?><option value="<?=$pond['id']?>" <?= (isset($_GET['pond_id']) && $_GET['pond_id'] == $pond['id']) ? 'selected' : '' ?>><?=htmlspecialchars($pond['name'])?></option><?php endforeach;?></select></div>
            <div class="col-md-6"><label for="harvest_date" class="form-label">วันที่จับปลา</label><input type="date" name="harvest_date" id="harvest_date" class="form-control" value="<?= $_GET['harvest_date'] ?? date('Y-m-d') ?>" required></div>
            <div class="col-md-6"><label for="harvest_weight_kg" class="form-label">น้ำหนักปลาที่จับได้ (กก.)</label><input type="number" step="0.01" name="harvest_weight_kg" id="harvest_weight_kg" class="form-control" placeholder="เช่น 3500.50" value="<?= $_GET['harvest_weight_kg'] ?? '' ?>" required></div>
            <div class="col-md-6"><label for="harvest_fish_size" class="form-label">ไซส์ปลาเฉลี่ยตอนจับ (ตัว/กก.)</label><input type="number" step="0.1" name="harvest_fish_size" id="harvest_fish_size" class="form-control" placeholder="เช่น 15" value="<?= $_GET['harvest_fish_size'] ?? '' ?>" required></div>
            <div class="col-md-6"><label for="price_per_kg" class="form-label">ราคาขายต่อ กก. (บาท)</label><input type="number" step="0.01" name="price_per_kg" id="price_per_kg" class="form-control" placeholder="เช่น 80.00" value="<?= $_GET['price_per_kg'] ?? '' ?>" required></div>
            <div class="col-md-6"><label for="other_expenses" class="form-label">ค่าใช้จ่ายอื่นๆ (บาท)</label><input type="number" step="0.01" name="other_expenses" id="other_expenses" class="form-control" placeholder="เช่น ค่ายา, ค่าไฟ" value="<?= $_GET['other_expenses'] ?? 0 ?>" required></div>
            <div class="col-md-12"><label for="annual_rent" class="form-label">ค่าเช่าบ่อ (ต่อปี)</label><input type="number" step="0.01" name="annual_rent" id="annual_rent" class="form-control" placeholder="หากไม่มีให้ใส่ 0" value="<?= $_GET['annual_rent'] ?? 0 ?>" required></div>
            <div class="col-12 text-end"><button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-calculator-fill"></i> คำนวณผล</button></div>
        </form>
    </div>
</div>


<?php if ($show_report): ?>
<div class="card shadow-sm" id="report-section">
    <div class="card-header bg-dark text-white">
        <h4 class="mb-0">สรุปผลประกอบการบ่อ: <?= htmlspecialchars($report_data['pond_data']['name']) ?></h4>
        <small>รอบการเลี้ยง: <?= $report_data['release_data']['release_date'] ?> ถึง <?= $report_data['harvest_date'] ?> (<?= $report_data['cycle_duration_days'] ?> วัน)</small>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <h5><i class="bi bi-graph-up text-success"></i> รายรับ (Revenue)</h5>
                <ul class="list-group mb-4">
                    <li class="list-group-item d-flex justify-content-between"><span>น้ำหนักปลาที่ขายได้</span> <strong><?= number_format($report_data['harvest_weight_kg'], 2) ?> กก.</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>ราคาขายต่อ กก.</span> <strong><?= number_format($report_data['price_per_kg'], 2) ?> บาท</strong></li>
                    <li class="list-group-item list-group-item-success d-flex justify-content-between"><strong>รายรับรวม</strong> <strong class="fs-5"><?= number_format($report_data['total_revenue'], 2) ?> บาท</strong></li>
                </ul>

                <h5><i class="bi bi-graph-down text-danger"></i> ต้นทุน (Expenses)</h5>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>ค่าอาหาร (<?= number_format($report_data['total_food_sacks'],1) ?> กระสอบ)</span>
                        <strong><?= number_format($report_data['total_food_cost'], 2) ?> บาท</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between"><span>ค่าลูกปลา</span><strong><?= number_format($report_data['release_data']['total_cost'], 2) ?> บาท</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>ค่าเช่า (<?= $report_data['cycle_duration_days'] ?> วัน)</span><strong><?= number_format($report_data['prorated_rent_cost'], 2) ?> บาท</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>ค่าใช้จ่ายอื่นๆ</span><strong><?= number_format($report_data['other_expenses'], 2) ?> บาท</strong></li>
                    <li class="list-group-item list-group-item-danger d-flex justify-content-between"><strong>ต้นทุนรวม</strong> <strong class="fs-5"><?= number_format($report_data['total_expenses'], 2) ?> บาท</strong></li>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="text-center p-3 border rounded mb-4 <?= ($report_data['net_profit_loss'] >= 0) ? 'bg-success-subtle' : 'bg-danger-subtle' ?>">
                    <h5>กำไร/ขาดทุนสุทธิ</h5>
                    <h2 class="display-5 fw-bold <?= ($report_data['net_profit_loss'] >= 0) ? 'text-success-emphasis' : 'text-danger-emphasis' ?>">
                        <?= number_format($report_data['net_profit_loss'], 2) ?>
                    </h2>
                    <span class="text-muted">บาท</span>
                </div>

                <h5><i class="bi bi-speedometer2"></i> ตัวชี้วัดประสิทธิภาพ (KPIs)</h5>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between"><span>อัตรารอด (โดยประมาณ)</span> <strong><?= number_format($report_data['survival_rate'], 2) ?> %</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>ต้นทุนรวมต่อ กก.</span> <strong><?= number_format($report_data['cost_per_kg'], 2) ?> บาท</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>FCR (ประสิทธิภาพอาหาร)</span> <strong><?= number_format($report_data['gross_fcr'], 2) ?></strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>FCG (ต้นทุนอาหารต่อเนื้อปลา)</span> <strong><?= number_format($report_data['fcg'], 2) ?> บาท/กก.</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>ความหนาแน่นผลผลิต</span> <strong><?= number_format($report_data['density'], 2) ?> กก./ไร่</strong></li>
                    <li class="list-group-item d-flex justify-content-between"><span>กำไรต่อ กก.</span> <strong><?= number_format($report_data['profit_per_kg'], 2) ?> บาท</strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
include 'templates/sidebar_footer.php';
?>