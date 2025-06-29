<?php
// api/get_latest_growth_data.php (ฉบับแก้ไขที่ถูกต้อง)
header('Content-Type: application/json; charset=utf-8');
include '../config.php'; // ../ เพื่อย้อนกลับไปหาไฟล์ config.php

$response = ['success' => false, 'data' => null, 'error' => 'ไม่ได้ระบุ ID ของบ่อ'];

// รับ pond_id ที่เป็นตัวเลขจากหน้าเว็บ
if (isset($_GET['pond_id']) && is_numeric($_GET['pond_id'])) {
    $pond_id = (int)$_GET['pond_id'];

    try {
        // --- 1. ดึงข้อมูลการสุ่มวัดขนาดปลาล่าสุดของบ่อนี้ ---
        $stmt_size = $conn->prepare("
            SELECT record_date, fish_count 
            FROM fish_sizes 
            WHERE pond_id = ? 
            ORDER BY record_date DESC 
            LIMIT 1
        ");
        $stmt_size->execute([$pond_id]);
        $latest_size_data = $stmt_size->fetch(PDO::FETCH_ASSOC);

        // --- ตรวจสอบว่าพบข้อมูลหรือไม่ ---
        if ($latest_size_data) {
            
            // --- 2. ดึงข้อมูลการปล่อยปลารอบล่าสุดของบ่อนี้ (ที่เกิดขึ้นก่อนหรือในวันเดียวกับที่สุ่มปลา) ---
            $stmt_release = $conn->prepare("
                SELECT release_date 
                FROM fish_releases 
                WHERE pond_id = ? AND release_date <= ? 
                ORDER BY release_date DESC 
                LIMIT 1
            ");
            $stmt_release->execute([$pond_id, $latest_size_data['record_date']]);
            $release_data = $stmt_release->fetch(PDO::FETCH_ASSOC);
            
            $rearing_day = 'N/A';
            if ($release_data) {
                // --- 3. คำนวณระยะเวลาเลี้ยง ---
                $start_date = new DateTime($release_data['release_date']);
                $current_record_date = new DateTime($latest_size_data['record_date']);
                $interval = $start_date->diff($current_record_date);
                $rearing_day = $interval->days;
            }
            
            // --- 4. รวบรวมข้อมูลทั้งหมดเพื่อส่งกลับไป ---
            $response['success'] = true;
            $response['data'] = [
                'latest_record_date' => $latest_size_data['record_date'],
                // VVVV จุดที่แก้ไข VVVV
                'latest_fish_size' => $latest_size_data['fish_count'],
                // ^^^^ จุดที่แก้ไข ^^^^
                'current_rearing_day' => $rearing_day
            ];
            unset($response['error']); // ลบ error message ถ้าสำเร็จ

        } else {
            $response['error'] = 'ไม่พบข้อมูลการสุ่มวัดขนาดของบ่อนี้';
        }

    } catch (PDOException $e) {
        $response['error'] = 'Database Error: ' . $e->getMessage();
    }
}

// ส่งผลลัพธ์กลับไปเป็น JSON ที่รองรับภาษาไทย
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>