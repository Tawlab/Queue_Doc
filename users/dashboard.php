<?php
session_start();
include '../config/db.php';


// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$dept_id = $_SESSION['department_id'];


// ดึงสถิติสำหรับ User
// เอกสารที่ฉันส่ง (ทั้งหมด)
$my_sent = $conn->query("SELECT COUNT(*) as total FROM documents WHERE sender_id = '$user_id'")->fetch_assoc()['total'];
// เอกสารรอดำเนินการที่ส่งถึงแผนกฉัน
$dept_pending = $conn->query("SELECT COUNT(*) as total FROM documents WHERE to_department_id = '$dept_id' AND status = 'pending'")->fetch_assoc()['total'];
// เอกสารที่ดำเนินการเสร็จสิ้นแล้ว (ที่เกี่ยวข้องกับฉัน)
$my_success = $conn->query("SELECT COUNT(*) as total FROM documents WHERE (sender_id = '$user_id' OR to_department_id = '$dept_id') AND status = 'success'")->fetch_assoc()['total'];

// ดึงการแจ้งเตือนล่าสุด 4 รายการ
$recent_notif = $conn->query("SELECT * FROM notifications WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 4");

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบเอกสาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-body: #f0f2f5;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            --accent-color: #6366f1;
        }
        body { 
            font-family: 'Kanit', sans-serif;
            background-color: var(--bg-body);
            color: #334155;
        }
        .main-content { padding: 40px; width: 100%; height: 100vh; overflow-y: auto; }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 24px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        .stat-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; margin-bottom: 15px;
        }
        .notif-item {
            border-left: 4px solid transparent;
            transition: 0.2s;
            cursor: pointer;
        }
        .notif-item:hover { background: #f8fafc; border-left-color: var(--accent-color); }
        .btn-send {
            background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            border: none; border-radius: 12px; padding: 12px 24px;
            color: white; font-weight: 600;
        }
        .btn-send:hover { opacity: 0.9; transform: translateY(-2px); color: white; }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="flex-shrink-0">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">สวัสดี, <?php echo $_SESSION['fullname'] ?? 'เพื่อนร่วมงาน'; ?> ✨</h2>
                    <p class="text-secondary">นี่คือภาพรวมเอกสารและการแจ้งเตือนของคุณในวันนี้</p>
                </div>
                <!-- <a href="send_doc.php" class="btn btn-send shadow">
                    <i class="bi bi-plus-lg me-2"></i> ส่งเอกสารใหม่
                </a> -->
            </div>

            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card glass-card p-4 h-100 border-0">
                        <div class="stat-icon bg-indigo-soft text-indigo" style="background: #e0e7ff; color: #4338ca;">
                            <i class="bi bi-file-earmark-arrow-up"></i>
                        </div>
                        <h6 class="text-secondary fw-bold">เอกสารที่ฉันส่ง</h6>
                        <h2 class="fw-bold mb-0"><?php echo $my_sent; ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card glass-card p-4 h-100 border-0">
                        <div class="stat-icon bg-amber-soft text-amber" style="background: #fef3c7; color: #d97706;">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <h6 class="text-secondary fw-bold">รอดำเนินการ (แผนก)</h6>
                        <h2 class="fw-bold mb-0"><?php echo $dept_pending; ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card glass-card p-4 h-100 border-0">
                        <div class="stat-icon bg-emerald-soft text-emerald" style="background: #d1fae5; color: #059669;">
                            <i class="bi bi-check-all"></i>
                        </div>
                        <h6 class="text-secondary fw-bold">รับเรื่องแล้ว</h6>
                        <h2 class="fw-bold mb-0"><?php echo $my_success; ?></h2>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card glass-card border-0 p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0"><i class="bi bi-bell me-2"></i>การแจ้งเตือนล่าสุด</h5>
                            <a href="../notifications/index.php" class="small text-decoration-none text-indigo">ดูทั้งหมด</a>
                        </div>
                        <div class="notif-list">
                            <?php if($recent_notif->num_rows > 0): ?>
                                <?php while($n = $recent_notif->fetch_assoc()): ?>
                                    <div class="notif-item p-3 mb-2 rounded-3">
                                        <div class="fw-bold small"><?php echo htmlspecialchars($n['title']); ?></div>
                                        <div class="text-secondary small text-truncate"><?php echo htmlspecialchars($n['message']); ?></div>
                                        <div class="text-muted mt-1" style="font-size: 10px;">
                                            <?php echo date('d M Y | H:i', strtotime($n['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted small">ไม่มีการแจ้งเตือนใหม่</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card glass-card border-0 p-4 h-100 bg-white">
                        <h5 class="fw-bold mb-4">เมนูเข้าถึงด่วน</h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="view_incoming.php" class="btn w-100 py-3 border rounded-4 text-start h-100 shadow-sm">
                                    <i class="bi bi-mailbox mb-2 d-block fs-4 text-primary"></i>
                                    <span class="small fw-bold">กล่องเอกสารเข้า</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="../profile/index.php" class="btn w-100 py-3 border rounded-4 text-start h-100 shadow-sm">
                                    <i class="bi bi-person-circle mb-2 d-block fs-4 text-success"></i>
                                    <span class="small fw-bold">ข้อมูลส่วนตัว</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // SweetAlert2 Toast แจ้งเตือนเมื่อ Login สำเร็จ
    document.addEventListener('DOMContentLoaded', function() {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: 'success',
            title: 'เชื่อมต่อระบบสำเร็จ',
            text: 'ยินดีต้อนรับกลับมาครับ/ค่ะ'
        });
    });
</script>

</body>
</html>