<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkPageAccess($conn, $slug) {
    // ตรวจสอบว่า Login หรือยัง
    if (!isset($_SESSION['user_id'])) {
        // ถ้ายังไม่ Login ให้ดีดไปหน้า Login (ใช้ Absolute Path)
        header("Location: /queue_document/auth/login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // เรียกใช้ hasPermission เพื่อตรวจสอบสิทธิ์
    if (!hasPermission($conn, $user_id, $slug)) {
        // ถ้าไม่มีสิทธิ์ ให้แจ้งเตือนและดีดกลับ
        echo "<script>
            alert('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ ($slug)');
            if(window.history.length > 1) {
                window.history.back(); // กลับหน้าก่อนหน้า
            } else {
                window.location.href = '/queue_document/index.php'; // หรือกลับหน้าแรก
            }
        </script>";
        exit(); // หยุดการทำงานทันที
    }
}

function hasPermission($conn, $user_id, $slug) {
    // 1ถ้าเป็น Admin ให้ผ่านตลอด 
    // ตรวจสอบจาก Session เพื่อความรวดเร็ว
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
        return true;
    }

    // ถ้าไม่ใช่ Admin ให้เช็คสิทธิ์จากฐานข้อมูล
    $sql = "SELECT COUNT(*) as cnt 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ? AND p.slug = ?";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $slug);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        // ถ้า cnt > 0 แสดงว่ามีสิทธิ์
        return ($result['cnt'] > 0);
    }

    // กรณี Query ผิดพลาด ให้ถือว่าไม่มีสิทธิ์ไว้ก่อน
    return false;
}

function isActive($keyword) {
    return (strpos($_SERVER['REQUEST_URI'], $keyword) !== false) ? 'active' : '';
}
?>