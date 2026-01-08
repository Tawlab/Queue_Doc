<?php
session_start();
include '../config/db.php';
// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// อัปเดตสถานะแจ้งเตือนทั้งหมดของผู้ใช้นี้เป็น "อ่านแล้ว"
$update_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();

// ลบการแจ้งเตือนที่เก่ากว่า 7 วัน เพื่อไม่ให้หนักฐานข้อมูล
$conn->query("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");

// ดึงข้อมูลการแจ้งเตือนมาแสดงผล
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การแจ้งเตือน - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { 
            font-family: 'Kanit', sans-serif;
            background-color: #f8fafc; 
            color: #334155;
        }
        .main-content { padding: 40px; width: 100%; height: 100vh; overflow-y: auto; }
        
        .notif-card {
            background: #ffffff;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            margin-bottom: 12px;
            border-left: 5px solid #e2e8f0;
        }
        .notif-card:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        /* ไฮไลท์การแจ้งเตือนที่เพิ่งอ่านในเซสชันนี้ */
        .notif-new {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .time-stamp { font-size: 0.75rem; color: #94a3b8; }
        .empty-state { padding: 80px 0; opacity: 0.6; }
    </style>
</head>
<body>

<div class="d-flex">
    <div class="flex-shrink-0">
        <?php include '../includes/sidebar.php'; ?>
    </div>

    <div class="main-content flex-grow-1">
        <div class="container-fluid" style="max-width: 850px;">
            
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1">การแจ้งเตือน</h3>
                    <p class="text-muted mb-0">รายการย้อนหลัง 7 วันที่ส่งถึงคุณ</p>
                </div>
            </div>

            <hr class="mb-4">

            <div class="notification-list">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="card notif-card">
                            <div class="card-body d-flex align-items-start p-3">
                                <div class="icon-circle bg-light text-primary me-3">
                                    <i class="bi bi-chat-left-text"></i>
                                </div>
                                
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($row['title']); ?></h6>
                                        <span class="time-stamp"><i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></span>
                                    </div>
                                    <p class="mb-0 text-secondary small"><?php echo htmlspecialchars($row['message']); ?></p>
                                </div>

                                <div class="ms-3">
                                    <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-sm btn-outline-danger border-0">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center empty-state">
                        <i class="bi bi-bell-slash display-1 mb-3"></i>
                        <h5>ไม่มีรายการแจ้งเตือนใหม่</h5>
                        <p>เมื่อมีเอกสารส่งถึงคุณ ข้อมูลจะปรากฏที่นี่</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // ฟังก์ชันลบการแจ้งเตือน
    function confirmDelete(id) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "รายการนี้จะหายไปจากประวัติของคุณ",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ลบออก',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_notif.php?id=' + id;
            }
        })
    }
</script>

</body>
</html>