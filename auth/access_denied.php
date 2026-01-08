<?php
session_start();
// ตรวจสอบว่ามี referrer หรือไม่
$back_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../users/dashboard.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ไม่มีสิทธิ์เข้าถึง - Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            max-width: 500px;
            width: 90%;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
            padding: 40px;
            border-top: 5px solid #dc3545; /* สีแดงสื่อถึงการปฏิเสธ */
        }
        .error-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
            animation: shake 0.5s;
        }
        .error-code {
            font-size: 24px;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        .error-title {
            font-size: 28px;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 15px;
        }
        .error-message {
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        @keyframes shake {
            0% { transform: translate(1px, 1px) rotate(0deg); }
            10% { transform: translate(-1px, -2px) rotate(-1deg); }
            20% { transform: translate(-3px, 0px) rotate(1deg); }
            30% { transform: translate(3px, 2px) rotate(0deg); }
            40% { transform: translate(1px, -1px) rotate(1deg); }
            50% { transform: translate(-1px, 2px) rotate(-1deg); }
            60% { transform: translate(-3px, 1px) rotate(0deg); }
            100% { transform: translate(0, 0); }
        }
    </style>
</head>
<body>

    <div class="error-card">
        <div class="error-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div class="error-code">403 FORBIDDEN</div>
        <h1 class="error-title">ไม่มีสิทธิ์เข้าถึง</h1>
        <p class="error-message">
            ขออภัย คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้<br>
            หากคุณคิดว่านี่เป็นข้อผิดพลาด กรุณาติดต่อผู้ดูแลระบบ
        </p>

        <div class="d-grid gap-2 col-8 mx-auto">
            <a href="../users/dashboard.php" class="btn btn-primary rounded-pill py-2 shadow-sm">
                <i class="bi bi-house-door-fill me-2"></i>กลับสู่หน้าหลัก
            </a>
            <button onclick="history.back()" class="btn btn-outline-secondary rounded-pill py-2">
                <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
            </button>
        </div>
    </div>

</body>
</html>