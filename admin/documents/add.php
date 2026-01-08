<?php
session_start();
include '../../config/db.php';

$alert_script = ""; // ตัวแปรเก็บสคริปต์แจ้งเตือน

// ดึงข้อมูล Dropdown
$types = $conn->query("SELECT * FROM document_types ORDER BY id ASC");
$depts = $conn->query("SELECT * FROM departments ORDER BY id ASC");

// บันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_no = trim($_POST['document_no']);
    $ext_no = trim($_POST['external_no']);
    $rec_date = $_POST['receive_date'];
    $title = trim($_POST['title']);
    $book_name = trim($_POST['book_name']);
    $type_id = $_POST['document_type_id'];
    $from = trim($_POST['from_source']);
    $to_dept = !empty($_POST['to_department_id']) ? $_POST['to_department_id'] : NULL;
    $sender_id = $_SESSION['user_id'] ?? 0;

    // อัปโหลดไฟล์
    $file_path = "";
    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] == 0) {
        $ext = pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION);
        $new_name = "doc_" . date('Ymd_His') . "_" . uniqid() . "." . $ext;
        $upload_dir = "../../uploads/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir . $new_name)) {
            $file_path = $new_name;
        }
    }

    // SQL Insert
    $sql = "INSERT INTO documents 
            (document_no, external_no, receive_date, title, book_name, document_type_id, from_source, to_department_id, sender_id, file_path, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssssisiis", $doc_no, $ext_no, $rec_date, $title, $book_name, $type_id, $from, $to_dept, $sender_id, $file_path);

        if ($stmt->execute()) {
            // SweetAlert สำเร็จ -> เด้งกลับหน้า index
            $alert_script = "
                Swal.fire({
                    title: 'บันทึกสำเร็จ!',
                    text: 'ข้อมูลเอกสารถูกบันทึกเรียบร้อยแล้ว',
                    icon: 'success',
                    confirmButtonText: 'ตกลง'
                }).then((result) => {
                    window.location = 'index.php';
                });
            ";
        } else {
            // SweetAlert Error
            $alert_script = "Swal.fire('เกิดข้อผิดพลาด', '" . $stmt->error . "', 'error');";
        }
        $stmt->close();
    } else {
        $alert_script = "Swal.fire('Database Error', '" . $conn->error . "', 'error');";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนรับเอกสารใหม่</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Kanit', sans-serif;
        }

        .main-content {
            width: 100%;
            padding: 20px;
            overflow-y: auto;
            height: 100vh;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <div class="flex-shrink-0">
            <?php include '../../includes/sidebar.php'; ?>
        </div>

        <div class="main-content flex-grow-1">
            <div class="container-fluid" style="max-width: 1000px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold"><i class="bi bi-plus-circle text-success"></i> ลงทะเบียนรับเอกสาร</h3>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">แบบฟอร์มบันทึกข้อมูล</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="post" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label text-primary">เลขที่รับ (ภายใน) <span class="text-danger">*</span></label>
                                    <input type="text" name="document_no" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ที่ (ภายนอก)</label>
                                    <input type="text" name="external_no" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ลงวันที่ <span class="text-danger">*</span></label>
                                    <input type="date" name="receive_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">จากหน่วยงาน/บุคคล</label>
                                    <input type="text" name="from_source" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ประเภทเอกสาร <span class="text-danger">*</span></label>
                                    <select name="document_type_id" class="form-select" required>
                                        <option value="">-- เลือกประเภท --</option>
                                        <?php if ($types): while ($t = $types->fetch_assoc()): ?>
                                                <option value="<?php echo $t['id']; ?>"><?php echo $t['type_name']; ?></option>
                                        <?php endwhile;
                                        endif; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">เรื่อง <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ชื่อหนังสือ / รายละเอียด</label>
                                <input type="text" name="book_name" class="form-control">
                            </div>

                            <hr>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-success">ส่งถึงแผนก (ปลายทาง)</label>
                                    <select name="to_department_id" class="form-select">
                                        <option value="">-- ไม่ระบุ (เข้าส่วนกลาง) --</option>
                                        <?php if ($depts): while ($d = $depts->fetch_assoc()): ?>
                                                <option value="<?php echo $d['id']; ?>"><?php echo $d['name']; ?></option>
                                        <?php endwhile;
                                        endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">แนบไฟล์ (PDF/รูปภาพ)</label>
                                    <input type="file" name="doc_file" class="form-control" accept=".pdf,.jpg,.png">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-save"></i> บันทึกข้อมูล</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        <?php echo $alert_script; ?>
    </script>
</body>

</html>