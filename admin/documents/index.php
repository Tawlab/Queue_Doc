<?php
session_start();
include '../../config/db.php'; // เชื่อมต่อฐานข้อมูล

// ดึงข้อมูลเอกสาร
$sql = "SELECT d.*, 
        t.type_name AS type_name, 
        dp.name AS dept_name 
        FROM documents d
        LEFT JOIN document_types t ON d.document_type_id = t.id
        LEFT JOIN departments dp ON d.to_department_id = dp.id
        ORDER BY d.id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทะเบียนรับเอกสาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            /* ป้องกันสกรอลแนวนอนเกิน */
        }

        .main-content {
            width: 100%;
            padding: 20px;
            overflow-y: auto;
            height: 100vh;
            /* ให้ Scroll ได้เฉพาะเนื้อหา */
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>

<body>

    <div class="d-flex">

        <div class="flex-shrink-0">
            <?php include '../../includes/sidebar.php'; ?>
        </div>

        <div class="main-content flex-grow-1">
            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold text-dark">
                        <i class="bi bi-file-earmark-plus text-primary"></i> ทะเบียนรับเอกสาร
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <a href="add.php" class="btn btn-success mb-3 shadow-sm">
                                <i class="bi bi-plus-lg"></i> ลงทะเบียนรับเอกสารใหม่
                            </a>
                        </ol>
                    </nav>
                </div>

                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-muted">รายการเอกสารทั้งหมด</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>เลขที่รับ / วันที่</th>
                                        <th>ที่ / ลงวันที่</th>
                                        <th>เรื่อง</th>
                                        <th>ส่งถึง</th>
                                        <th>สถานะ</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-bold text-primary"><?php echo $row['document_no']; ?></span><br>
                                                    <small class="text-muted"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo $row['external_no']; ?><br>
                                                    <small class="text-muted">วันที่: <?php echo date('d/m/Y', strtotime($row['receive_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo $row['title']; ?></div>
                                                    <small class="text-muted">จาก: <?php echo $row['from_source']; ?></small>
                                                    <div><span class="badge bg-light text-dark border mt-1"><?php echo isset($row['type_name']) ? $row['type_name'] : '-'; ?></span></div>
                                                </td>
                                                <td><?php echo $row['type_name'] ? $row['type_name'] : '-'; ?></td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'pending' => ['bg-warning', 'รอดำเนินการ'],
                                                        'process' => ['bg-info', 'รับเรื่องแล้ว'],
                                                        'success' => ['bg-success', 'เสร็จสิ้น'],
                                                        'cancel'  => ['bg-danger', 'ยกเลิก']
                                                    ];
                                                    $st = $row['status'];
                                                    $class = $statusMap[$st][0] ?? 'bg-secondary';
                                                    $text = $statusMap[$st][1] ?? $st;
                                                    ?>
                                                    <span class="badge <?php echo $class; ?>"><?php echo $text; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($row['file_path']): ?>
                                                        <a href="../../uploads/<?php echo $row['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-info" title="ดูไฟล์">
                                                            <i class="bi bi-file-pdf"></i>
                                                        </a>
                                                    <?php endif; ?>

                                                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-warning" title="แก้ไข">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>

                                                    <?php if ($row['status'] !== 'cancel'): ?>
                                                        <button onclick="confirmCancel(<?php echo $row['id']; ?>, '<?php echo $row['document_no']; ?>')" class="btn btn-sm btn-outline-danger" title="ยกเลิกเอกสาร">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                ยังไม่มีข้อมูลเอกสาร
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmCancel(id, docNo) {
            Swal.fire({
                title: 'ยืนยันการยกเลิก?',
                text: "คุณต้องการยกเลิกเอกสารเลขที่ " + docNo + " ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ใช่, ยกเลิกเลย',
                cancelButtonText: 'ไม่'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ถ้ากดตกลง ให้วิ่งไปไฟล์ cancel_doc.php
                    window.location.href = 'cancel_doc.php?id=' + id;
                }
            })
        }
    </script>
</body>

</html>