<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์
checkPageAccess($conn, 'dept_edit');

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);
$error = '';
$success = '';

// ดึงข้อมูลเดิม
$sql = "SELECT * FROM departments WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('ไม่พบข้อมูลแผนก'); window.location='index.php';</script>";
    exit();
}
$dept = $result->fetch_assoc();

// บันทึกการแก้ไข
if (isset($_POST['update_dept'])) {
    $name = trim($_POST['name']);

    if (empty($name)) {
        $error = "กรุณากรอกชื่อแผนก";
    } else {
        // เช็คชื่อซ้ำ
        $check = $conn->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
        $check->bind_param("si", $name, $id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "ชื่อแผนกนี้มีอยู่แล้ว";
        } else {
            $update = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $update->bind_param("si", $name, $id);
            
            if ($update->execute()) {
                $success = "แก้ไขข้อมูลสำเร็จ";
                // อัปเดตข้อมูลในตัวแปรเพื่อแสดงผลล่าสุด
                $dept['name'] = $name;
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึก";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขแผนก - E-Document</title>
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
                    <h3 class="fw-bold text-success"><i class="bi bi-pencil-square me-2"></i>แก้ไขแผนก</h3>
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4 shadow-sm">
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
                                }).then(() => { window.location.href = 'index.php'; });
                            </script>
                        <?php endif; ?>

                        <?php if($error): ?>
                            <script>
                                Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: '<?php echo $error; ?>' });
                            </script>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-8 mx-auto">
                                    <div class="mb-4">
                                        <label class="form-label text-muted">ชื่อแผนก/หน่วยงาน</label>
                                        <input type="text" name="name" class="form-control form-control-lg rounded-3" value="<?php echo htmlspecialchars($dept['name']); ?>" required>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="update_dept" class="btn btn-warning text-white btn-lg rounded-pill shadow-sm">
                                            <i class="bi bi-save me-2"></i>บันทึกการแก้ไข
                                        </button>
                                    </div>
                                </div>
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