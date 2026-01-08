<?php
session_start();
include '../config/db.php';
include '../includes/mail_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$alert_script = "";
$my_dept_id = $_SESSION['department_id'] ?? 0;
$sender_id = $_SESSION['user_id'];

// รับค่า Draft ID
$draft_id = isset($_GET['draft_id']) ? intval($_GET['draft_id']) : 0;
$doc_data = [];
$attached_files = [];

if ($draft_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? AND sender_id = ? AND status = 'draft'");
    $stmt->bind_param("ii", $draft_id, $sender_id);
    $stmt->execute();
    $doc_data = $stmt->get_result()->fetch_assoc();
    
    if ($doc_data) {
        $stmt_att = $conn->prepare("SELECT * FROM document_attachments WHERE document_id = ?");
        $stmt_att->bind_param("i", $draft_id);
        $stmt_att->execute();
        $attached_files = $stmt_att->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        header("Location: send_doc.php");
        exit();
    }
}

$types = $conn->query("SELECT id, type_name FROM document_types WHERE is_active = 1 ORDER BY id ASC");
$depts = $conn->query("SELECT id, name FROM departments ORDER BY id ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $external_no = trim($_POST['external_no']);
    $title = trim($_POST['title']);
    $book_name = trim($_POST['book_name']);
    $receive_date = $_POST['receive_date'];
    $type_id = $_POST['document_type_id'];
    $from_source = trim($_POST['from_source']);
    $priority = $_POST['priority'] ?? 0;
    $to_depts = $_POST['to_department_ids'] ?? [];

    // จัดการอัปโหลดไฟล์
    $upload_dir = '../uploads/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $new_uploaded_files = [];
    
    if (isset($_FILES['doc_files'])) {
        $countfiles = count($_FILES['doc_files']['name']);
        for ($i = 0; $i < $countfiles; $i++) {
            if ($_FILES['doc_files']['error'][$i] == 0) {
                $ext = pathinfo($_FILES['doc_files']['name'][$i], PATHINFO_EXTENSION);
                $new_filename = "DOC_" . date('Ymd_His') . "_" . uniqid() . "." . $ext;
                if (move_uploaded_file($_FILES['doc_files']['tmp_name'][$i], $upload_dir . $new_filename)) {
                    $new_uploaded_files[] = ['path' => $new_filename, 'type' => $ext];
                }
            }
        }
    }

    if (isset($_POST['delete_files'])) {
        foreach ($_POST['delete_files'] as $del_id) {
            $conn->query("DELETE FROM document_attachments WHERE id = " . intval($del_id));
        }
    }

    // --- LOGIC 1: บันทึกร่าง (Save Draft) ---
    if ($action == 'save_draft') {
        $document_no = $doc_data['document_no'] ?? "";
        if (empty($document_no)) {
            $year_th = date('Y') + 543;
            $res_last = $conn->query("SELECT document_no FROM documents WHERE document_no LIKE '%/$year_th' ORDER BY id DESC LIMIT 1");
            $row_last = $res_last->fetch_assoc();
            $new_no = $row_last ? str_pad((int)explode('/', $row_last['document_no'])[0] + 1, 3, '0', STR_PAD_LEFT) : "001";
            $document_no = $new_no . "/" . $year_th;
        }

        $main_file_path = $doc_data['file_path'] ?? "";
        if (!empty($new_uploaded_files)) {
            $main_file_path = $new_uploaded_files[0]['path'];
        }

        $to_dept_placeholder = NULL; 

        if ($draft_id > 0) {
            $sql = "UPDATE documents SET external_no=?, title=?, book_name=?, receive_date=?, document_type_id=?, from_source=?, priority=?, file_path=?, updated_at=NOW() WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssisisi", $external_no, $title, $book_name, $receive_date, $type_id, $from_source, $priority, $main_file_path, $draft_id);
            $stmt->execute();
            $target_id = $draft_id;
        } else {
            $sql = "INSERT INTO documents (document_no, external_no, title, book_name, receive_date, sender_id, to_department_id, document_type_id, from_source, status, priority, file_path, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            // [แก้ไขจุดที่ Error] เปลี่ยนจาก "sssssiisis" เป็น "sssssiiisis" (11 ตัวแปร)
            $stmt->bind_param("sssssiiisis", $document_no, $external_no, $title, $book_name, $receive_date, $sender_id, $to_dept_placeholder, $type_id, $from_source, $priority, $main_file_path);
            $stmt->execute();
            $target_id = $conn->insert_id;
        }

        if (!empty($new_uploaded_files)) {
            $stmt_att = $conn->prepare("INSERT INTO document_attachments (document_id, file_path, file_type) VALUES (?, ?, ?)");
            foreach ($new_uploaded_files as $index => $file) {
                if ($draft_id == 0 && $index == 0) continue;
                $stmt_att->bind_param("iss", $target_id, $file['path'], $file['type']);
                $stmt_att->execute();
            }
        }

        $alert_script = "Swal.fire('สำเร็จ', 'บันทึกแบบร่างเรียบร้อยแล้ว', 'success').then(() => { window.location='view_incoming.php?filter=draft'; });";
    }

    // --- LOGIC 2: ส่งเอกสารจริง (Send) ---
    elseif ($action == 'send_doc') {
        if (empty($to_depts)) {
            $alert_script = "Swal.fire('แจ้งเตือน', 'กรุณาเลือกแผนกปลายทาง', 'warning');";
        } else {
            $success_count = 0;
            $final_attachments = [];
            
            if ($draft_id > 0) {
                $res_old_att = $conn->query("SELECT * FROM document_attachments WHERE document_id = $draft_id");
                while($row = $res_old_att->fetch_assoc()) {
                    $final_attachments[] = ['path' => $row['file_path'], 'type' => $row['file_type']];
                }
                if (!empty($doc_data['file_path'])) {
                    if (empty($new_uploaded_files)) {
                        $main_file_to_save = $doc_data['file_path'];
                    } else {
                        $final_attachments[] = ['path' => $doc_data['file_path'], 'type' => 'old_main'];
                        $main_file_to_save = $new_uploaded_files[0]['path'];
                        array_shift($new_uploaded_files);
                    }
                } else {
                    $main_file_to_save = !empty($new_uploaded_files) ? $new_uploaded_files[0]['path'] : "";
                    if(!empty($new_uploaded_files)) array_shift($new_uploaded_files);
                }
            } else {
                $main_file_to_save = !empty($new_uploaded_files) ? $new_uploaded_files[0]['path'] : "";
                if(!empty($new_uploaded_files)) array_shift($new_uploaded_files);
            }

            foreach ($new_uploaded_files as $f) {
                $final_attachments[] = $f;
            }

            foreach ($to_depts as $dept_id) {
                $year_th = date('Y') + 543;
                $res_last = $conn->query("SELECT document_no FROM documents WHERE document_no LIKE '%/$year_th' ORDER BY id DESC LIMIT 1");
                $row_last = $res_last->fetch_assoc();
                $new_no = $row_last ? str_pad((int)explode('/', $row_last['document_no'])[0] + 1, 3, '0', STR_PAD_LEFT) : "001";
                $document_no = $new_no . "/" . $year_th;

                $sql = "INSERT INTO documents (document_no, external_no, title, book_name, receive_date, sender_id, to_department_id, document_type_id, from_source, status, priority, file_path, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                // จุดนี้ถูกต้องอยู่แล้ว (11 ตัวแปร = sssssiiisis)
                $stmt->bind_param("sssssiiisis", $document_no, $external_no, $title, $book_name, $receive_date, $sender_id, $dept_id, $type_id, $from_source, $priority, $main_file_to_save);

                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $success_count++;
                    if (!empty($final_attachments)) {
                        $stmt_att = $conn->prepare("INSERT INTO document_attachments (document_id, file_path, file_type) VALUES (?, ?, ?)");
                        foreach ($final_attachments as $att) {
                            $stmt_att->bind_param("iss", $new_id, $att['path'], $att['type']);
                            $stmt_att->execute();
                        }
                    }
                    $conn->query("INSERT INTO notifications (user_id, title, message) SELECT id, 'มีเอกสารใหม่', '$title' FROM users WHERE department_id = $dept_id");
                }
            }

            if ($success_count > 0) {
                if ($draft_id > 0) {
                    $conn->query("DELETE FROM documents WHERE id = $draft_id");
                    $conn->query("DELETE FROM document_attachments WHERE document_id = $draft_id");
                }
                $alert_script = "Swal.fire('สำเร็จ', 'ส่งเอกสารไปยัง $success_count แผนกแล้ว', 'success').then(() => { window.location='dashboard.php'; });";
            }
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
                    <div class="card-header bg-success text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-send-plus me-2"></i> <?php echo ($draft_id > 0) ? 'แก้ไขแบบร่าง / ส่งเอกสาร' : 'สร้างรายการส่งเอกสาร'; ?></h5>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" id="form_action" value="send_doc">

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">เลขที่หนังสือภายนอก</label>
                                    <input type="text" name="external_no" class="form-control" value="<?php echo $doc_data['external_no'] ?? ''; ?>" placeholder="ระบุเลขที่หนังสือ">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ลงวันที่ <span class="text-danger">*</span></label>
                                    <input type="date" name="receive_date" class="form-control" value="<?php echo $doc_data['receive_date'] ?? date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">เรื่อง <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" value="<?php echo $doc_data['title'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">ประเภทเอกสาร <span class="text-danger">*</span></label>
                                    <select name="document_type_id" id="document_type_id" class="form-select" required>
                                        <?php 
                                        $types->data_seek(0); 
                                        $current_type = $doc_data['document_type_id'] ?? '';
                                        while ($row = $types->fetch_assoc()): 
                                        ?>
                                            <option value="<?= $row['id'] ?>" <?= ($current_type == $row['id']) ? 'selected' : ''; ?>><?= $row['type_name'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">รับจากหน่วยงาน/บุคคล <span class="text-danger">*</span></label>
                                    <input type="text" name="from_source" class="form-control" value="<?php echo $doc_data['from_source'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">รายละเอียดเพิ่มเติม</label>
                                    <input type="text" name="book_name" class="form-control" value="<?php echo $doc_data['book_name'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="mb-4 p-3 bg-light border rounded-3">
                                <label class="form-label fw-bold text-primary"><i class="bi bi-building me-2"></i>เลือกแผนกปลายทาง (สำหรับการส่ง)</label>
                                <select name="to_department_ids[]" class="form-select select2-multiple" multiple="multiple">
                                    <?php while ($row = $depts->fetch_assoc()): ?>
                                        <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="form-text">* หากกด "บันทึกร่าง" ยังไม่ต้องเลือกแผนกก็ได้</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">ความเร่งด่วน</label>
                                <div class="d-flex gap-4 p-3 border rounded-3">
                                    <?php $p = $doc_data['priority'] ?? 0; ?>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="0" <?= $p==0 ? 'checked' : '' ?> id="p0"><label class="form-check-label" for="p0">ปกติ</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="1" <?= $p==1 ? 'checked' : '' ?> id="p1"><label class="form-check-label text-warning fw-bold" for="p1">ด่วน</label></div>
                                    <div class="form-check"><input class="form-check-input" type="radio" name="priority" value="2" <?= $p==2 ? 'checked' : '' ?> id="p2"><label class="form-check-label text-danger fw-bold" for="p2">ด่วนที่สุด</label></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-primary">แนบไฟล์เอกสาร</label>
                                
                                <?php if ($draft_id > 0 && (!empty($doc_data['file_path']) || !empty($attached_files))): ?>
                                    <div class="mb-3 p-3 border rounded bg-white">
                                        <h6>ไฟล์แนบเดิม:</h6>
                                        <?php if (!empty($doc_data['file_path'])): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="bi bi-file-earmark-text me-2"></i> ไฟล์หลัก 
                                                <a href="../uploads/<?= $doc_data['file_path'] ?>" target="_blank" class="ms-2 btn btn-sm btn-outline-info">ดู</a>
                                            </div>
                                        <?php endif; ?>
                                        <?php foreach ($attached_files as $f): ?>
                                            <div class="d-flex align-items-center mb-2">
                                                <input type="checkbox" name="delete_files[]" value="<?= $f['id'] ?>" class="form-check-input me-2">
                                                <i class="bi bi-paperclip me-2"></i> ไฟล์แนบเสริม
                                                <a href="../uploads/<?= $f['file_path'] ?>" target="_blank" class="ms-2 btn btn-sm btn-outline-info">ดู</a>
                                                <span class="ms-2 text-danger small">(ติ๊กเพื่อลบ)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <input type="file" name="doc_files[]" class="form-control" multiple>
                                <div class="form-text">สามารถแนบไฟล์เพิ่มได้ หรืออัปโหลดเพื่อแทนที่ไฟล์เดิม</div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                                <button type="button" class="btn btn-light rounded-pill px-4" onclick="history.back()">ยกเลิก</button>
                                <button type="button" onclick="submitForm('save_draft')" class="btn btn-warning rounded-pill px-4 text-dark shadow-sm">
                                    <i class="bi bi-save me-2"></i> บันทึกร่าง
                                </button>
                                <button type="button" onclick="submitForm('send_doc')" class="btn btn-success rounded-pill px-5 shadow">
                                    <i class="bi bi-send-fill me-2"></i> ส่งเอกสาร
                                </button>
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
        });

        function submitForm(action) {
            document.getElementById('form_action').value = action;
            if (action == 'send_doc') {
                var depts = $('select[name="to_department_ids[]"]').val();
                if (!depts || depts.length === 0) {
                    Swal.fire('แจ้งเตือน', 'กรุณาเลือกแผนกปลายทางก่อนกดส่ง', 'warning');
                    return;
                }
            }
            $('form').submit();
        }
        <?= $alert_script ?>
    </script>
</body>
</html>