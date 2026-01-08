<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์ 
checkPageAccess($conn, 'user_edit');

$error = '';
$success = '';

// ตรวจสอบ ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// ดึงข้อมูล User เดิม 
$sql_user = "SELECT u.*, ur.role_id 
             FROM users u 
             LEFT JOIN user_roles ur ON u.id = ur.user_id 
             WHERE u.id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "<script>alert('ไม่พบข้อมูลผู้ใช้งาน'); window.location='index.php';</script>";
    exit();
}

// ดึงข้อมูลสำหรับ Dropdown
$departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY id ASC");
$roles = mysqli_query($conn, "SELECT * FROM roles ORDER BY id ASC");

// บันทึกการแก้ไข
if (isset($_POST['update_user'])) {
    $username = trim($_POST['username']);
    $new_password = $_POST['new_password']; 
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $email = trim($_POST['email']); 
    $dept_id = $_POST['department_id'];
    $role_id = $_POST['role_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // เช็ค Username ซ้ำ 
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->bind_param("si", $username, $id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $error = "Username นี้มีผู้ใช้งานอื่นใช้แล้ว";
    } else {
        $conn->begin_transaction();
        try {
            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username=?, password_hash=?, first_name=?, last_name=?, email=?, department_id=?, is_active=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssii", $username, $hash, $fname, $lname, $email, $dept_id, $is_active, $id);
            } else {
                $sql = "UPDATE users SET username=?, first_name=?, last_name=?, email=?, department_id=?, is_active=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssiii", $username, $fname, $lname, $email, $dept_id, $is_active, $id);
            }
            $stmt->execute();

            // อัปเดต Role
            $sql_role = "UPDATE user_roles SET role_id = ? WHERE user_id = ?";
            $stmt_role = $conn->prepare($sql_role);
            $stmt_role->bind_param("ii", $role_id, $id);
            $stmt_role->execute();

            $conn->commit();
            $success = "บันทึกข้อมูลเรียบร้อยแล้ว";
            
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
    <title>แก้ไขผู้ใช้งาน - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> body { font-family: 'Kanit', sans-serif; background-color: #f8f9fa; } </style>
</head>
<body>
    <div class="d-flex" style="height: 100vh; overflow: hidden;">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4" style="overflow-y: auto;">
            <div class="container-fluid">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success"><i class="bi bi-pencil-square me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h3>
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4">
                        <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        
                        <?php if($success): ?>
                            <script>
                                Swal.fire({
                                    icon: 'success',
                                    title: 'เรียบร้อย',
                                    text: '<?php echo $success; ?>',
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    window.location.href = 'index.php'; 
                                });
                            </script>
                        <?php endif; ?>

                        <?php if($error): ?>
                            <script>
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ผิดพลาด',
                                    text: '<?php echo $error; ?>'
                                });
                            </script>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">ชื่อจริง</label>
                                    <input type="text" name="first_name" class="form-control rounded-3" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">นามสกุล</label>
                                    <input type="text" name="last_name" class="form-control rounded-3" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">ชื่อผู้ใช้งาน (Username)</label>
                                    <input type="text" name="username" class="form-control rounded-3" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">อีเมล</label>
                                    <input type="email" name="email" class="form-control rounded-3" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="user@example.com">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">รหัสผ่านใหม่ <span class="text-danger small">(เว้นว่างหากไม่ต้องการเปลี่ยน)</span></label>
                                    <input type="password" name="new_password" class="form-control rounded-3" placeholder="กรอกเพื่อเปลี่ยนรหัสผ่าน">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">แผนก/ฝ่าย</label>
                                    <select name="department_id" class="form-select rounded-3" required>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" <?php echo ($user['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo $dept['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted">บทบาท (Role)</label>
                                    <select name="role_id" class="form-select rounded-3" required>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>" <?php echo ($user['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                                <?php echo $role['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo ($user['is_active']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">สถานะเปิดใช้งาน (Active)</label>
                                </div>
                            </div>

                            <hr class="text-secondary opacity-25">

                            <div class="text-end">
                                <button type="submit" name="update_user" class="btn btn-warning text-white rounded-pill px-5 py-2">
                                    <i class="bi bi-save me-1"></i> บันทึกการแก้ไข
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