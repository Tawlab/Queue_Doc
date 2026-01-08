<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์ 
checkPageAccess($conn, 'user_add');

$error = '';
$success = '';

// ดึงข้อมูลแผนกและบทบาทสำหรับ Dropdown
$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY id ASC");
$roles = mysqli_query($conn, "SELECT * FROM roles ORDER BY id ASC");

if (isset($_POST['save_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $email = trim($_POST['email']); 
    $dept_id = $_POST['department_id'];
    $role_id = $_POST['role_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // 1เช็ค Username ซ้ำ
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "Username นี้มีผู้ใช้งานแล้ว";
    } else {
        // Hash Password & Insert User
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $conn->begin_transaction(); 
        try {
            $sql_user = "INSERT INTO users (username, password_hash, first_name, last_name, email, department_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_user);
            $stmt->bind_param("sssssii", $username, $password_hash, $fname, $lname, $email, $dept_id, $is_active);
            $stmt->execute();
            $new_user_id = $conn->insert_id;

            // Insert Role
            $sql_role = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
            $stmt_role = $conn->prepare($sql_role);
            $stmt_role->bind_param("ii", $new_user_id, $role_id);
            $stmt_role->execute();

            $conn->commit(); 
            $success = "เพิ่มผู้ใช้งานสำเร็จ";
        } catch (Exception $e) {
            $conn->rollback(); 
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>เพิ่มผู้ใช้งาน - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="d-flex" style="height: 100vh; overflow: hidden;">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4" style="overflow-y: auto;">
            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success"><i class="bi bi-person-plus-fill me-2"></i>เพิ่มผู้ใช้งานใหม่</h3>
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4">
                        <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">

                        <?php if ($error): ?>
                            <script>
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: '<?php echo $error; ?>'
                                })
                            </script>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <script>
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: '<?php echo $success; ?>',
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    window.location.href = 'index.php';
                                });
                            </script>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">ชื่อจริง</label>
                                    <input type="text" name="first_name" class="form-control rounded-3" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">นามสกุล</label>
                                    <input type="text" name="last_name" class="form-control rounded-3" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">ชื่อผู้ใช้งาน (Username)</label>
                                    <input type="text" name="username" class="form-control rounded-3" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">อีเมล (สำหรับรับแจ้งเตือน)</label>
                                    <input type="email" name="email" class="form-control rounded-3" placeholder="user@example.com">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">รหัสผ่าน</label>
                                    <input type="password" name="password" class="form-control rounded-3" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">แผนก/ฝ่าย</label>
                                    <select name="department_id" class="form-select rounded-3" required>
                                        <option value="">-- เลือกแผนก --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>"><?php echo $dept['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">บทบาท (Role)</label>
                                    <select name="role_id" class="form-select rounded-3" required>
                                        <option value="">-- เลือกบทบาท --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                    <label class="form-check-label" for="isActive">เปิดใช้งานทันที</label>
                                </div>
                            </div>

                            <hr class="text-secondary opacity-25">

                            <div class="text-end">
                                <button type="submit" name="save_user" class="btn btn-success rounded-pill px-5 py-2">
                                    <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>