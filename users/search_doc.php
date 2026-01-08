<?php
session_start();
include '../config/db.php';

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// ----------------------------------------------------------------------
// PHP สำหรับจัดการ AJAX Request
// ----------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] == 'search_docs') {
    $search = $_POST['search'] ?? '';
    $type_id = $_POST['type_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $dept_id = $_POST['dept_id'] ?? ''; // [แก้ไข] รับค่าแผนกแทนเวลา
    
    // สร้างเงื่อนไข SQL
    $where_sql = " WHERE 1=1 ";
    $params = [];
    $types = "";

    // กรองตามคำค้นหา
    if (!empty($search)) {
        $where_sql .= " AND (d.document_no LIKE ? OR d.title LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    // กรองตามประเภท
    if (!empty($type_id)) {
        $where_sql .= " AND d.document_type_id = ?";
        $params[] = $type_id;
        $types .= "i";
    }

    // กรองตามสถานะ
    if (!empty($status)) {
        $where_sql .= " AND d.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    // กรองตามแผนกปลายทาง
    if (!empty($dept_id)) {
        $where_sql .= " AND d.to_department_id = ?";
        $params[] = $dept_id;
        $types .= "i";
    }

    // Query ข้อมูล (Join ตารางแผนกเพื่อแสดงชื่อแผนกด้วย)
    $sql = "SELECT d.*, t.type_name, dept.name as dept_name 
            FROM documents d 
            LEFT JOIN document_types t ON d.document_type_id = t.id 
            LEFT JOIN departments dept ON d.to_department_id = dept.id
            $where_sql 
            ORDER BY d.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // สร้าง HTML ส่งกลับไป
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $badges = ['pending' => 'bg-warning', 'process' => 'bg-info', 'success' => 'bg-success', 'cancel' => 'bg-danger'];
            $texts = ['pending' => 'รอดำเนินการ', 'process' => 'รับเรื่องแล้ว', 'success' => 'เสร็จสิ้น', 'cancel' => 'ยกเลิก'];
            $status_badge = $badges[$row['status']] ?? 'bg-secondary';
            $status_text = $texts[$row['status']] ?? $row['status'];
            
            $date_th = date('d/m/Y', strtotime($row['created_at']));
            $dept_name = $row['dept_name'] ?? '-';

            echo '<tr class="align-middle">';
            echo '<td class="ps-4 text-muted small">' . $date_th . '</td>';
            echo '<td>';
            echo '  <div class="fw-bold text-dark">' . htmlspecialchars($row['title']) . '</div>';
            echo '  <span class="badge bg-light text-secondary border small">' . $row['document_no'] . '</span>';
            echo '</td>';
            echo '<td><span class="badge bg-light text-dark border">' . htmlspecialchars($row['type_name']) . '</span></td>';
            echo '<td><span class="text-muted small"><i class="bi bi-building"></i> ' . htmlspecialchars($dept_name) . '</span></td>';
            echo '<td class="text-center"><span class="badge rounded-pill ' . $status_badge . '">' . $status_text . '</span></td>';
            echo '<td class="text-center">';
            echo '  <a href="view_details.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm"><i class="bi bi-eye"></i> ดูรายละเอียด</a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i> ไม่พบเอกสารตามเงื่อนไขที่ระบุ</td></tr>';
    }
    exit(); // จบการทำงานของ AJAX
}

// ----------------------------------------------------------------------
// หน้าจอ HTML (แสดงผลเมื่อเข้าหน้าเว็บปกติ)
// ----------------------------------------------------------------------
$types = $conn->query("SELECT * FROM document_types");
// ดึงข้อมูลแผนก
$depts = $conn->query("SELECT * FROM departments ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ค้นหาเอกสาร - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background: #f4f7f6; }
        .main-content { padding: 30px; width: 100%; height: 100vh; overflow-y: auto; }
        .search-card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .table-hover tbody tr:hover { background-color: #ffffff; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transform: translateY(-1px); transition: all 0.2s; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="flex-shrink-0"><?php include '../includes/sidebar.php'; ?></div>

        <div class="main-content flex-grow-1">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4 text-primary"><i class="bi bi-search me-2"></i>ค้นหาเอกสาร</h3>

                <div class="card search-card mb-4">
                    <div class="card-body p-4">
                        <form id="searchForm" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">คำค้นหา</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" id="search_text" class="form-control border-start-0 ps-0" placeholder="ระบุเลขที่ หรือชื่อเรื่อง...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">ประเภทเอกสาร</label>
                                <select id="search_type" class="form-select">
                                    <option value="">-- ทั้งหมด --</option>
                                    <?php while ($t = $types->fetch_assoc()): ?>
                                        <option value="<?= $t['id'] ?>"><?= $t['type_name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold small text-muted">สถานะ</label>
                                <select id="search_status" class="form-select">
                                    <option value="">-- ทั้งหมด --</option>
                                    <option value="pending">รอดำเนินการ</option>
                                    <option value="process">รับเรื่องแล้ว</option>
                                    <option value="success">เสร็จสิ้น</option>
                                    <option value="cancel">ยกเลิก</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">แผนกปลายทาง</label>
                                <select id="search_dept" class="form-select">
                                    <option value="">-- ทุกแผนก --</option>
                                    <?php while ($d = $depts->fetch_assoc()): ?>
                                        <option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light border-bottom">
                                    <tr>
                                        <th class="ps-4 py-3" width="10%">วันที่</th>
                                        <th class="py-3" width="30%">เรื่อง / เลขที่</th>
                                        <th class="py-3" width="15%">ประเภท</th>
                                        <th class="py-3" width="15%">แผนกปลายทาง</th>
                                        <th class="py-3 text-center" width="15%">สถานะ</th>
                                        <th class="py-3 text-center" width="15%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsTable">
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            กำลังโหลดข้อมูล...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // ฟังก์ชันโหลดข้อมูล
            function loadDocuments() {
                let search = $('#search_text').val();
                let type_id = $('#search_type').val();
                let status = $('#search_status').val();
                let dept_id = $('#search_dept').val();

                $.ajax({
                    url: 'search_doc.php',
                    type: 'POST',
                    data: {
                        action: 'search_docs',
                        search: search,
                        type_id: type_id,
                        status: status,
                        dept_id: dept_id
                    },
                    success: function(response) {
                        $('#resultsTable').html(response);
                        
                        if(response.includes('ไม่พบเอกสาร') && search !== '') {
                             const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                            Toast.fire({
                                icon: 'info',
                                title: 'ไม่พบเอกสารที่ค้นหา'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                    }
                });
            }

            // โหลดข้อมูลครั้งแรก
            loadDocuments();

            // ดักจับเหตุการณ์การพิมพ์หรือเปลี่ยนตัวเลือก
            $('#search_text').on('keyup', function() {
                loadDocuments();
            });

            // การดักจับ #search_dept
            $('#search_type, #search_status, #search_dept').on('change', function() {
                loadDocuments();
            });
        });
    </script>
</body>
</html>