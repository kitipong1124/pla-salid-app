<?php
// templates/get_latest_water_data.php
// ไฟล์นี้ทำหน้าที่เป็น API เล็กๆ สำหรับดึงข้อมูลล่าสุดของบ่อ
header('Content-Type: application/json');
include '../config.php';

$response = ['success' => false, 'data' => null];

if (isset($_GET['pond_id']) && is_numeric($_GET['pond_id'])) {
    $pond_id = (int)$_GET['pond_id'];

    $stmt = $conn->prepare("SELECT ph, ammonium, nitrite FROM water_quality WHERE pond_id = ? ORDER BY check_date DESC, id DESC LIMIT 1");
    $stmt->execute([$pond_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        $response['success'] = true;
        $response['data'] = $data;
    }
}

echo json_encode($response);