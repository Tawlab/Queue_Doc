<?php
session_start();
require_once '../config/db.php';

$step = 1; // เริ่มต้นที่ขั้นตอนตรวจสอบ
$error = '';
$success = '';

// ตรวจสอบข้อมูล 
if (isset($_POST['verify_user'])) {
    $username = trim($_POST['username']);
    $firstname = trim($_POST['firstname']);

    $sql = "SELECT id FROM users WHERE username = ? AND first_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $firstname);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $_SESSION['reset_user_id'] = $row['id']; 
        $step = 2; 
    } else {
        $error = "ไม่พบข้อมูลผู้ใช้งานนี้ หรือชื่อไม่ถูกต้อง";
    }
}

// เปลี่ยนรหัสผ่าน
if (isset($_POST['reset_password']) && isset($_SESSION['reset_user_id'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass === $confirm_pass) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $user_id = $_SESSION['reset_user_id'];

        $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hash, $user_id);
        
        if ($stmt->execute()) {
            unset($_SESSION['reset_user_id']); // ล้าง Session
            echo "<script>alert('เปลี่ยนรหัสผ่านสำเร็จ!'); window.location='login.php';</script>";
        } else {
            $error = "เกิดข้อผิดพลาดในการบันทึก";
        }
    } else {
        $step = 2; // อยู่หน้าเดิม
        $error = "รหัสผ่านไม่ตรงกัน";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: linear-gradient(135deg, #F1F8E9 0%, #C8E6C9 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-custom {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }
        .form-control { border-radius: 10px; }
        .btn-green {
            background: #2E7D32; color: white; border-radius: 50px;
        }
        .btn-green:hover { background: #1B5E20; color: white; }
    </style>
</head>
<body>

<div class="container">
    <div class="card-custom p-4 mx-auto">
        <div class="text-center mb-4">
            <h4 class="text-success fw-bold">Reset Password</h4>
            <p class="text-muted small">
                <?php echo ($step == 1) ? 'ยืนยันตัวตนเพื่อเปลี่ยนรหัส' : 'ตั้งรหัสผ่านใหม่'; ?>
            </p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger py-2 small rounded-3"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($step == 1): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted">ชื่อจริง (First Name)</label>
                    <input type="text" name="firstname" class="form-control" placeholder="เช่น Admin" required>
                </div>
                <button type="submit" name="verify_user" class="btn btn-green w-100 py-2">ตรวจสอบ</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted">ยืนยันรหัสผ่าน</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="reset_password" class="btn btn-green w-100 py-2">บันทึกรหัสผ่าน</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="login.php" class="text-secondary small text-decoration-none">กลับหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
</div>

</body>
</html>