<?php
session_start();
include '../config/db.php';
include '../includes/mail_helper.php'; 

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// ป้องกัน Warning กรณี Session ยังไม่มีข้อมูลชื่อ
if (!isset($_SESSION['first_name'])) $_SESSION['first_name'] = "User";
if (!isset($_SESSION['last_name'])) $_SESSION['last_name'] = "";
if (!isset($_SESSION['fullname'])) $_SESSION['fullname'] = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

$alert_script = "";
$doc_id = $_GET['id'] ?? null;

if (!$doc_id) {
    header('Location: view_incoming.php');
    exit();
}

$sql = "SELECT d.*, u.username as sender_name, u.email as sender_email, t.type_name as type_name 
        FROM documents d
        LEFT JOIN users u ON d.sender_id = u.id
        LEFT JOIN document_types t ON d.document_type_id = t.id
        WHERE d.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) {
    die("<script>alert('ไม่พบข้อมูลเอกสาร'); window.location='view_incoming.php';</script>");
}

// เมื่อมีการอัปเดตสถานะ (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_status = $_POST['status'];
    $remark = trim($_POST['remark']);
    $actor_name = $_SESSION['fullname']; // ชื่อคนที่กดอัปเดต

    $update_sql = "UPDATE documents SET status = ?, remark = ? WHERE id = ?";
    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param("ssi", $new_status, $remark, $doc_id);

    if ($stmt_update->execute()) {
        
        // แปลงสถานะเป็นภาษาไทยสำหรับแสดงผล
        $status_text = ($new_status == 'process') ? 'กำลังดำเนินการ' : (($new_status == 'success') ? 'รับเรื่องแล้ว' : 'ส่งคืน / ยกเลิก');
        
        $notif_title = "อัปเดตสถานะเอกสาร: " . $doc['title'];
        $notif_msg = "เอกสารเลขที่ " . $doc['document_no'] . " สถานะเปลี่ยนเป็น: $status_text โดย $actor_name";
        
        $sender_id = $doc['sender_id'];
        $sender_email = $doc['sender_email'];

        // บันทึกแจ้งเตือนลงเว็บ (Web Notification)
        $stmt_n = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt_n->bind_param("iss", $sender_id, $notif_title, $notif_msg);
        $stmt_n->execute();

        // ส่งอีเมลแจ้งกลับ (Email Notification)
        if (!empty($sender_email)) {
            $email_subject = "แจ้งความคืบหน้าเอกสาร: " . $doc['title'] . " (" . $doc['document_no'] . ")";
            $status_color = '#0d6efd'; 
            if ($new_status == 'success') $status_color = '#198754'; 
            if ($new_status == 'cancel') $status_color = '#dc3545';  

            $link_url = "http://" . $_SERVER['HTTP_HOST'] . "/queue_document";

            $email_body = "
            <html>
            <head>
                <link href='https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap' rel='stylesheet'>
                <style> body { font-family: 'Sarabun', sans-serif; margin: 0; padding: 0; background-color: #f4f6f9; } </style>
            </head>
            <body style='background-color: #f4f6f9; padding: 20px; font-family: \"Sarabun\", sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #1a237e;'>
                    <div style='background-color: #ffffff; padding: 25px 30px; border-bottom: 1px solid #ececec;'>
                        <h2 style='margin: 0; color: #1a237e; font-size: 22px; font-weight: 600;'>ระบบสารบรรณอิเล็กทรอนิกส์</h2>
                        <p style='margin: 5px 0 0; color: #6c757d; font-size: 14px;'>E-Document Notification System</p>
                    </div>
                    <div style='padding: 30px;'>
                        <p style='font-size: 16px; color: #333333; margin-bottom: 20px;'><b>เรียน เจ้าของเรื่อง</b></p>
                        <p style='color: #555555; line-height: 1.6; margin-bottom: 25px;'>
                            เอกสารของท่านได้รับการดำเนินการและปรับปรุงสถานะแล้ว โดยมีรายละเอียดดังนี้:
                        </p>
                        <div style='background-color: #f8f9fa; border-radius: 6px; padding: 20px; border: 1px solid #e9ecef;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr><td style='padding: 8px 0; color: #6c757d; width: 35%; font-size: 14px;'>เลขที่เอกสาร:</td><td style='padding: 8px 0; color: #1a237e; font-weight: 600; font-size: 15px;'>$doc[document_no]</td></tr>
                                <tr><td style='padding: 8px 0; color: #6c757d; font-size: 14px;'>เรื่อง:</td><td style='padding: 8px 0; color: #333333; font-weight: 600; font-size: 15px;'>$doc[title]</td></tr>
                                <tr><td style='padding: 8px 0; color: #6c757d; font-size: 14px;'>สถานะล่าสุด:</td><td style='padding: 8px 0; color: $status_color; font-weight: bold; font-size: 16px;'>$status_text</td></tr>
                                <tr><td style='padding: 8px 0; color: #6c757d; font-size: 14px;'>ผู้ดำเนินการ:</td><td style='padding: 8px 0; color: #333333; font-size: 15px;'>$actor_name</td></tr>
                                <tr><td style='padding: 8px 0; color: #6c757d; font-size: 14px;'>หมายเหตุ:</td><td style='padding: 8px 0; color: #333333; font-size: 15px;'>" . ($remark ? $remark : '-') . "</td></tr>
                                <tr><td style='padding: 8px 0; color: #6c757d; font-size: 14px;'>วันที่ดำเนินการ:</td><td style='padding: 8px 0; color: #333333; font-size: 15px;'>" . date('d/m/Y H:i') . "</td></tr>
                            </table>
                        </div>
                        <div style='text-align: center; margin-top: 30px;'>
                            <a href='$link_url' style='background-color: #198754; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block; box-shadow: 0 2px 5px rgba(25, 135, 84, 0.3);'>ตรวจสอบผลการดำเนินงาน</a>
                        </div>
                    </div>
                    <div style='background-color: #f1f3f5; padding: 20px; text-align: center;'>
                        <p style='margin: 0; font-size: 12px; color: #868e96;'>อีเมลฉบับนี้เป็นการแจ้งเตือนอัตโนมัติ กรุณาอย่าตอบกลับ<br>&copy; " . date('Y') . " E-Document System. สงวนลิขสิทธิ์</p>
                    </div>
                </div>
            </body>
            </html>";
            
            sendEmail($sender_email, $email_subject, $email_body);
        }

        $alert_script = "
            Swal.fire({
                title: 'บันทึกสำเร็จ!',
                text: 'ปรับปรุงสถานะเอกสารเรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonColor: '#6366f1',
                confirmButtonText: 'ตกลง'
            }).then(() => {
                window.location = 'view_incoming.php';
            });
        ";
    } else {
        $alert_script = "Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถบันทึกข้อมูลได้', 'error');";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสถานะเอกสาร - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        }

        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f0f2f5;
            color: #1e293b;
        }

        .main-content {
            padding: 40px;
            width: 100%;
            height: 100vh;
            overflow-y: auto;
        }

        .glass-card {
            background: #ffffff;
            border: none;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .doc-header {
            background: var(--primary-grad);
            color: white;
            padding: 30px;
            border-radius: 24px 24px 0 0;
        }

        .info-item {
            background: #f8fafc;
            border-radius: 16px;
            padding: 15px;
            height: 100%;
            border: 1px solid #edf2f7;
        }

        .status-btn-group .btn-check:checked+.btn-outline-primary {
            background-color: #e0e7ff;
            border-color: #6366f1;
            color: #4338ca;
        }

        .status-btn-group .btn-check:checked+.btn-outline-success {
            background-color: #dcfce7;
            border-color: #22c55e;
            color: #15803d;
        }

        .status-btn-group .btn-check:checked+.btn-outline-danger {
            background-color: #fee2e2;
            border-color: #ef4444;
            color: #b91c1c;
        }

        .btn-save {
            background: var(--primary-grad);
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-weight: 600;
            color: white;
            transition: 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.4);
            color: white;
        }
        .doc-title {
            text-indent: 50px;       /* ย่อหน้าบรรทัดแรก 50px */
            word-wrap: break-word;   /* ตัดคำอัตโนมัติ */
            word-break: break-word;  /* ตัดคำกรณีคำยาวติดกัน */
            overflow-wrap: break-word; 
            line-height: 1.8;        /* เพิ่มระยะห่างบรรทัดให้อ่านง่าย */
            width: 100%;             /* ให้ความกว้างเต็มพื้นที่ */
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <div class="flex-shrink-0"><?php include '../includes/sidebar.php'; ?></div>

        <div class="main-content flex-grow-1">
            <div class="container" style="max-width: 850px;">

                <div class="mb-4">
                    <a href="view_incoming.php" class="text-decoration-none text-secondary small">
                        <i class="bi bi-chevron-left"></i> ย้อนกลับไปรายการเอกสาร
                    </a>
                </div>

                <div class="glass-card shadow-lg">
                    <div class="doc-header">
                        <div class="d-flex flex-column align-items-start">
                            <span class="badge bg-white text-primary mb-3 px-3 py-2 rounded-pill shadow-sm">
                                <?php echo htmlspecialchars($doc['type_name']); ?>
                            </span>
                            
                            <h3 class="fs-6 mb-0 doc-title">
                                <?php echo htmlspecialchars($doc['title']); ?>
                            </h3>
                        </div>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <div class="row g-3 mb-5">
                            <div class="col-md-4">
                                <div class="info-item">
                                    <label class="text-muted small d-block mb-1">ผู้ส่งเอกสาร</label>
                                    <div class="fw-bold"><i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($doc['sender_name']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-item">
                                    <label class="text-muted small d-block mb-1">วันที่รับเข้าระบบ</label>
                                    <div class="fw-bold"><i class="bi bi-calendar3 me-2"></i><?php echo date('d/m/Y | H:i', strtotime($doc['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-item d-flex align-items-center justify-content-center p-2">
                                    <?php if (!empty($doc['file_path'])): ?>
                                        <a href="../uploads/<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-primary rounded-pill px-4 shadow-sm w-100 h-100 d-flex align-items-center justify-content-center">
                                            <i class="bi bi-file-earmark-pdf-fill me-2"></i>เปิดดูเอกสาร
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">ไม่มีไฟล์แนบ</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <h5 class="fw-bold mb-4"><i class="bi bi-pencil-square me-2 text-primary"></i>ดำเนินการกับเอกสารนี้</h5>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-uppercase text-secondary">เลือกสถานะใหม่</label>
                                <div class="row g-3 status-btn-group">
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="status" id="st_success" value="success" <?php echo $doc['status'] == 'success' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success w-100 py-3 rounded-4" for="st_success">
                                            <i class="bi bi-check-circle fs-4 d-block mb-1"></i>รับเรื่องแล้ว
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="status" id="st_cancel" value="cancel" <?php echo $doc['status'] == 'cancel' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-danger w-100 py-3 rounded-4" for="st_cancel">
                                            <i class="bi bi-x-circle fs-4 d-block mb-1"></i>ส่งคืน / ยกเลิก
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label small fw-bold text-uppercase text-secondary">หมายเหตุการดำเนินการ</label>
                                <textarea name="remark" class="form-control rounded-4 p-3 shadow-sm" rows="4" placeholder="ระบุรายละเอียดเพิ่มเติม หรือเหตุผลในการปรับสถานะ..."><?php echo htmlspecialchars($doc['remark'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-save shadow">
                                    <i class="bi bi-cloud-arrow-up-fill me-2"></i> ยืนยันและบันทึกข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <p class="text-center mt-4 text-muted small">
                    <i class="bi bi-shield-lock me-1"></i> การเปลี่ยนแปลงสถานะจะถูกบันทึกประวัติไว้ในระบบ
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?= $alert_script ?>
    </script>
</body>
</html>