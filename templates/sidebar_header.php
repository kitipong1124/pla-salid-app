<?php
// templates/sidebar_header.php
session_start();
include_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'ระบบจัดการฟาร์มปลา' ?></title>

    <link href="<?= BASE_URL ?>node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/custom.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    </head>
<body>

<div class="sidebar">
    <div class="sidebar-header"><i class="bi bi-water"></i> FarmManager</div>
    <ul class="nav flex-column sidebar-nav">
        <li class="nav-item">
            <a class="nav-link" href="index.php"><i class="bi bi-house-door-fill"></i> แดชบอร์ด</a>
        </li>
        <?php
        // เตรียมลิงก์แบบไดนามิก
        $pond_view_link = 'view_ponds.php'; // ค่าเริ่มต้น
        if ($userRole === 'owner' && isset($_SESSION['owned_ponds']) && count($_SESSION['owned_ponds']) === 1) {
            // ทางลัดสำหรับ Owner ที่มีบ่อเดียว
            $pond_view_link = 'pond_detail.php?id=' . $_SESSION['owned_ponds'][0];
        }?>
        <li class="nav-item">
            <a class="nav-link" href="<?= $pond_view_link ?>"><i class="bi bi-layout-text-sidebar-reverse"></i> ภาพรวมบ่อเลี้ยง</a>
        </li>
        <li class="nav-item mt-3"><small class="text-white-50 ps-3">รายงานและสรุปผล</small></li>
        <li class="nav-item"><a class="nav-link" href="profit_loss_report.php"><i class="bi bi-cash-coin"></i> สรุปผลกำไร/ขาดทุน</a></li>
        <li class="nav-item mt-3"><small class="text-white-50 ps-3">เครื่องมือ AI</small></li>
        <li class="nav-item"><a class="nav-link" href="ai_water_lab.php"><i class="bi bi-robot"></i> ทำนายคุณภาพน้ำ</a></li>
        <li class="nav-item"><a class="nav-link" href="ai_growth_advisor.php"><i class="bi bi-graph-up"></i> ทำนายการเติบโต</a></li>
        <li class="nav-item"><a class="nav-link" href="ai_yield_predictor.php"><i class="bi bi-graph-up-arrow"></i> ทำนายผลผลิต</a></li>
        <li class="nav-item mt-3"><small class="text-white-50 ps-3">การจัดการข้อมูล</small></li>
        <li class="nav-item mt"><a class="nav-link" href="add_feeding.php"><i class="bi bi-plus-circle-fill"></i> บันทึกการให้อาหาร</a></li>
        <li class="nav-item"><a class="nav-link" href="view_feeding.php"><i class="bi bi-card-list"></i> ดูข้อมูลให้อาหาร</a></li>
        <li class="nav-item"><a class="nav-link" href="water_Q.php"><i class="bi bi-droplet-fill"></i> บันทึก/ดูการตรวจน้ำ</a></li>
        <li class="nav-item"><a class="nav-link" href="add_fish_size.php"><i class="bi bi-rulers"></i> บันทึก/ดูขนาดปลา</a></li>
        <?php if ($userRole == 'admin'): ?>
        <li class="nav-item mt-3"><small class="text-white-50 ps-3">การตั้งค่าระบบ</small></li>
        <li class="nav-item"><a class="nav-link" href="add_fish_release.php"><i class="bi bi-box-seam-fill"></i> บันทึกข้อมูลลูกปลา</a></li>
        <li class="nav-item"><a class="nav-link" href="add_user.php"><i class="bi bi-person-plus-fill"></i> จัดการผู้ใช้</a></li>
        <li class="nav-item"><a class="nav-link" href="add_pond.php"><i class="bi bi-map-fill"></i> จัดการบ่อปลา</a></li>
        <?php endif; ?>
    </ul>
    <div class="mt-auto">
        <div class="dropup">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle fs-4 me-2"></i>
                <div class="w-100">
                    <strong class="d-block"><?= htmlspecialchars($username) ?></strong>
                    <small><?= htmlspecialchars($userRole) ?></small>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" data-bs-theme="dark">
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
</div>

<main class="main-content">
    <?php
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        echo sprintf(
            '<div class="alert alert-%s alert-dismissible fade show" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>',
            htmlspecialchars($message['type']),
            htmlspecialchars($message['text'])
        );
    }
    ?>