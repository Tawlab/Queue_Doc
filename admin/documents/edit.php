<?php
session_start();
include '../../config/db.php';

$alert_script = ""; // ตัวแปรเก็บสคริปต์แจ้งเตือน

// รับค่า ID และดึงข้อมูลเก่า
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}
$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) {
    echo "ไม่พบข้อมูล";
    exit();
}

// ดึง Dropdown
$types = $conn->query("SELECT * FROM document_types ORDER BY id ASC");
$depts = $conn->query("SELECT * FROM departments ORDER BY id ASC");

// บันทึกแก้ไข
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doc_no = trim($_POST['document_no']);
    $ext_no = trim($_POST['external_no']);
    $rec_date = $_POST['receive_date'];
    $title = trim($_POST['title']);
    $book_name = trim($_POST['book_name']);
    $type_id = $_POST['document_type_id'];
    $from = trim($_POST['from_source']);
    $to_dept = !empty($_POST['to_department_id']) ? $_POST['to_department_id'] : NULL;
    $status = $_POST['status'];

    // จัดการไฟล์
    $file_path = $doc['file_path']; // ค่าเดิม
    if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] == 0) {
        $ext = pathinfo($_FILES['doc_file']['name'], PATHINFO_EXTENSION);
        $new_name = "doc_" . date('Ymd_His') . "_" . uniqid() . "." . $ext;
        $upload_dir = "../../uploads/";
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir . $new_name)) {
            $file_path = $new_name; // ค่าใหม่
        }
    }

    $sql = "UPDATE documents SET 
            document_no=?, external_no=?, receive_date=?, title=?, book_name=?, 
            document_type_id=?, from_source=?, to_department_id=?, file_path=?, status=? 
            WHERE id=?";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssssisissi", $doc_no, $ext_no, $rec_date, $title, $book_name, $type_id, $from, $to_dept, $file_path, $status, $id);

        if ($stmt->execute()) {
            $alert_script = "
                Swal.fire({
                    title: 'แก้ไขสำเร็จ!',
                    text: 'บันทึกการแก้ไขข้อมูลเรียบร้อยแล้ว',
                    icon: 'success',
                    confirmButtonText: 'ตกลง'
                }).then((result) => {
                    window.location = 'index.php';
                });
            ";
            // อัปเดตข้อมูลในตัวแปร $doc เพื่อให้ฟอร์มแสดงค่าใหม่ทันที
            $doc['document_no'] = $doc_no;
        } else {
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
    <title>แก้ไขเอกสาร</title>
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
            height: 100vh;
            overflow-y: auto;
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
                    <h3><i class="bi bi-pencil-square text-warning"></i> แก้ไขเอกสาร</h3>
                    <a href="index.php" class="btn btn-secondary">ย้อนกลับ</a>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <form method="post" enctype="multipart/form-data">

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label text-primary">เลขที่รับ (ภายใน)</label>
                                    <input type="text" name="document_no" class="form-control" value="<?php echo htmlspecialchars($doc['document_no']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ที่ (ภายนอก)</label>
                                    <input type="text" name="external_no" class="form-control" value="<?php echo htmlspecialchars($doc['external_no']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ลงวันที่</label>
                                    <input type="date" name="receive_date" class="form-control" value="<?php echo $doc['receive_date']; ?>" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">จากหน่วยงาน/บุคคล</label>
                                    <input type="text" name="from_source" class="form-control" value="<?php echo htmlspecialchars($doc['from_source']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ประเภทเอกสาร</label>
                                    <select name="document_type_id" class="form-select" required>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?php echo $t['id']; ?>" <?php echo ($t['id'] == $doc['document_type_id']) ? 'selected' : ''; ?>>
                                                <?php echo $t['type_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">เรื่อง</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($doc['title']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ชื่อหนังสือ</label>
                                <input type="text" name="book_name" class="form-control" value="<?php echo htmlspecialchars($doc['book_name']); ?>">
                            </div>

                            <hr>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">สถานะเอกสาร</label>
                                    <select name="status" class="form-select border-warning">
                                        <option value="pending" <?php echo ($doc['status'] == 'pending') ? 'selected' : ''; ?>>รอดำเนินการ</option>
                                        <option value="process" <?php echo ($doc['status'] == 'process') ? 'selected' : ''; ?>>รับเรื่องแล้ว</option>
                                        <option value="success" <?php echo ($doc['status'] == 'success') ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                                        <option value="cancel" <?php echo ($doc['status'] == 'cancel') ? 'selected' : ''; ?>>ยกเลิก</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ส่งถึงแผนก</label>
                                    <select name="to_department_id" class="form-select">
                                        <option value="">-- ไม่ระบุ (เข้าส่วนกลาง) --</option>
                                        <?php foreach ($depts as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo ($d['id'] == $doc['to_department_id']) ? 'selected' : ''; ?>>
                                                <?php echo $d['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ไฟล์แนบ</label>
                                    <input type="file" name="doc_file" class="form-control">
                                    <?php if ($doc['file_path']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">ไฟล์ปัจจุบัน:
                                                <a href="../../uploads/<?php echo $doc['file_path']; ?>" target="_blank" class="text-decoration-none">
                                                    <i class="bi bi-file-earmark-pdf"></i> เปิดดูไฟล์
                                                </a>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-warning btn-lg text-white"><i class="bi bi-save"></i> บันทึกการแก้ไข</button>
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