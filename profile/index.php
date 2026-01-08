<?php
session_start();
include '../config/db.php';

// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้จากฐานข้อมูล (Join กับแผนกเพื่อให้ได้ชื่อแผนก)
$sql = "SELECT u.*, d.name AS dept_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ส่วนตัว - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

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

        .profile-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            background: #fff;
        }

        .profile-header {
            background: linear-gradient(45deg, #198754, #20c997);
            height: 120px;
        }

        .avatar-wrapper {
            margin-top: -60px;
            text-align: center;
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid #fff;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #198754;
            font-weight: bold;
            margin: 0 auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .info-label {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .info-value {
            color: #334155;
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .badge-role {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <div class="flex-shrink-0">
            <?php include '../includes/sidebar.php'; ?>
        </div>

        <div class="main-content flex-grow-1">
            <div class="container" style="max-width: 800px;">

                <div class="card profile-card">
                    <div class="profile-header"></div>
                    <div class="card-body p-4 p-md-5">

                        <div class="avatar-wrapper mb-4">
                            <div class="avatar">
                                <?php echo mb_substr($user['first_name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <h3 class="mt-3 fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <span class="badge bg-success badge-role shadow-sm">สิทธิ์การใช้งาน: <?php echo $_SESSION['role']; ?></span>
                        </div>

                        <div class="row g-4 mt-2">
                            <div class="col-md-6 border-end">
                                <h6 class="text-success fw-bold mb-4"><i class="bi bi-person-badge me-2"></i>ข้อมูลบัญชี</h6>

                                <p class="info-label">ชื่อผู้ใช้งาน</p>
                                <p class="info-value"><?php echo htmlspecialchars($user['username']); ?></p>

                                <p class="info-label">อีเมลติดต่อ</p>
                                <p class="info-value text-primary">
                                    <?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : 'ไม่ระบุอีเมล'; ?>
                                </p>
                            </div>

                            <div class="col-md-6 ps-md-4">
                                <h6 class="text-success fw-bold mb-4"><i class="bi bi-building me-2"></i>ข้อมูลหน่วยงาน</h6>

                                <p class="info-label">แผนก / สังกัด</p>
                                <p class="info-value"><?php echo htmlspecialchars($user['dept_name'] ?? 'ไม่ระบุ'); ?></p>

                                <p class="info-label">วันที่เริ่มใช้งาน</p>
                                <p class="info-value"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>

                        <hr class="my-5">

                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="refreshProfile()">
                                <i class="bi bi-arrow-clockwise me-2"></i>รีเฟรช
                            </button>
                            <button type="button" class="btn btn-danger px-4 rounded-pill shadow-sm" onclick="confirmLogout()">
                                <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ฟังก์ชันรีเฟรชข้อมูลพร้อมแสดงแจ้งเตือน
        function refreshProfile() {
            Swal.fire({
                title: 'กำลังอัปเดต...',
                timer: 800,
                timerProgressBar: true,
                didOpen: () => {
                    Swal.showLoading();
                },
                willClose: () => {
                    window.location.reload();
                }
            });
        }

        // ฟังก์ชันยืนยันการออกจากระบบ
        function confirmLogout() {
            Swal.fire({
                title: 'ออกจากระบบ?',
                text: "คุณต้องการออกจากระบบการทำงานใช่หรือไม่",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ใช่, ออกจากระบบ',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../auth/logout.php';
                }
            });
        }
    </script>

</body>

</html>