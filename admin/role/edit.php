<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

checkPageAccess($conn, 'role_edit');

if (!isset($_GET['id'])) { header("Location: index.php"); exit(); }
$id = intval($_GET['id']);
$success = ''; $error = '';

// ดึงข้อมูลเดิม
$sql = "SELECT * FROM roles WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();

if (!$role) {
    echo "<script>alert('ไม่พบข้อมูล'); window.location='index.php';</script>";
    exit();
}

if (isset($_POST['update_role'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    // เช็คชื่อซ้ำ (ยกเว้นตัวเอง)
    $check = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
    $check->bind_param("si", $name, $id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $error = "ชื่อบทบาทนี้มีอยู่แล้ว";
    } else {
        $sql = "UPDATE roles SET name = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $description, $id);
        
        if ($stmt->execute()) {
            $success = "แก้ไขข้อมูลสำเร็จ";
            $role['name'] = $name; // อัปเดตค่าแสดงผล
            $role['description'] = $description;
        } else {
            $error = "เกิดข้อผิดพลาดในการบันทึก";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขบทบาท - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> body { font-family: 'Kanit', sans-serif; background-color: #f8f9fa; } </style>
</head>
<body>
    <div class="d-flex" style="height: 100vh;">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="flex-grow-1 p-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success">แก้ไขบทบาท</h3>
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <?php if($success): ?>
                        <script>
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: '<?php echo $success; ?>', showConfirmButton: false, timer: 1500 })
                            .then(() => { window.location.href = 'index.php'; });
                        </script>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <script>Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: '<?php echo $error; ?>' });</script>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted">ชื่อบทบาท</label>
                            <input type="text" name="name" class="form-control rounded-3" value="<?php echo htmlspecialchars($role['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">คำอธิบาย</label>
                            <textarea name="description" class="form-control rounded-3" rows="3"><?php echo htmlspecialchars($role['description']); ?></textarea>
                        </div>
                        <button type="submit" name="update_role" class="btn btn-warning text-white rounded-pill px-5">บันทึกการแก้ไข</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>