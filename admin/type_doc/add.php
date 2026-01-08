<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์ 
checkPageAccess($conn, 'type_doc_add');

$error = '';
$success = '';

// ตรวจสอบว่ามีการส่งข้อมูลแบบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_type'])) {
    
    $type_name = trim($_POST['type_name'] ?? '');

    if (empty($type_name)) {
        $error = "กรุณากรอกชื่อประเภทเอกสาร";
    } else {
        // เช็คชื่อซ้ำในฐานข้อมูล
        $check = $conn->prepare("SELECT id FROM document_types WHERE type_name = ?");
        $check->bind_param("s", $type_name);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "ชื่อประเภทเอกสารนี้มีอยู่แล้ว";
        } else {
            // บันทึกข้อมูล
            $sql = "INSERT INTO document_types (type_name) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $type_name);
            
            if ($stmt->execute()) {
                $success = "เพิ่มประเภทเอกสารสำเร็จ";
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึก: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มประเภทเอกสาร - E-Document</title>
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
                    <h3 class="fw-bold text-success"><i class="bi bi-file-earmark-plus-fill me-2"></i>เพิ่มประเภทเอกสาร</h3>
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-5">

                        <?php if($success): ?>
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
                            <div class="row justify-content-center">
                                <div class="col-md-8">
                                    <div class="mb-4">
                                        <label for="name" class="form-label text-muted">ชื่อประเภทเอกสาร</label>
                                        <input type="text" id="type_name" name="type_name" class="form-control form-control-lg rounded-3" placeholder="เช่น หนังสือภายใน, คำสั่ง, ประกาศ" required autofocus>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="save_type" class="btn btn-success btn-lg rounded-pill shadow-sm">
                                            <i class="bi bi-save me-2"></i> บันทึกข้อมูล
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