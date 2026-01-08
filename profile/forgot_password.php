<?php
session_start();
include '../config/db.php';

// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$alert_script = "";

// เมื่อมีการกดปุ่มเปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // ดึงรหัสผ่านปัจจุบันจากฐานข้อมูลมาตรวจสอบ
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($current_pass, $user['password_hash'])) {
        // ตรวจสอบว่ารหัสใหม่และยืนยันรหัสตรงกันหรือไม่
        if ($new_pass === $confirm_pass) {
            // Hash รหัสผ่านใหม่
            $new_password_hash = password_hash($new_pass, PASSWORD_DEFAULT);

            // อัปเดตรหัสผ่านในฐานข้อมูล
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_password_hash, $user_id);

            if ($update_stmt->execute()) {
                $alert_script = "
                    Swal.fire({
                        title: 'เปลี่ยนรหัสผ่านสำเร็จ!',
                        text: 'กรุณาเข้าสู่ระบบใหม่อีกครั้งด้วยรหัสผ่านใหม่',
                        icon: 'success',
                        confirmButtonColor: '#198754'
                    }).then(() => { window.location = '../auth/logout.php'; });";
            } else {
                $alert_script = "Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเปลี่ยนรหัสผ่านได้ในขณะนี้', 'error');";
            }
        } else {
            $alert_script = "Swal.fire('ข้อมูลไม่ถูกต้อง', 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน', 'error');";
        }
    } else {
        $alert_script = "Swal.fire('รหัสผ่านเดิมผิด', 'รหัสผ่านเดิมที่คุณระบุไม่ถูกต้อง', 'error');";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เปลี่ยนรหัสผ่าน - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f4f7f6;
        }

        .main-content {
            padding: 40px;
            width: 100%;
            height: 100vh;
            overflow-y: auto;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px;
            border-radius: 15px 15px 0 0 !important;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
        }

        .input-group-text {
            background: none;
            border-right: none;
        }

        .form-control {
            border-left: none;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <div class="flex-shrink-0">
            <?php include '../includes/sidebar.php'; ?>
        </div>

        <div class="main-content flex-grow-1">
            <div class="container" style="max-width: 600px;">
                <div class="card mt-4">
                    <div class="card-header text-center">
                        <h4 class="fw-bold mb-0 text-success"><i class="bi bi-shield-lock-fill me-2"></i>เปลี่ยนรหัสผ่านใหม่</h4>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form action="" method="POST">

                            <div class="mb-4">
                                <label class="form-label">รหัสผ่านเดิม</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" name="current_password" class="form-control" placeholder="กรอกรหัสผ่านปัจจุบันของคุณ" required>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="mb-3">
                                <label class="form-label text-primary">รหัสผ่านใหม่</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="new_password" class="form-control" placeholder="ตั้งรหัสผ่านใหม่" minlength="6" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-primary">ยืนยันรหัสผ่านใหม่อีกครั้ง</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-check-circle"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" minlength="6" required>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-success btn-lg shadow-sm">
                                    <i class="bi bi-check-lg me-2"></i> อัปเดตรหัสผ่าน
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
                <p class="text-center mt-4 text-muted small">
                    <i class="bi bi-info-circle me-1"></i> เมื่อเปลี่ยนรหัสผ่านสำเร็จ ระบบจะให้คุณเข้าสู่ระบบใหม่อีกครั้ง
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        <?= $alert_script ?>
    </script>
</body>

</html>