<?php
session_start();
include '../config/db.php';

// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// รับ ID เอกสารจาก URL
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    header('Location: view_incoming.php');
    exit();
}

// ดึงข้อมูลเอกสารโดยละเอียด
$sql = "SELECT d.*, 
               u.first_name, u.last_name, 
               t.type_name, 
               dept.name as dept_name
        FROM documents d
        LEFT JOIN users u ON d.sender_id = u.id
        LEFT JOIN document_types t ON d.document_type_id = t.id
        LEFT JOIN departments dept ON d.to_department_id = dept.id
        WHERE d.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();

// หากไม่พบข้อมูลเอกสาร ให้ใช้ SweetAlert แจ้งเตือนแล้วเด้งกลับ
if (!$doc) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        setTimeout(function() {
            Swal.fire({
                icon: 'error',
                title: 'ไม่พบข้อมูล',
                text: 'ไม่พบเอกสารที่ท่านต้องการเข้าถึง',
                confirmButtonText: 'ตกลง'
            }).then(() => { window.location = 'view_incoming.php'; });
        }, 100);
    </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดเอกสาร - <?php echo htmlspecialchars($doc['document_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background: #f4f7f6; }
        .main-content { padding: 30px; width: 100%; height: 100vh; overflow-y: auto; }
        
        /* ปรับปรุง Label ให้ไม่หดตัว และมีความกว้างคงที่ */
        .detail-label { 
            font-weight: 500; 
            color: #6c757d; 
            width: 150px; 
            flex-shrink: 0; /* สำคัญ: ห้ามหดตัวเมื่อเนื้อหายาว */
        }
        
        .info-card { border-radius: 15px; border: none; }
        .file-item { padding: 10px; border: 1px solid #eee; border-radius: 10px; margin-bottom: 8px; transition: 0.2s; }
        .file-item:hover { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="flex-shrink-0"><?php include '../includes/sidebar.php'; ?></div>

        <div class="main-content flex-grow-1">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-primary m-0">
                        <i class="bi bi-file-earmark-text me-2"></i>รายละเอียดเอกสาร
                    </h3>
                    <div class="d-flex gap-2">
                        <?php if ($doc['status'] == 'success'): ?>
                            <a href="forward_doc.php?id=<?= $doc['id'] ?>" class="btn btn-info text-white rounded-pill px-4 shadow-sm">
                                <i class="bi bi-share me-1"></i> ส่งต่อเอกสารนี้
                            </a>
                        <?php endif; ?>
                        
                        <a href="view_incoming.php" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm">
                            <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                        </a>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card info-card shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-bold border-bottom pb-3 mb-4 text-secondary">
                                    ข้อมูลทั่วไปของเอกสาร
                                </h5>
                                
                                <div class="d-flex mb-3">
                                    <div class="detail-label">เลขที่รับภายใน</div>
                                    <div class="fs-6 text-dark"><?php echo htmlspecialchars($doc['document_no']); ?></div>
                                </div>

                                <div class="d-flex mb-3">
                                    <div class="detail-label">เลขที่ภายนอก</div>
                                    <div class="fs-6 text-dark"><?php echo htmlspecialchars($doc['external_no'] ?: '-'); ?></div>
                                </div>

                                <div class="d-flex mb-3 align-items-start">
                                    <div class="detail-label pt-1">เรื่อง</div> <div class="fs-6 text-break"><?php echo htmlspecialchars($doc['title']); ?></div>
                                </div>

                                <div class="d-flex mb-3">
                                    <div class="detail-label">วันที่รับ</div>
                                    <div><?php echo date('d/m/Y', strtotime($doc['receive_date'])); ?></div>
                                </div>

                                <?php if ($doc['status'] == 'success' || $doc['status'] == 'archive'): ?>
                                <div class="d-flex mb-3">
                                    <div class="detail-label">วันที่เสร็จสิ้น</div>
                                    <div class="text-success fw-bold">
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        <?php 
                                            $completed_time = $doc['updated_at'] ?? $doc['created_at'];
                                            echo date('d/m/Y H:i', strtotime($completed_time)) . ' น.';
                                        ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="d-flex mb-3">
                                    <div class="detail-label">ประเภท</div>
                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($doc['type_name']); ?></span>
                                </div>

                                <div class="d-flex mb-3">
                                    <div class="detail-label">ความเร่งด่วน</div>
                                    <?php if ($doc['priority'] == 1): ?>
                                        <span class="badge bg-warning text-dark">ด่วน</span>
                                    <?php elseif ($doc['priority'] == 2): ?>
                                        <span class="badge bg-danger">ด่วนที่สุด</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ปกติ</span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex mb-3 align-items-start">
                                    <div class="detail-label pt-1">รายละเอียด</div>
                                    <div class="text-muted text-break"><?php echo nl2br(htmlspecialchars($doc['book_name'] ?: 'ไม่มีรายละเอียดเพิ่มเติม')); ?></div>
                                </div>

                                <div class="d-flex mb-3 align-items-start">
                                    <div class="detail-label pt-2">ความเห็น/หมายเหตุ</div>
                                    <div class="p-3 bg-light rounded-3 w-100 text-danger fw-medium text-break">
                                        <?php echo nl2br(htmlspecialchars($doc['remark'] ?: '-')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card info-card shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-secondary mb-3">ผู้ส่งและหน่วยงาน</h6>
                                <div class="mb-4">
                                    <small class="text-muted d-block">ส่งโดย (ชื่อ-นามสกุล):</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></div>
                                </div>
                                <div class="mb-4">
                                    <small class="text-muted d-block">รับจากหน่วยงาน:</small>
                                    <div class="text-break"><?php echo htmlspecialchars($doc['from_source']); ?></div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted d-block">ส่งถึงแผนก:</small>
                                    <span class="badge bg-info text-dark"><?php echo htmlspecialchars($doc['dept_name']); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="card info-card shadow-sm">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-secondary mb-3">ไฟล์เอกสารแนบ</h6>
                                
                                <?php if (!empty($doc['file_path'])): ?>
                                    <div class="file-item d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-4 me-2"></i>
                                            <span class="small text-truncate" style="max-width: 150px;">ไฟล์หลัก</span>
                                        </div>
                                        <a href="../uploads/<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-primary">เปิดดู</a>
                                    </div>
                                <?php endif; ?>

                                <?php
                                $res_att = $conn->query("SELECT * FROM document_attachments WHERE document_id = '$doc_id'");
                                while ($att = $res_att->fetch_assoc()):
                                ?>
                                    <div class="file-item d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-paperclip text-primary fs-4 me-2"></i>
                                            <span class="small">ไฟล์แนบเพิ่มเติม</span>
                                        </div>
                                        <a href="../uploads/<?php echo $att['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">เปิดดู</a>
                                    </div>
                                <?php endwhile; ?>

                                <?php if (empty($doc['file_path']) && $res_att->num_rows == 0): ?>
                                    <div class="text-center py-3 text-muted small">ไม่มีไฟล์แนบ</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>