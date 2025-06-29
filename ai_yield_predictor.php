<?php
$page_title = "AI ทำนายผลผลิตและกำไร";
include 'templates/sidebar_header.php';

$prediction_data = null;
$error_message = '';
$input_values = $_POST;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $features_order = [
        'pond_size_rai', 'cycle_duration_days', 'initial_fish_amount', 
        'initial_fish_cost', 'total_food_sacks_811', 'total_food_sacks_812',
        'total_food_cost', 'other_expenses', 'prorated_rent_cost', 
        'avg_ph', 'avg_ammonium', 'avg_nitrite', 'water_problem_incidents'
    ];
    
    $input_data_for_json = [];
    $is_valid = true;
    foreach ($features_order as $feature) {
        if (isset($_POST[$feature]) && is_numeric($_POST[$feature])) {
            // สร้าง array แบบ key-value สำหรับ JSON
            $input_data_for_json[$feature] = (float)$_POST[$feature];
        } else {
            $is_valid = false;
            break;
        }
    }

    if ($is_valid) {
        // --- ส่วนที่แก้ไขทั้งหมด: เปลี่ยนวิธีการส่งข้อมูล ---

        // 1. สร้าง "จดหมาย" (ไฟล์ JSON ชั่วคราว)
        $temp_file = tempnam(sys_get_temp_dir(), 'pred_input_');
        // เราจะส่งข้อมูลเป็น object ที่มี key ตรงกับชื่อ feature
        file_put_contents($temp_file, json_encode($input_data_for_json));

        // 2. เตรียมคำสั่งเพื่อส่ง "จดหมาย" ให้ Python (วิธีใหม่)
        $python_path = "\"C:\\Users\\kitipong naktub\\AppData\\Local\\Microsoft\\WindowsApps\\python.exe\"";
        $script_path = "predict_yield.py";
        // *** จุดที่แก้ไข: ครอบ path ของไฟล์ด้วย "..." ด้วยตัวเอง ***
        $command = $python_path . " -X utf8 " . $script_path . " \"" . $temp_file . "\"";
        
        // 3. รันคำสั่ง
        $raw_output = shell_exec($command . " 2>&1");
        $result = json_decode(trim($raw_output), true);

        // 4. ลบ "จดหมาย" ทิ้ง
        unlink($temp_file);

        // 5. ตรวจสอบผลลัพธ์
        if (isset($result['success']) && $result['success']) {
            $prediction_data = $result;
        } else {
            $error_message = $result['error'] ?? "เกิดข้อผิดพลาดที่ไม่รู้จักจากสคริปต์ AI. Raw Output: " . $raw_output;
        }
    } else {
        $error_message = "กรุณากรอกข้อมูลให้ครบทุกช่องและต้องเป็นตัวเลขเท่านั้น";
    }
}
?>

<h1 class="gradient-text fw-bolder"><i class="bi bi-robot"></i> AI ทำนายผลผลิตและกำไร</h1>
<p class="lead">กรอกแผนการเลี้ยงของคุณ เพื่อให้ AI ช่วยคาดการณ์ผลลัพธ์สุดท้าย</p>
<hr>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">ใส่แผนการเลี้ยงของคุณ</h5></div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <?php
                    // *** จุดที่แก้ไข: เปลี่ยน 'price_selling/Kg' เป็น 'price_selling_per_kg' ***
                    $form_fields = [
                        'pond_size_rai' => 'ขนาดบ่อ (ไร่)', 'cycle_duration_days' => 'ระยะเวลาเลี้ยง (วัน)',
                        'initial_fish_amount' => 'จำนวนลูกปลา (ตัว)', 'initial_fish_cost' => 'ต้นทุนลูกปลา (บาท)',
                        'total_food_sacks_811' => 'รวมอาหาร 811 (กระสอบ)', 'total_food_sacks_812' => 'รวมอาหาร 812 (กระสอบ)',
                        'total_food_cost' => 'รวมค่าอาหาร (บาท)', 'other_expenses' => 'ค่าใช้จ่ายอื่นๆ (บาท)',
                        'prorated_rent_cost' => 'ค่าเช่าตามสัดส่วน (บาท)', 'avg_ph' => 'ค่า pH เฉลี่ย',
                        'avg_ammonium' => 'แอมโมเนียเฉลี่ย', 'avg_nitrite' => 'ไนไตรต์เฉลี่ย',
                        'water_problem_incidents' => 'จำนวนครั้งที่น้ำมีปัญหา',
                        'price_selling_per_kg' => 'ราคาขายต่อกิโลกรัม (บาท)' // แก้ไข key นี้
                    ];
                    ?>
                    <?php foreach ($form_fields as $name => $label): ?>
                        <div class="col-md-6">
                            <label for="<?= $name ?>" class="form-label"><?= $label ?></label>
                            <input type="number" step="any" name="<?= $name ?>" id="<?= $name ?>" class="form-control" value="<?= htmlspecialchars($input_values[$name] ?? 0) ?>" required>
                        </div>
                    <?php endforeach; ?>

                    <div class="col-12 d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-graph-up-arrow"></i> ทำนายผลลัพธ์</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">ผลการทำนายจาก AI</h5></div>
            <div class="card-body" style="min-height: 400px;">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                <?php elseif ($prediction_data): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <h6 class="text-muted">น้ำหนักผลผลิตที่คาดการณ์</h6>
                            <p class="display-5 fw-bold text-info"><?= number_format($prediction_data['predicted_weight'], 2) ?> กก.</p>
                        </div>
                        <hr>
                        <div class="mt-4">
                            <h6 class="text-muted">กำไร/ขาดทุนสุทธิที่คาดการณ์</h6>
                            <p class="display-5 fw-bold <?= ($prediction_data['predicted_profit'] >= 0) ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($prediction_data['predicted_profit'], 2) ?> บาท
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted p-5"><i class="bi bi-clipboard-data-fill" style="font-size: 3rem;"></i><p class="mt-2">กรอกข้อมูลแผนการเลี้ยงด้านซ้ายเพื่อรอรับผลการทำนาย</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // อ้างอิงไปยัง element input ของเรา
    const food811Input = document.getElementById('total_food_sacks_811');
    const food812Input = document.getElementById('total_food_sacks_812');
    const totalCostInput = document.getElementById('total_food_cost');

    // ราคาอาหาร (ควรจะตรงกับในฝั่ง PHP)
    const price811 = 625;
    const price812 = 645;

    // ฟังก์ชันสำหรับคำนวณและอัปเดตค่า
    function calculateTotalCost() {
        const sacks811 = parseFloat(food811Input.value) || 0;
        const sacks812 = parseFloat(food812Input.value) || 0;
        
        const totalCost = (sacks811 * price811) + (sacks812 * price812);
        
        totalCostInput.value = totalCost.toFixed(0);
    }

    // สั่งให้ฟังก์ชันทำงานทุกครั้งที่มีการพิมพ์ในช่องอาหาร
    food811Input.addEventListener('input', calculateTotalCost);
    food812Input.addEventListener('input', calculateTotalCost);
});
</script>
<?php
include 'templates/sidebar_footer.php';
?>