<?php
$page_title = "AI ที่ปรึกษาการเจริญเติบโต";
include 'templates/sidebar_header.php';

// ดึงชื่อบ่อทั้งหมดจากฐานข้อมูลจริงมาให้เลือก
$ponds_for_form = [];
if ($userRole === 'admin') {
    $stmt_ponds = $conn->query("SELECT id, name FROM ponds ORDER BY name ASC");
    $ponds_for_form = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);
} else {
    $owned_ponds_ids = $_SESSION['owned_ponds'] ?? [];
    if (!empty($owned_ponds_ids)) {
        $placeholders = implode(',', array_fill(0, count($owned_ponds_ids), '?'));
        $stmt_ponds = $conn->prepare("SELECT id, name FROM ponds WHERE id IN ($placeholders) ORDER BY name ASC");
        $stmt_ponds->execute($owned_ponds_ids);
        $ponds_for_form = $stmt_ponds->fetchAll(PDO::FETCH_ASSOC);
    }
}

$prediction_result = null;
$error_message = '';
$input_days_future = $_POST['days_to_predict'] ?? '';
$selected_pond_id = $_POST['pond_id'] ?? '';
$current_rearing_day_hidden = $_POST['current_rearing_day'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (is_numeric($input_days_future) && !empty($selected_pond_id) && is_numeric($current_rearing_day_hidden)) {
        
        $future_day = (float)$current_rearing_day_hidden + (float)$input_days_future;
        
        // ดึงข้อมูลสำหรับ AI Features จาก Database
        $stmt_pond_features = $conn->prepare("SELECT size_rai FROM ponds WHERE id = ?");
        $stmt_pond_features->execute([$selected_pond_id]);
        $pond_size_rai = $stmt_pond_features->fetchColumn();

        $stmt_release_features = $conn->prepare("SELECT fish_amount FROM fish_releases WHERE pond_id = ? ORDER BY release_date DESC LIMIT 1");
        $stmt_release_features->execute([$selected_pond_id]);
        $initial_fish_amount = $stmt_release_features->fetchColumn();

        if ($pond_size_rai && $initial_fish_amount) {
            $python_path = "\"C:\\Users\\kitipong naktub\\AppData\\Local\\Microsoft\\WindowsApps\\python.exe\"";
            $script_path = "predict_growth.py";
            
            // ส่ง argument 3 ตัว: rearing_day, pond_size_rai, initial_fish_amount
            $command = $python_path . " -X utf8 " . $script_path . " " . $future_day . " " . $pond_size_rai . " " . $initial_fish_amount;
            
            $raw_output = shell_exec($command . " 2>&1");
            $result = json_decode(trim($raw_output), true);

            if (isset($result['success']) && $result['success']) {
                $prediction_result = $result['predicted_size'];
            } else {
                $error_message = $result['error'] ?? "เกิดข้อผิดพลาดจาก AI: " . $raw_output;
            }
        } else {
            $error_message = "ไม่พบข้อมูลขนาดบ่อหรือข้อมูลการปล่อยปลาสำหรับใช้ทำนาย";
        }
    } else {
        $error_message = "กรุณาเลือกบ่อและกรอกข้อมูลให้ถูกต้อง";
    }
}
?>

<h1 class="gradient-text fw-bolder"><i class="bi bi-graph-up-arrow"></i> AI ที่ปรึกษาการเจริญเติบโต</h1>
<p class="lead">เลือกบ่อเพื่อดูสถานะปัจจุบัน และทำนายขนาดปลาในอนาคต</p>
<hr>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h5 class="mb-0">1. เลือกบ่อและดูสถานะปัจจุบัน</h5></div>
            <div class="card-body">
                <div class="input-group mb-3">
                    <select id="pondSelector" class="form-select">
                        <option value="">-- กรุณาเลือกบ่อ --</option>
                        <?php foreach($ponds_for_form as $pond): ?>
                            <option value="<?= $pond['id'] ?>"><?= htmlspecialchars($pond['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary" type="button" id="loadDataBtn">
                        <i class="bi bi-arrow-clockwise"></i> โหลดข้อมูล
                    </button>
                </div>
                <div id="currentStatusCard" class="alert alert-light" style="display: none;">
                    <strong>สถานะล่าสุด:</strong>
                    <ul class="list-unstyled mb-0 mt-2">
                        <li><i class="bi bi-calendar-check"></i> วันที่: <span id="latestDate"></span></li>
                        <li><i class="bi bi-rulers"></i> ขนาด: <span id="latestSize" class="fw-bold"></span> ตัว/กก.</li>
                        <li><i class="bi bi-hourglass-split"></i> ระยะเวลาเลี้ยง: <span id="currentDays"></span> วัน</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">2. ทำนายอนาคต</h5></div>
            <div class="card-body">
                <form id="predictionForm" method="post" style="display: none;">
                    <input type="hidden" name="pond_id" id="hidden_pond_id">
                    <input type="hidden" name="current_rearing_day" id="hidden_current_rearing_day">
                    <div class="mb-3">
                        <label for="days_to_predict" class="form-label">ทำนายการเติบโตในอีก (วัน)</label>
                        <input type="number" name="days_to_predict" id="days_to_predict" class="form-control" placeholder="เช่น 15 หรือ 30" value="<?= htmlspecialchars($input_days_future) ?>" required>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-magic"></i> ทำนายขนาดปลา</button></div>
                </form>
                <div id="formPlaceholder" class="text-muted">กรุณาเลือกบ่อและโหลดข้อมูลก่อน...</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-7">
        <div class="card shadow-sm">
             <div class="card-header"><h5 class="mb-0">ผลการทำนายจาก AI</h5></div>
             <div class="card-body text-center" style="min-height: 350px;">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger mt-3"><?= htmlspecialchars($error_message) ?></div>
                <?php elseif ($prediction_result !== null): ?>
                    <h6 class="text-muted">ในอีก <?= htmlspecialchars($input_days_future) ?> วันข้างหน้า (รวมเป็น <?= (int)$current_rearing_day_hidden + (int)$input_days_future ?> วัน)</h6>
                    <p class="display-4 fw-bold text-success"><?= htmlspecialchars($prediction_result) ?></p>
                    <p class="text-muted mb-0">ตัว/กิโลกรัม (โดยประมาณ)</p>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center h-100 text-muted p-4"><p>รอรับข้อมูลเพื่อเริ่มการทำนาย...</p></div>
                <?php endif; ?>
             </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pondSelector = document.getElementById('pondSelector');
    const loadDataBtn = document.getElementById('loadDataBtn');
    const statusCard = document.getElementById('currentStatusCard');
    const predictionForm = document.getElementById('predictionForm');
    const formPlaceholder = document.getElementById('formPlaceholder');

    loadDataBtn.addEventListener('click', function() {
        const selectedPondId = pondSelector.value;
        if (!selectedPondId) { alert('กรุณาเลือกบ่อก่อน'); return; }

        statusCard.style.display = 'block';
        document.getElementById('latestDate').textContent = 'กำลังโหลด...';
        document.getElementById('latestSize').textContent = '...';
        document.getElementById('currentDays').textContent = '...';

        fetch(`api/get_latest_growth_data.php?pond_id=${selectedPondId}`)
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data) {
                    const data = result.data;
                    document.getElementById('latestDate').textContent = data.latest_record_date;
                    document.getElementById('latestSize').textContent = data.latest_fish_size;
                    document.getElementById('currentDays').textContent = data.current_rearing_day;

                    document.getElementById('hidden_pond_id').value = selectedPondId;
                    document.getElementById('hidden_current_rearing_day').value = data.current_rearing_day;
                    
                    predictionForm.style.display = 'block';
                    formPlaceholder.style.display = 'none';
                } else {
                    document.getElementById('latestDate').textContent = 'ไม่พบข้อมูล';
                    document.getElementById('latestSize').textContent = '-';
                    document.getElementById('currentDays').textContent = '-';
                    predictionForm.style.display = 'none';
                    formPlaceholder.style.display = 'block';
                }
            })
            .catch(error => console.error('Error fetching data:', error));
    });
});
</script>

<?php include 'templates/sidebar_footer.php'; ?>