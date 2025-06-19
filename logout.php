<?php
// 1. เริ่มต้น Session เสมอ
// เพื่อให้สามารถเข้าถึงและจัดการข้อมูล Session ที่มีอยู่ได้
session_start();

// 2. ล้างข้อมูลทั้งหมดใน Session Array
// คำสั่งนี้จะลบตัวแปรทั้งหมดออกจาก $_SESSION
// เช่น $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'] จะหายไป
session_unset();

// 3. ทำลาย Session ทั้งหมดที่เซิร์ฟเวอร์
// เป็นการลบไฟล์ Session ของผู้ใช้นี้ออกจากเซิร์ฟเวอร์ ทำให้ Session สิ้นสุดลงอย่างสมบูรณ์
session_destroy();

// 4. (ทางเลือก แต่แนะนำอย่างยิ่ง) ล้าง Session Cookie ในฝั่งเบราว์เซอร์
// เพื่อให้แน่ใจว่าเบราว์เซอร์จะลืม Session ID นี้ไปอย่างสมบูรณ์ ป้องกันปัญหาบางอย่างที่อาจเกิดขึ้น
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. ส่งผู้ใช้กลับไปยังหน้า Login
// หลังจากทำลาย Session เรียบร้อยแล้ว ก็ไม่ควรให้ผู้ใช้อยู่ในหน้านี้ต่อไป
header("Location: login.php");
exit(); // จบการทำงานของสคริปต์ทันทีหลังจากการ Redirect เพื่อความปลอดภัย
?>