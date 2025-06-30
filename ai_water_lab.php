<?php
// 1. ตั้งชื่อ Title และเรียกใช้ Header
$page_title = "AI วิเคราะห์คุณภาพน้ำ";
include 'templates/sidebar_header.php';

// --- ส่วนจัดการ Logic ---

// ดึงข้อมูลบ่อสำหรับฟอร์ม Dropdown
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

// ส่วนประมวลผลเมื่อกดปุ่ม "วิเคราะห์ผล"
$prediction_result = '';
$error_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // โค้ดส่วนนี้ยังคงเหมือนเดิมทุกประการ
    $ph_input = $_POST['ph'] ?? null;
    $ammonium_input = $_POST['ammonium'] ?? null;
    $nitrite_input = $_POST['nitrite'] ?? null;
    if (is_numeric($ph_input) && is_numeric($ammonium_input) && is_numeric($nitrite_input)) {
        $ph = (float)$ph_input;
        $ammonium = (float)$ammonium_input;
        $nitrite = (float)$nitrite_input;
        $python_path = "\"C:\\Users\\kitipong naktub\\AppData\\Local\\Microsoft\\WindowsApps\\python.exe\"";
        $script_path = "predict.py";
        $command = $python_path . " -X utf8 " . $script_path . " " . $ph . " " . $ammonium . " " . $nitrite;
        $command_to_run = $command . " 2>&1";
        $raw_output = shell_exec($command_to_run);
        if ($raw_output === null || str_contains(strtolower(trim($raw_output)), 'traceback') || str_contains(strtolower(trim($raw_output)), 'error')) {
            $error_message = "เกิดข้อผิดพลาดในการประมวลผลของ AI";
        } else {
            $prediction_result = $raw_output;
        }
    } else {
        $error_message = "กรุณากรอกข้อมูลเป็นตัวเลขให้ครบทุกช่อง";
    }
}
?>

<h1 class="gradient-text fw-bolder"><i class="bi bi-robot"></i> ห้องปฏิบัติการคุณภาพน้ำ (AI)</h1>
<p class="lead">โหลดข้อมูลจริงจากบ่อ หรือทดลองจำลองสถานการณ์ต่างๆ เพื่อรับคำแนะนำจาก AI</p>
<hr class="mb-4">

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h5 class="mb-0">1. โหลดข้อมูลจริง (Optional)</h5></div>
            <div class="card-body">
                <div class="input-group">
                    <select id="pondSelector" class="form-select">
                        <option value="">-- เลือกบ่อเพื่อโหลดข้อมูล --</option>
                        <?php foreach($ponds_for_form as $pond): ?>
                            <option value="<?= $pond['id'] ?>"><?= htmlspecialchars($pond['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary" type="button" id="loadDataBtn"><i class="bi bi-cloud-download"></i> โหลด</button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">2. ปรับค่าและวิเคราะห์ผล</h5></div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-4">
                        <label for="phSlider" class="form-label d-flex justify-content-between">
                            <span>ค่า pH</span>
                            <span id="phValue" class="badge bg-primary rounded-pill">7.5</span>
                        </label>
                        <input type="range" class="form-range" id="phSlider" name="ph" min="5" max="10" step="0.1" value="7.5">
                    </div>
                    <div class="mb-4">
                        <label for="ammoniumSlider" class="form-label d-flex justify-content-between">
                            <span>ค่าแอมโมเนีย (mg/L)</span>
                            <span id="ammoniumValue" class="badge bg-danger rounded-pill">0.25</span>
                        </label>
                        <input type="range" class="form-range" id="ammoniumSlider" name="ammonium" min="0" max="2" step="0.01" value="0.25">
                    </div>
                    <div class="mb-3">
                        <label for="nitriteSlider" class="form-label d-flex justify-content-between">
                            <span>ค่าไนไตรต์ (mg/L)</span>
                            <span id="nitriteValue" class="badge bg-warning rounded-pill">0.1</span>
                        </label>
                        <input type="range" class="form-range" id="nitriteSlider" name="nitrite" min="0" max="1" step="0.01" value="0.1">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-magic"></i> วิเคราะห์ผล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">ผลการวิเคราะห์จาก AI</h5></div>
            <div class="card-body" style="min-height: 400px;">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><h4 class="alert-heading"><i class="bi bi-x-octagon-fill"></i> เกิดข้อผิดพลาด</h4><p><?= htmlspecialchars($error_message) ?></p></div>
                <?php elseif ($prediction_result): ?>
                    <?php
                    // --- ส่วนที่เพิ่มเข้ามา ---
                    // 1. ตั้งค่าคลาสเริ่มต้นเป็นสีฟ้า (เผื่อกรณีไม่ตรงกับเงื่อนไขใดๆ)
                    $alert_class = 'alert-info';
                    $result_text = trim($prediction_result); // นำผลลัพธ์มาตัดช่องว่างก่อน

                    // 2. ตรวจสอบข้อความเพื่อเปลี่ยนสี
                    if (str_contains($result_text, 'อันตราย')) {
                        $alert_class = 'alert-danger'; // ถ้าเจอคำว่า "อันตราย" ให้ใช้คลาสสีแดง
                    } elseif (str_contains($result_text, 'เสี่ยง')) {
                        $alert_class = 'alert-warning'; // ถ้าเจอคำว่า "เสี่ยง" ให้ใช้คลาสสีเหลือง
                    } elseif (str_contains($result_text, 'ปกติ')) {
                        $alert_class = 'alert-success'; // ถ้าเจอคำว่า "ปกติ" ให้ใช้คลาสสีเขียว
                    }
                    ?>
                    <div class="alert <?= $alert_class ?>">
                        <h4 class="alert-heading">ผลการทำนาย:</h4>
                        <p class="fs-5 fw-bold"><?= htmlspecialchars($result_text) ?></p>
                        <hr>
                        <p class="mb-0 small">นี่เป็นเพียงคำแนะนำเบื้องต้นจาก AI ควรพิจารณาร่วมกับปัจจัยอื่นๆ ในการเลี้ยงจริง</p>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted p-5"><i class="bi bi-cpu-fill" style="font-size: 3rem;"></i><p class="mt-2">รอรับข้อมูลเพื่อทำการวิเคราะห์...</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- ฟังก์ชันสำหรับเชื่อม Slider กับป้ายแสดงตัวเลข ---
    function setupSlider(sliderId, valueId) {
        const slider = document.getElementById(sliderId);
        const valueDisplay = document.getElementById(valueId);
        if (slider && valueDisplay) {
            // อัปเดตตัวเลขเมื่อมีการเลื่อน Slider
            slider.addEventListener('input', function() {
                valueDisplay.textContent = this.value;
            });
        }
    }

    setupSlider('phSlider', 'phValue');
    setupSlider('ammoniumSlider', 'ammoniumValue');
    setupSlider('nitriteSlider', 'nitriteValue');

    // --- ฟังก์ชันสำหรับปุ่ม "โหลดข้อมูล" ---
    const loadDataBtn = document.getElementById('loadDataBtn');
    const pondSelector = document.getElementById('pondSelector');

    if (loadDataBtn && pondSelector) {
        loadDataBtn.addEventListener('click', function() {
            const selectedPondId = pondSelector.value;
            if (!selectedPondId) {
                alert('กรุณาเลือกบ่อก่อน');
                return;
            }

            // ใช้ Fetch API เพื่อเรียกไฟล์ PHP
            fetch(`api/get_latest_water_data.php?pond_id=${selectedPondId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        const data = result.data;
                        
                        // อัปเดตค่าของ Slider
                        document.getElementById('phSlider').value = data.ph;
                        document.getElementById('ammoniumSlider').value = data.ammonium;
                        document.getElementById('nitriteSlider').value = data.nitrite;

                        // อัปเดตป้ายแสดงตัวเลข
                        document.getElementById('phValue').textContent = data.ph;
                        document.getElementById('ammoniumValue').textContent = data.ammonium;
                        document.getElementById('nitriteValue').textContent = data.nitrite;

                        alert('โหลดข้อมูลล่าสุดของบ่อสำเร็จ!');
                    } else {
                        alert('ไม่พบข้อมูลการตรวจวัดคุณภาพน้ำของบ่อนี้');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        });
    }
});
</script>

<?php
// เรียกใช้ Footer
include 'templates/sidebar_footer.php';
?>