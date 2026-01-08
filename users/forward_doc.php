<?php
session_start();
include '../config/db.php';
include '../includes/mail_helper.php';

// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$my_dept_id = $_SESSION['department_id'] ?? 0;
$alert_script = "";
$is_process_done = false;

// --- จัดการ Action ---

// ปิดงาน (Archive)
if (isset($_GET['action']) && $_GET['action'] == 'archive' && isset($_GET['doc_id'])) {
    $target_id = intval($_GET['doc_id']);
    $stmt_close = $conn->prepare("UPDATE documents SET status = 'archive' WHERE id = ? AND to_department_id = ?");
    $stmt_close->bind_param("ii", $target_id, $my_dept_id);
    if ($stmt_close->execute()) {
        $alert_script = "Swal.fire('สำเร็จ', 'ปิดรายการเอกสารเรียบร้อยแล้ว', 'success').then(() => { window.location='forward_doc.php'; });";
        $is_process_done = true;
    }
}

// ส่งฟอร์มส่งต่อ (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['id'])) {
    $doc_id = intval($_GET['id']);
    
    // ดึงข้อมูลเดิม
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $orig_doc = $stmt->get_result()->fetch_assoc();

    if ($orig_doc) {
        $workflow_step = "normal";
        if ($orig_doc['document_type_id'] == 1) { 
            if ($my_dept_id == 4) $workflow_step = "head_office"; 
            elseif ($my_dept_id == 5) $workflow_step = "palad";
            elseif ($my_dept_id == 6) $workflow_step = "mayor";
            elseif ($my_dept_id == 2) $workflow_step = "admin_office";
        }

        $forward_remark = trim($_POST['forward_remark']);
        $actions = $_POST['actions'] ?? [];
        $action_text = !empty($actions) ? implode(", ", $actions) : "-";
        $sender_name = $_SESSION['fullname'] ?? 'เจ้าหน้าที่';
        
        $timestamp = date('d/m/Y H:i');
        if ($workflow_step == 'admin_office') {
             $new_log = "\n[$timestamp] $sender_name (ธุรการสำนักปลัด):\n- หมายเหตุ: $forward_remark";
        } else {
             $new_log = "\n[$timestamp] $sender_name:\n- ความเห็น: $action_text\n- หมายเหตุ: $forward_remark";
        }
        $full_remark = $orig_doc['remark'] . $new_log;

        $actual_targets_to_insert = []; 
        $next_pending_depts_val = $orig_doc['pending_target_depts'];

        // Logic เลือกปลายทาง
        if ($workflow_step == 'head_office') {
            $selected_targets = $_POST['to_department_ids'] ?? [];
            if (empty($selected_targets)) { $alert_script = "Swal.fire('แจ้งเตือน', 'กรุณาเลือกแผนก', 'warning');"; goto end_post; }
            $next_pending_depts_val = implode(',', $selected_targets);
            $actual_targets_to_insert = [5]; // ส่งไปปลัด
        } elseif ($workflow_step == 'admin_office') {
            $selected_targets = $_POST['to_department_ids'] ?? [];
            if (empty($selected_targets)) { $alert_script = "Swal.fire('แจ้งเตือน', 'กรุณาเลือกแผนก', 'warning');"; goto end_post; }
            $actual_targets_to_insert = $selected_targets;
            $next_pending_depts_val = NULL; 
        } else {
            $actual_targets_to_insert = $_POST['to_department_ids'] ?? [];
             if (empty($actual_targets_to_insert)) { $alert_script = "Swal.fire('แจ้งเตือน', 'กรุณาเลือกแผนก', 'warning');"; goto end_post; }
        }

        // เริ่ม Clone เอกสาร
        $success_count = 0;
        foreach ($actual_targets_to_insert as $target_id) {
            $year_th = date('Y') + 543;
            $res_last = $conn->query("SELECT document_no FROM documents WHERE document_no LIKE '%/$year_th' ORDER BY id DESC LIMIT 1");
            $row_last = $res_last->fetch_assoc();
            $new_no = $row_last ? str_pad((int)explode('/', $row_last['document_no'])[0] + 1, 3, '0', STR_PAD_LEFT) : "001";
            $document_no = $new_no . "/" . $year_th;

            $sql = "INSERT INTO documents (document_no, external_no, title, book_name, receive_date, sender_id, to_department_id, document_type_id, from_source, status, priority, remark, file_path, pending_target_depts, parent_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())";
            $stmt_ins = $conn->prepare($sql);
            
            $stmt_ins->bind_param("sssssiiisisssi", 
                $document_no, $orig_doc['external_no'], $orig_doc['title'], $orig_doc['book_name'], $orig_doc['receive_date'], 
                $_SESSION['user_id'], $target_id, $orig_doc['document_type_id'], $orig_doc['from_source'], 
                $orig_doc['priority'], $full_remark, $orig_doc['file_path'], $next_pending_depts_val, 
                $doc_id // parent_id = ID เอกสารปัจจุบัน
            );
            
            if ($stmt_ins->execute()) {
                $new_id = $conn->insert_id;
                $success_count++;
                $conn->query("INSERT INTO document_attachments (document_id, file_path, file_type) SELECT $new_id, file_path, file_type FROM document_attachments WHERE document_id = $doc_id");
                $conn->query("INSERT INTO notifications (user_id, title, message, is_read, created_at) SELECT id, 'ได้รับเอกสารส่งต่อ', 'เรื่อง: {$orig_doc['title']}', 0, NOW() FROM users WHERE department_id = $target_id");
            }
        }

        if ($success_count > 0) {
            $conn->query("UPDATE documents SET is_forwarded = 1, status = 'pending' WHERE id = $doc_id");
            
            if (!empty($orig_doc['parent_id'])) {
                $parent_id = $orig_doc['parent_id'];
                $conn->query("UPDATE documents SET status = 'success' WHERE id = $parent_id");
            }

            $alert_script = "Swal.fire('สำเร็จ', 'ส่งต่อเอกสารเรียบร้อยแล้ว', 'success').then(() => { window.location='forward_doc.php'; });";
            $is_process_done = true;
        } else {
            $alert_script = "Swal.fire('ข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้', 'error');";
        }
        end_post:; 
    } else {
         $alert_script = "Swal.fire('ข้อผิดพลาด', 'ไม่พบข้อมูลเอกสารต้นฉบับ', 'error').then(() => { window.location='forward_doc.php'; });";
    }
}

// --- แสดงผล ---
$show_mode = 'list'; 
$doc_data = null;
$pre_selected_depts = [];

if (isset($_GET['id']) && !$is_process_done) {
    $doc_id = intval($_GET['id']);
    // ดึงข้อมูลโดยไม่เช็คสถานะ success (เพื่อให้ pending ที่ส่งมาทำต่อได้)
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? AND to_department_id = ?");
    $stmt->bind_param("ii", $doc_id, $my_dept_id);
    $stmt->execute();
    $doc_data = $stmt->get_result()->fetch_assoc();

    if ($doc_data) {
        $show_mode = 'form'; 
        $workflow_step = "normal";
        $fixed_target_dept = 0;
        if ($doc_data['document_type_id'] == 1) { 
            if ($my_dept_id == 4) { $workflow_step = "head_office"; }
            elseif ($my_dept_id == 5) { $workflow_step = "palad"; $fixed_target_dept = 6; }
            elseif ($my_dept_id == 6) { $workflow_step = "mayor"; $fixed_target_dept = 2; }
            elseif ($my_dept_id == 2) { 
                $workflow_step = "admin_office"; 
                if (!empty($doc_data['pending_target_depts'])) $pre_selected_depts = explode(',', $doc_data['pending_target_depts']);
            }
        }
        $depts = $conn->query("SELECT id, name FROM departments WHERE id != '$my_dept_id' ORDER BY name ASC");
    } else {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $alert_script = "Swal.fire('ข้อผิดพลาด', 'ไม่พบเอกสาร', 'error').then(() => { window.location='forward_doc.php'; });";
        }
    }
} else {
    // List view
    if ($show_mode == 'list') {
        $sql_list = "SELECT d.id, d.document_no, d.title, d.created_at, d.status, t.type_name 
                     FROM documents d
                     LEFT JOIN document_types t ON d.document_type_id = t.id
                     WHERE d.to_department_id = ? 
                     AND d.status IN ('success', 'pending', 'process')
                     AND (d.is_forwarded = 0 OR d.is_forwarded IS NULL)
                     ORDER BY d.created_at DESC";
        $stmt_list = $conn->prepare($sql_list);
        $stmt_list->bind_param("i", $my_dept_id);
        $stmt_list->execute();
        $result_list = $stmt_list->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบส่งต่อเอกสาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { font-family: 'Kanit', sans-serif; background: #f4f7f6; } 
        .main-content { padding: 30px; height: 100vh; overflow-y: auto; }
        .option-group { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="flex-shrink-0"><?php include '../includes/sidebar.php'; ?></div>
        <div class="main-content flex-grow-1">
            <div class="container-fluid">
                
                <?php if ($show_mode == 'list'): ?>
                <h3 class="fw-bold mb-4 text-primary"><i class="bi bi-share-fill me-2"></i>รายการรอส่งต่อเอกสาร</h3>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light border-bottom">
                                    <tr>
                                        <th class="ps-4 py-3">วันที่</th>
                                        <th class="py-3">เรื่อง / เลขที่</th>
                                        <th class="py-3">ประเภท</th>
                                        <th class="py-3 text-center">สถานะปัจจุบัน</th>
                                        <th class="py-3 text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($result_list) && $result_list->num_rows > 0): ?>
                                        <?php while ($row = $result_list->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 small text-muted"><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($row['title']) ?></div>
                                                    <span class="badge bg-light text-secondary border small"><?= $row['document_no'] ?></span>
                                                </td>
                                                <td><span class="badge bg-info text-dark"><?= $row['type_name'] ?></span></td>
                                                <td class="text-center">
                                                    <?php if($row['status']=='pending'): ?>
                                                        <span class="badge bg-warning text-dark">รอดำเนินการ</span>
                                                    <?php elseif($row['status']=='success'): ?>
                                                        <span class="badge bg-success">เสร็จสิ้น</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?= $row['status'] ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="forward_doc.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                                        <i class="bi bi-send me-1"></i> ส่งต่อ
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted">ไม่มีเอกสารรอส่งต่อ</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4" style="max-width: 800px; margin: auto;">
                    <div class="card-header bg-primary text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-share me-2"></i> ดำเนินการส่งต่อเอกสาร</h5>
                        <a href="forward_doc.php" class="btn btn-sm btn-light text-primary rounded-pill"><i class="bi bi-x-lg"></i> ปิด</a>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-light border mb-4">
                            <strong>เรื่อง:</strong> <?= htmlspecialchars($doc_data['title']) ?> <br>
                            <strong>เลขที่:</strong> <?= htmlspecialchars($doc_data['document_no']) ?>
                        </div>

                        <form method="POST" action="forward_doc.php?id=<?= $doc_data['id'] ?>">
                            <div class="mb-4">
                                <label class="form-label fw-bold">1. แผนกปลายทาง</label>
                                <?php if ($workflow_step == 'head_office' || $workflow_step == 'admin_office' || $workflow_step == 'normal'): ?>
                                    <select name="to_department_ids[]" class="form-select select2-multiple" multiple required>
                                        <?php while($d = $depts->fetch_assoc()): ?>
                                            <option value="<?= $d['id'] ?>" <?= in_array($d['id'], $pre_selected_depts) ? 'selected' : '' ?>>
                                                <?= $d['name'] ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <?php if ($workflow_step == 'head_office'): ?>
                                        <div class="form-text text-primary">* กรุณาเลือกแผนกปลายทางที่ต้องการให้เอกสารไปถึงในขั้นตอนสุดท้าย</div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <input type="hidden" name="to_department_ids[]" value="<?= $fixed_target_dept ?>">
                                    <div class="p-3 bg-light border rounded">
                                        ระบบกำหนดส่งต่อให้: 
                                        <strong class="text-primary">
                                            <?php 
                                            if ($fixed_target_dept == 5) echo "สำนักปลัด (ปลัด)";
                                            elseif ($fixed_target_dept == 6) echo "สำนักงานนายกเทศมนตรี";
                                            elseif ($fixed_target_dept == 2) echo "สำนักปลัด (งานธุรการ)";
                                            else echo "แผนก ID: $fixed_target_dept";
                                            ?>
                                        </strong>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($workflow_step != 'admin_office'): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">2. ความเห็น/การสั่งการ</label>
                                    <div class="option-group">
                                        <?php if ($workflow_step == 'head_office' || $workflow_step == 'palad'): ?>
                                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="actions[]" value="เพื่อโปรดทราบและดำเนินการต่อ" id="p1"><label class="form-check-label" for="p1">เพื่อโปรดทราบและดำเนินการต่อ</label></div>
                                            <div class="form-check"><input class="form-check-input" type="checkbox" name="actions[]" value="เพื่อโปรดทราบและถือปฏิบัติ" id="p2"><label class="form-check-label" for="p2">เพื่อโปรดทราบและถือปฏิบัติ</label></div>
                                        <?php elseif ($workflow_step == 'mayor'): ?>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="actions[]" value="ทราบ" id="m1"><label class="form-check-label" for="m1">ทราบ</label></div>
                                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="actions[]" value="อนุมัติ" id="m2"><label class="form-check-label" for="m2">อนุมัติ</label></div>
                                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="actions[]" value="อนุญาต" id="m3"><label class="form-check-label" for="m3">อนุญาต</label></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="actions[]" value="ดำเนินการเสนอ" id="m4"><label class="form-check-label" for="m4">ดำเนินการเสนอ</label></div>
                                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="actions[]" value="เห็นควรตามเสนอ" id="m5"><label class="form-check-label" for="m5">เห็นควรตามเสนอ...</label></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="actions[]" value="ดำเนินการตามระเบียบ" id="n1"><label class="form-check-label" for="n1">ดำเนินการตามระเบียบ</label></div>
                                            <div class="form-check"><input class="form-check-input" type="checkbox" name="actions[]" value="เพื่อทราบ" id="n2"><label class="form-check-label" for="n2">เพื่อทราบ</label></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label fw-bold">3. หมายเหตุเพิ่มเติม</label>
                                <textarea name="forward_remark" class="form-control" rows="3" placeholder="ระบุข้อความเพิ่มเติม..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary rounded-pill shadow-sm py-2">ยืนยันการส่งต่อ</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-multiple').select2({ placeholder: "เลือกแผนกปลายทาง", width: '100%' });
        });
        <?= $alert_script ?>
    </script>
</body>
</html>