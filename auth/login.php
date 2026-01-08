<?php
session_start();
require_once '../config/db.php';

// ป้องกันการเข้าหน้า Login ซ้ำถ้า Login อยู่แล้ว
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'Admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../users/dashboard.php");
    }
    exit();
}

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']); 
    $password = $_POST['password'];

    $sql = "SELECT u.*, r.name AS role_name 
            FROM users u 
            LEFT JOIN user_roles ur ON u.id = ur.user_id 
            LEFT JOIN roles r ON ur.role_id = r.id 
            WHERE u.username = ? AND u.is_active = 1 LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // ตรวจสอบ Hash Password
        if (password_verify($password, $row['password_hash'])) {
            
            // สร้าง Session ID ใหม่ป้องกัน Session Fixation
            session_regenerate_id(true);

            // บันทึกข้อมูลลง Session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role_name'];
            
            // เก็บชื่อและนามสกุลแยกกันเพื่อป้องกัน Error ในหน้า send_doc.php
            $_SESSION['first_name'] = $row['first_name']; 
            $_SESSION['last_name'] = $row['last_name'];
            
            // เก็บชื่อเต็มสำหรับแสดงผลใน Sidebar/Dashboard
            $_SESSION['fullname'] = $row['first_name'] . ' ' . $row['last_name'];
            
            // เก็บอีเมลสำหรับระบบแจ้งเตือน
            $_SESSION['email'] = $row['email'];
            
            $_SESSION['department_id'] = $row['department_id'];

            // Redirect ตาม Role 
            if ($row['role_name'] == 'Admin') {
                header("Location: ../admin/dashboard.php");
            } else {
                header("Location: ../users/dashboard.php");
            }
            exit();
        } else {
            // Error Message กว้างๆ
            $error = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง"; 
        }
    } else {
        $error = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบส่งเอกสารภายใน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #2E7D32;
            --light-green: #E8F5E9;
            --gradient-bg: linear-gradient(135deg, #F1F8E9 0%, #C8E6C9 100%);
        }
        
        body {
            font-family: 'Kanit', sans-serif;
            background: var(--gradient-bg);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Background Shapes Decoration */
        .bg-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
        }
        .shape-1 { top: -10%; left: -10%; width: 500px; height: 500px; background: #81C784; }
        .shape-2 { bottom: -10%; right: -10%; width: 400px; height: 400px; background: #4CAF50; }

        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            transition: transform 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
        }

        .brand-icon {
            font-size: 3rem;
            color: var(--primary-green);
            background: var(--light-green);
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: var(--primary-green);
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(46, 125, 50, 0.2);
        }

        .btn-submit {
            background: linear-gradient(90deg, #2E7D32 0%, #43A047 100%);
            border: none;
            border-radius: 50px;
            padding: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: linear-gradient(90deg, #1B5E20 0%, #2E7D32 100%);
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
            transform: scale(1.02);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
    </style>
</head>
<body>

    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>

    <div class="container">
        <div class="login-card mx-auto">
            <div class="text-center mb-4">
                <div class="brand-icon">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
                <h4 class="fw-bold text-secondary">E-Document System</h4>
                <p class="text-muted small">ระบบส่งเอกสารภายในองค์กร</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center rounded-4 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control rounded-4" id="username" name="username" placeholder="Username" required>
                    <label for="username"><i class="bi bi-person me-2"></i>ชื่อผู้ใช้งาน</label>
                </div>

                <div class="form-floating mb-4 position-relative">
                    <input type="password" class="form-control rounded-4" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock me-2"></i>รหัสผ่าน</label>
                    <i class="bi bi-eye-slash password-toggle" id="togglePassword"></i>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="forgot_password.php" class="text-decoration-none small text-success fw-bold">ลืมรหัสผ่าน?</a>
                </div>

                <button type="submit" name="login" class="btn btn-primary w-100 btn-submit text-white">
                    เข้าสู่ระบบ <i class="bi bi-arrow-right-circle ms-2"></i>
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">© 2025 E-Document System V.1.0</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Password Function
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>