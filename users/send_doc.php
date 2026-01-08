<?php
session_start();
include '../config/db.php';
include '../includes/mail_helper.php';

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$alert_script = "";
$my_dept_id = $_SESSION['department_id'] ?? 0;

// ดึงข้อมูล Dropdown
$types = $conn->query("SELECT id, type_name FROM document_types WHERE is_active = 1 ORDER BY id ASC");
$depts = $conn->query("SELECT id, name FROM departments ORDER BY id ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $external_no = trim($_POST['external_no']);
    $title = trim($_POST['title']);
    $book_name = trim($_POST['book_name']);
    $receive_date = $_POST['receive_date'];
    $type_id = $_POST['document_type_id'];
    $from_source = trim($_POST['from_source']);
    $priority = $_POST['priority'] ?? 0;
    $sender_id = $_SESSION['user_id'];
    
    // รับค่าแผนก (อาจจะว่างได้ถ้าเป็นหนังสือเกษียณ)
    $to_depts = $_POST['to_department_ids'] ?? [];
    
    // --- กรณีหนังสือเกษียณ (Type 1) และผู้ส่งคือ ธุรการสำนักปลัด (Dept 2) ---
    if ($type_id == 1 && $my_dept_id == 2) {
        $target_head = 4; // ส่งไปหัวหน้าสำนักปลัด
        $pending_depts_csv = NULL; // ยังไม่มีแผนกปลายทาง (ให้หัวหน้าเลือกทีหลัง)

        // สร้างเลขที่เอกสาร
        $year_th = date('Y') + 543;
        $res_last = $conn->query("SELECT document_no FROM documents WHERE document_no LIKE '%/$year_th' ORDER BY id DESC LIMIT 1");
        $row_last = $res_last->fetch_assoc();
        $new_no = $row_last ? str_pad((int)explode('/', $row_last['document_no'])[0] + 1, 3, '0', STR_PAD_LEFT) : "001";
        $document_no = $new_no . "/" . $year_th;
        
        // จัดการไฟล์แนบ
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $uploaded_files = [];
        if (isset($_FILES['doc_files'])) {
            $countfiles = count($_FILES['doc_files']['name']);
            for ($i = 0; $i < $countfiles; $i++) {
                if ($_FILES['doc_files']['error'][$i] == 0) {
                    $ext = pathinfo($_FILES['doc_files']['name'][$i], PATHINFO_EXTENSION);
                    $new_filename = "DOC_" . date('Ymd_His') . "_" . uniqid() . "." . $ext;
                    if (move_uploaded_file($_FILES['doc_files']['tmp_name'][$i], $upload_dir . $new_filename)) {
                        $uploaded_files[] = ['path' => $new_filename, 'type' => $ext];
                    }
                }
            }
        }
        $main_file_path = !empty($uploaded_files) ? $uploaded_files[0]['path'] : "";

        // Insert
        $sql = "INSERT INTO documents (document_no, external_no, title, book_name, receive_date, sender_id, to_department_id, pending_target_depts, document_type_id, from_source, status, priority, file_path, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssiisisis", $document_no, $external_no, $title, $book_name, $receive_date, $sender_id, $target_head, $pending_depts_csv, $type_id, $from_source, $priority, $main_file_path);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            // บันทึกไฟล์แนบเพิ่ม
            if (count($uploaded_files) > 1) {
                $stmt_att = $conn->prepare("INSERT INTO document_attachments (document_id, file_path, file_type) VALUES (?, ?, ?)");
                for ($k = 1; $k < count($uploaded_files); $k++) {
                    $stmt_att->bind_param("iss", $new_id, $uploaded_files[$k]['path'], $uploaded_files[$k]['type']);
                    $stmt_att->execute();
                }
            }
            // แจ้งเตือนหัวหน้าสำนักปลัด
            $conn->query("INSERT INTO notifications (user_id, title, message) SELECT id, 'หนังสือเกษียณใหม่', 'รอการพิจารณาและกำหนดปลายทาง' FROM users WHERE department_id = 4");
            $alert_script = "Swal.fire('สำเร็จ', 'ส่งเรื่องให้หัวหน้าสำนักปลัดกำหนดแผนกปลายทางแล้ว', 'success').then(() => { window.location='dashboard.php'; });";
        }

    } else {
        // --- กรณีปกติ (ต้องเลือกแผนก) ---
        if (empty($to_depts)) {
            $alert_script = "Swal.fire('ผิดพลาด', 'กรุณาเลือกแผนกปลายทาง', 'error');";
        } else {
            $upload_dir = '../uploads/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            $uploaded_files = [];
            if (isset($_FILES['doc_files'])) {
                $countfiles = count($_FILES['doc_files']['name']);
                for ($i = 0; $i < $countfiles; $i++) {
                    if ($_FILES['doc_files']['error'][$i] == 0) {
                        $ext = pathinfo($_FILES['doc_files']['name'][$i], PATHINFO_EXTENSION);
                        $new_filename = "DOC_" . date('Ymd_His') . "_" . uniqid() . "." . $ext;
                        if (move_uploaded_file($_FILES['doc_files']['tmp_name'][$i], $upload_dir . $new_filename)) {
                            $uploaded_files[] = ['path' => $new_filename, 'type' => $ext];
                        }
                    }
                }
            }
            $main_file_path = !empty($uploaded_files) ? $uploaded_files[0]['path'] : "";

            $success_count = 0;
            foreach ($to_depts as $dept_id) {
                // สร้างเลขที่ใหม่และ Insert (Logic เดิม)
                $year_th = date('Y') + 543;
                $res_last = $conn->query("SELECT document_no FROM documents WHERE document_no LIKE '%/$year_th' ORDER BY id DESC LIMIT 1");
                $row_last = $res_last->fetch_assoc();
                $new_no = $row_last ? str_pad((int)explode('/', $row_last['document_no'])[0] + 1, 3, '0', STR_PAD_LEFT) : "001";
                $document_no = $new_no . "/" . $year_th;

                $sql = "INSERT INTO documents (document_no, external_no, title, book_name, receive_date, sender_id, to_department_id, document_type_id, from_source, status, priority, file_path, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssiiisis", $document_no, $external_no, $title, $book_name, $receive_date, $sender_id, $dept_id, $type_id, $from_source, $priority, $main_file_path);

                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $success_count++;
                    if (count($uploaded_files) > 1) {
                        $stmt_att = $conn->prepare("INSERT INTO document_attachments (document_id, file_path, file_type) VALUES (?, ?, ?)");
                        for ($k = 1; $k < count($uploaded_files); $k++) {
                            $stmt_att->bind_param("iss", $new_id, $uploaded_files[$k]['path'], $uploaded_files[$k]['type']);
                            $stmt_att->execute();
                        }
                    }
                    $conn->query("INSERT INTO notifications (user_id, title, message) SELECT id, 'มีเอกสารใหม่', '$title' FROM users WHERE department_id = $dept_id");
                }
            }
            $alert_script = "Swal.fire('สำเร็จ', 'ส่งเอกสารไปยัง $success_count แผนกแล้ว', 'success').then(() => { window.location='dashboard.php'; });";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ส่งเอกสาร - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <style> body { font-family: 'Kanit', sans-serif; background: #f4f7f6; } </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content flex-grow-1 p-4">
            <div class="container-fluid">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-success text-white p-3 rounded-top-4">
                        <h5 class="mb-0"><i class="bi bi-send-plus me-2"></i> สร้างรายการส่งเอกสาร</h5>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">เลขที่หนังสือภายนอก (ถ้ามี)</label>
                                    <input type="text" name="external_no" class="form-control" placeholder="ระบุเลขที่หนังสือ">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ลงวันที่ <span class="text-danger">*</span></label>
                                    <input type="date" name="receive_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">เรื่อง <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">ประเภทเอกสาร <span class="text-danger">*</span></label>
                                    <select name="document_type_id" id="document_type_id" class="form-select" required>
                                        <?php $types->data_seek(0); while ($row = $types->fetch_assoc()): ?>
                                            <option value="<?= $row['id'] ?>"><?= $row['type_name'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4" id="dept_selector_container">
                                <label class="form-label fw-bold">ส่งถึงแผนก (เลือกได้หลายแผนก) <span class="text-danger">*</span></label>
                                <select name="to_department_ids[]" class="form-select select2-multiple" multiple="multiple">
                                    <?php while ($row = $depts->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-4 d-none" id="dept_hidden_msg">
                                <div class="alert alert-info border-info">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    สำหรับ <strong>หนังสือเกษียณ</strong> ระบบจะส่งต่อให้ <strong>หัวหน้าสำนักปลัด</strong> เป็นผู้กำหนดแผนกปลายทาง
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">รับจากหน่วยงาน/บุคคล <span class="text-danger">*</span></label>
                                    <input type="text" name="from_source" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">รายละเอียดเพิ่มเติม</label>
                                    <input type="text" name="book_name" class="form-control">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">ความเร่งด่วน</label>
                                <div class="d-flex gap-4 p-3 border rounded-3 bg-light">
                                    <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="0" checked id="p0"><label class="form-check-label" for="p0">ปกติ</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="1" id="p1"><label class="form-check-label text-warning fw-bold" for="p1">ด่วน</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="2" id="p2"><label class="form-check-label text-danger fw-bold" for="p2">ด่วนที่สุด</label></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-primary">แนบไฟล์เอกสาร</label>
                                <input type="file" name="doc_files[]" class="form-control" multiple>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-4" onclick="history.back()">ยกเลิก</button>
                                <button type="submit" class="btn btn-success rounded-pill px-5 shadow">บันทึกและส่งเอกสาร</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-multiple').select2({ placeholder: "เลือกแผนกปลายทาง", width: '100%' });

            // Logic ซ่อน/แสดง แผนกปลายทาง
            const myDeptId = <?php echo $my_dept_id; ?>;
            
            function toggleDeptSelector() {
                const typeId = $('#document_type_id').val();
                // ถ้าเป็น ธุรการสำนักปลัด (2) และเลือก หนังสือเกษียณ (1)
                if (typeId == 1 && myDeptId == 2) {
                    $('#dept_selector_container').addClass('d-none');
                    $('#dept_hidden_msg').removeClass('d-none');
                } else {
                    $('#dept_selector_container').removeClass('d-none');
                    $('#dept_hidden_msg').addClass('d-none');
                }
            }

            // เรียกทำงานตอนเปลี่ยนค่า และตอนโหลดหน้า
            $('#document_type_id').change(toggleDeptSelector);
            toggleDeptSelector();
        });
        <?= $alert_script ?>
    </script>
</body>
</html>