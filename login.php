<?php
session_start();
// เราต้อง include config.php ก่อน เพื่อให้รู้จัก BASE_URL
include 'config.php';

// หากผู้ใช้ล็อกอินอยู่แล้ว ให้ redirect ไปยังหน้า dashboard ทันที
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'owner') {
                $stmt_ponds = $conn->prepare("SELECT pond_id FROM user_ponds WHERE user_id = ?");
                $stmt_ponds->execute([$user['id']]);
                $owned_ponds_data = $stmt_ponds->fetchAll(PDO::FETCH_COLUMN);
                $_SESSION['owned_ponds'] = $owned_ponds_data;
            }
            
            header("Location: index.php");
            exit();
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบจัดการฟาร์มปลา</title>

    <link href="<?= BASE_URL ?>node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        html, body {
            height: 100%;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e9ecef;
        }
        .login-card {
            max-width: 450px;
            width: 100%;
            padding: 15px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card shadow-lg border-0 rounded-lg">
        <div class="card-header bg-primary text-white text-center py-3">
            <h3 class="mb-0 fw-bold"><i class="bi bi-water"></i> FarmManager</h3>
        </div>
        <div class="card-body p-4 p-sm-5">
            <h4 class="card-title text-center mb-4">ลงชื่อเข้าใช้งาน</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label for="username" class="form-label">ชื่อผู้ใช้</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Username" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">รหัสผ่าน</label>
                     <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold">
                        เข้าสู่ระบบ
                    </button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center py-3">
            <div class="small">© <?= date("Y") ?> ระบบจัดการฟาร์มปลา</div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>