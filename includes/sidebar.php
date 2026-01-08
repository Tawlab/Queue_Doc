<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once __DIR__ . '/../config/db.php';

// ดึงข้อมูลพื้นฐานจาก Session
$sidebar_uid = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? 'user';
$first_name = $_SESSION['first_name'] ?? 'User';
$fullname = $_SESSION['fullname'] ?? 'User';
$dept_id = $_SESSION['department_id'] ?? 0;

// ฟังก์ชันเช็คสิทธิ์ (Permission)
if (!function_exists('hasPermission')) {
    function hasPermission($conn, $user_id, $dept_id, $permission_slug)
    {
        $sql = "SELECT p.id FROM permissions p 
                LEFT JOIN role_permissions rp ON p.id = rp.permission_id 
                LEFT JOIN user_roles ur ON rp.role_id = ur.role_id 
                LEFT JOIN department_permissions dp ON p.id = dp.permission_id
                WHERE p.slug = ? 
                AND (ur.user_id = ? OR dp.department_id = ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $permission_slug, $user_id, $dept_id);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}

// นับการแจ้งเตือนที่ยังไม่ได้อ่าน
$unread_count = 0;
if ($sidebar_uid > 0) {
    $sql_count = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("i", $sidebar_uid);
    $stmt_count->execute();
    $res_count = $stmt_count->get_result()->fetch_assoc();
    $unread_count = $res_count['total'] ?? 0;
}

// กำหนด Path และหน้าปัจจุบัน
$base_url = '/queue_document';
$currentPage = basename($_SERVER['PHP_SELF']);
$dashboardLink = ($role == 'Admin') ? "$base_url/admin/dashboard.php" : "$base_url/users/dashboard.php";
?>

<style>
    .sidebar-container {
        width: 280px;
        background: #fff;
        border-right: 1px solid #f0f0f0;
        height: 100vh;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .nav-link-custom {
        color: #4b5563;
        padding: 12px 18px;
        display: flex;
        align-items: center;
        text-decoration: none;
        border-radius: 12px;
        margin: 4px 15px;
        transition: all 0.3s ease;
        position: relative;
        font-size: 0.95rem;
    }

    .nav-link-custom:hover {
        background: #f0fdf4;
        color: #16a34a;
        transform: translateX(5px);
    }

    .nav-link-custom.active {
        background: linear-gradient(135deg, #16a34a, #22c55e);
        color: #fff;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
    }

    .notification-dot {
        position: absolute;
        top: 12px;
        left: 28px;
        width: 10px;
        height: 10px;
        background-color: #ef4444;
        border-radius: 50%;
        border: 2px solid #fff;
        animation: pulse-red 2s infinite;
    }

    @keyframes pulse-red {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    .nav-header {
        font-size: 0.75rem;
        color: #9ca3af;
        padding: 15px 25px 5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
</style>

<div class="sidebar-container d-flex flex-column">
    <a href="<?php echo $dashboardLink; ?>" class="d-flex align-items-center p-4 text-decoration-none border-bottom">
        <div class="bg-success text-white rounded-3 p-2 me-3 shadow-sm">
            <i class="bi bi-file-earmark-text-fill fs-4"></i>
        </div>
        <div>
            <h5 class="m-0 text-dark fw-bold">E-Document</h5>
            <small class="text-muted">Smart Workflow</small>
        </div>
    </a>

    <div class="flex-grow-1 overflow-auto py-3">

        <?php if (
            hasPermission($conn, $sidebar_uid, $dept_id, 'noti') ||
            (hasPermission($conn, $sidebar_uid, $dept_id, 'income') && $role !== 'Admin') || // ซ่อนถ้าเป็น Admin
            (hasPermission($conn, $sidebar_uid, $dept_id, 'send') && $role !== 'Admin') ||   // ซ่อนถ้าเป็น Admin
            (hasPermission($conn, $sidebar_uid, $dept_id, 'forward') && $role !== 'Admin')   // ซ่อนถ้าเป็น Admin
        ): ?>

            <div class="nav-header">เมนูหลัก</div>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'noti')): ?>
                <a href="<?php echo $base_url; ?>/notifications/index.php" class="nav-link-custom <?php echo (strpos($_SERVER['REQUEST_URI'], '/notifications/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-bell me-2"></i> การแจ้งเตือน
                    <?php if ($unread_count > 0): ?> <span class="notification-dot"></span> <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'income') && $role !== 'Admin'): ?>
                <a href="<?php echo $base_url; ?>/users/view_incoming.php" class="nav-link-custom <?php echo ($currentPage == 'view_incoming.php') ? 'active' : ''; ?>">
                    <i class="bi bi-inbox-fill me-2"></i> เอกสารรับเข้า
                </a>
            <?php endif; ?>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'send') && $role !== 'Admin'): ?>
                <a href="<?php echo $base_url; ?>/users/send_doc.php" class="nav-link-custom <?php echo ($currentPage == 'send_doc.php') ? 'active' : ''; ?>">
                    <i class="bi bi-send me-2"></i> ส่งเอกสาร
                </a>
            <?php endif; ?>

            <!-- <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'forward') && $role !== 'Admin'): ?>
                <a href="<?php echo $base_url; ?>/users/forward_doc.php" class="nav-link-custom <?php echo ($currentPage == 'forward_doc.php' || $currentPage == 'forward_list.php') ? 'active' : ''; ?>">
                    <i class="bi bi-share me-2"></i> ส่งต่อเอกสาร
                </a>
            <?php endif; ?> -->
        <?php endif; ?>

        <?php if (
            hasPermission($conn, $sidebar_uid, $dept_id, 'admin_dashboard') ||
            hasPermission($conn, $sidebar_uid, $dept_id, 'search_doc') ||
            hasPermission($conn, $sidebar_uid, $dept_id, 'user_view') ||
            hasPermission($conn, $sidebar_uid, $dept_id, 'dept_view') ||
            hasPermission($conn, $sidebar_uid, $dept_id, 'role_view') ||
            hasPermission($conn, $sidebar_uid, $dept_id, 'permission_view')
        ): ?>

            <div class="nav-header">จัดการระบบ</div>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'admin_dashboard')): ?>
                <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="nav-link-custom <?php echo ($currentPage == 'dashboard.php' && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> แดชบอร์ด
                </a>
            <?php endif; ?>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'search_doc')): ?>
                <a href="<?php echo $base_url; ?>/users/search_doc.php" class="nav-link-custom <?php echo ($currentPage == 'search_doc.php') ? 'active' : ''; ?>">
                    <i class="bi bi-search me-2"></i> ค้นหาเอกสาร
                </a>
            <?php endif; ?>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'user_view')): ?>
                <a href="<?php echo $base_url; ?>/admin/user/index.php" class="nav-link-custom <?php echo (strpos($_SERVER['REQUEST_URI'], '/user/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-people me-2"></i> จัดการผู้ใช้งาน
                </a>
            <?php endif; ?>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'dept_view')): ?>
                <a href="<?php echo $base_url; ?>/admin/dept/index.php" class="nav-link-custom <?php echo (strpos($_SERVER['REQUEST_URI'], '/dept/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-building me-2"></i> จัดการแผนก
                </a>
            <?php endif; ?>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'permission_view')): ?>
                <a href="<?php echo $base_url; ?>/admin/permission/index.php" class="nav-link-custom <?php echo (strpos($_SERVER['REQUEST_URI'], '/permission/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-shield-lock me-2"></i> จัดการสิทธิ์
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (
            hasPermission($conn, $sidebar_uid, $dept_id, 'type_doc_view') ||
            hasPermission($conn, $sidebar_uid, $dept_id, 'document_view')
        ): ?>

            <div class="nav-header">จัดการเอกสาร</div>

            <?php if (hasPermission($conn, $sidebar_uid, $dept_id, 'type_doc_view')): ?>
                <a href="<?php echo $base_url; ?>/admin/type_doc/index.php" class="nav-link-custom <?php echo (strpos($_SERVER['REQUEST_URI'], '/type_doc/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-tags me-2"></i> ประเภทเอกสาร
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="p-3 border-top mt-auto">
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle p-2 rounded-3 hover-bg" data-bs-toggle="dropdown">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px; font-weight: bold;">
                    <?php echo mb_substr($first_name, 0, 1, 'UTF-8'); ?>
                </div>
                <div class="overflow-hidden">
                    <p class="m-0 fw-bold text-truncate" style="max-width: 150px; font-size: 0.9rem;"><?php echo htmlspecialchars($fullname); ?></p>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="min-width: 200px;">
                <li><a class="dropdown-item rounded-2 py-2" href="<?php echo $base_url; ?>/profile/index.php"><i class="bi bi-person-circle me-2 text-primary"></i> ข้อมูลส่วนตัว</a></li>
                <li><a class="dropdown-item rounded-2 py-2" href="<?php echo $base_url; ?>/profile/forgot_password.php"><i class="bi bi-shield-lock me-2 text-warning"></i> เปลี่ยนรหัสผ่าน</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item rounded-2 py-2 text-danger" href="<?php echo $base_url; ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a></li>
            </ul>
        </div>
    </div>
</div>