<?php
session_start();
include '../config/db.php';

// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// ----------------------------------------------------------------------
// ส่วนที่ 1: PHP สำหรับจัดการ AJAX Request (คืนค่าเป็น HTML Table Row)
// ----------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] == 'fetch_dashboard_data') {
    $search = $_POST['search'] ?? '';
    $type_id = $_POST['type_id'] ?? '';
    $status = $_POST['status'] ?? '';
    
    // สร้างเงื่อนไข SQL
    $where_sql = " WHERE 1=1 ";
    $params = [];
    $types = "";

    // 1. ค้นหา: เลขที่ภายใน, เลขที่ภายนอก, ชื่อเรื่อง
    if (!empty($search)) {
        $where_sql .= " AND (d.document_no LIKE ? OR d.external_no LIKE ? OR d.title LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }

    // 2. กรองตามประเภท
    if (!empty($type_id)) {
        $where_sql .= " AND d.document_type_id = ?";
        $params[] = $type_id;
        $types .= "i";
    }

    // 3. กรองตามสถานะ
    if (!empty($status)) {
        $where_sql .= " AND d.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    // ดึงข้อมูล (Limit 10 รายการล่าสุด เพื่อไม่ให้รกหน้า Dashboard)
    $sql = "SELECT d.*, t.type_name 
            FROM documents d 
            LEFT JOIN document_types t ON d.document_type_id = t.id 
            $where_sql 
            ORDER BY d.id DESC LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $badges = [
                'pending' => 'bg-warning text-dark', 
                'process' => 'bg-info text-dark', 
                'success' => 'bg-success', 
                'cancel' => 'bg-danger'
            ];
            $texts = [
                'pending' => 'รอดำเนินการ', 
                'process' => 'รับเรื่องแล้ว', 
                'success' => 'เสร็จสิ้น', 
                'cancel' => 'ยกเลิก'
            ];
            
            $status_badge = $badges[$row['status']] ?? 'bg-secondary';
            $status_text = $texts[$row['status']] ?? $row['status'];
            $ext_no = !empty($row['external_no']) ? htmlspecialchars($row['external_no']) : '-';

            echo '<tr>';
            echo '<td class="ps-3 fw-bold text-primary">' . htmlspecialchars($row['document_no']) . '</td>';
            echo '<td class="text-secondary">' . $ext_no . '</td>'; // คอลัมน์เลขที่ภายนอก
            
            // คอลัมน์เรื่อง: เพิ่ม class text-break และ style เพื่อตัดคำ
            echo '<td>';
            echo '  <div class="text-break" style="min-width: 200px; max-width: 400px;">';
            echo       htmlspecialchars($row['title']);
            echo '  </div>';
            echo '</td>';
            
            echo '<td class="text-center"><span class="badge rounded-pill ' . $status_badge . '">' . $status_text . '</span></td>';
            echo '<td class="text-center">';
            echo '  <a href="../users/view_details.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-info rounded-pill px-3 shadow-sm">';
            echo '      <i class="bi bi-eye"></i> ดูรายละเอียด';
            echo '  </a>';
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i> ไม่พบข้อมูลเอกสาร</td></tr>';
    }
    exit();
}

// ----------------------------------------------------------------------
// ส่วนที่ 2: PHP สำหรับโหลดหน้าเว็บปกติ (Load Stats & Initial View)
// ----------------------------------------------------------------------

// จัดการตัวกรองเวลาสำหรับ Stats Card (Time Filter)
$time_filter = $_GET['time_filter'] ?? 'all';
$time_sql = "";
if ($time_filter == 'today') {
    $time_sql = " AND DATE(created_at) = CURDATE()";
} elseif ($time_filter == 'week') {
    $time_sql = " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
} elseif ($time_filter == 'month') {
    $time_sql = " AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
} elseif ($time_filter == 'year') {
    $time_sql = " AND YEAR(created_at) = YEAR(NOW())";
}

// ดึงสถิติตามช่วงเวลา
$total_docs = $conn->query("SELECT COUNT(*) as total FROM documents WHERE 1=1 $time_sql")->fetch_assoc()['total'];
$pending_docs = $conn->query("SELECT COUNT(*) as total FROM documents WHERE status = 'pending' $time_sql")->fetch_assoc()['total'];
$process_docs = $conn->query("SELECT COUNT(*) as total FROM documents WHERE status = 'process' $time_sql")->fetch_assoc()['total'];
$success_docs = $conn->query("SELECT COUNT(*) as total FROM documents WHERE status = 'success' $time_sql")->fetch_assoc()['total'];
$cancel_docs = $conn->query("SELECT COUNT(*) as total FROM documents WHERE status = 'cancel' $time_sql")->fetch_assoc()['total'];

// ดึงประเภทเอกสารสำหรับ Dropdown
$types = $conn->query("SELECT * FROM document_types");
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แดชบอร์ด - ระบบเอกสาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f4f7f6;
            overflow: hidden;
        }

        .main-content {
            padding: 25px;
            height: 100vh;
            overflow-y: auto;
            width: 100%;
        }

        .stat-card {
            border: none;
            border-radius: 12px;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
        }

        .icon-box {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #eef0f2;
        }
        
        /* CSS สำหรับตัดบรรทัด */
        .text-break {
            word-wrap: break-word !important;
            word-break: break-word !important;
            white-space: normal !important;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <div class="flex-shrink-0"><?php include '../includes/sidebar.php'; ?></div>

        <div class="main-content flex-grow-1">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold m-0 text-primary">ภาพรวมระบบ</h4>
                    <div class="btn-group btn-group-sm shadow-sm">
                        <a href="?time_filter=today" class="btn btn-outline-primary <?= $time_filter == 'today' ? 'active' : '' ?>">วันนี้</a>
                        <a href="?time_filter=week" class="btn btn-outline-primary <?= $time_filter == 'week' ? 'active' : '' ?>">สัปดาห์นี้</a>
                        <a href="?time_filter=month" class="btn btn-outline-primary <?= $time_filter == 'month' ? 'active' : '' ?>">เดือนนี้</a>
                        <a href="?time_filter=all" class="btn btn-outline-primary <?= $time_filter == 'all' ? 'active' : '' ?>">ทั้งหมด</a>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col">
                        <div class="card stat-card shadow-sm p-2 border-start border-primary border-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-primary text-white me-2"><i class="bi bi-files"></i></div>
                                <div><small class="text-muted">ทั้งหมด</small>
                                    <h5 class="fw-bold mb-0"><?= $total_docs ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card shadow-sm p-2 border-start border-warning border-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-warning text-white me-2"><i class="bi bi-clock"></i></div>
                                <div><small class="text-muted">รอดำเนินการ</small>
                                    <h5 class="fw-bold mb-0 text-warning"><?= $pending_docs ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card shadow-sm p-2 border-start border-info border-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-info text-white me-2"><i class="bi bi-gear"></i></div>
                                <div><small class="text-muted">รับเรื่องแล้ว</small>
                                    <h5 class="fw-bold mb-0 text-info"><?= $process_docs ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card shadow-sm p-2 border-start border-success border-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-success text-white me-2"><i class="bi bi-check-all"></i></div>
                                <div><small class="text-muted">เสร็จสิ้น</small>
                                    <h5 class="fw-bold mb-0 text-success"><?= $success_docs ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card stat-card shadow-sm p-2 border-start border-danger border-4">
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-danger text-white me-2"><i class="bi bi-x"></i></div>
                                <div><small class="text-muted">ยกเลิก</small>
                                    <h5 class="fw-bold mb-0 text-danger"><?= $cancel_docs ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-body p-3">
                        <div style="height: 220px;"><canvas id="mainChart"></canvas></div>
                    </div>
                </div>

                <div class="filter-section shadow-sm bg-white p-3 mb-4 rounded-3 border">
                    <form id="dashboardFilterForm" class="row g-2" onsubmit="return false;">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                <input type="text" id="search" class="form-control" placeholder="เลขที่ภายใน / ภายนอก / ชื่อเรื่อง...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select id="type_id" class="form-select form-select-sm">
                                <option value="">-- ทุกประเภท --</option>
                                <?php while ($t = $types->fetch_assoc()): ?>
                                    <option value="<?= $t['id'] ?>"><?= $t['type_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="status" class="form-select form-select-sm">
                                <option value="">-- ทุกสถานะ --</option>
                                <option value="pending">รอดำเนินการ</option>
                                <option value="process">รับเรื่องแล้ว</option>
                                <option value="success">เสร็จสิ้น</option>
                                <option value="cancel">ยกเลิก</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-primary w-100" onclick="loadDashboardData()">ค้นหา</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetFilter()">ล้าง</button>
                        </div>
                    </form>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold m-0"><i class="bi bi-list-ul me-2"></i>รายการเอกสารล่าสุด</h6>
                        <small class="text-muted">แสดง 10 รายการล่าสุด</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3" width="15%">เลขที่รับ</th>
                                        <th width="15%">เลขที่ภายนอก</th> <th width="35%">เรื่อง</th>
                                        <th class="text-center" width="15%">สถานะ</th>
                                        <th class="text-center" width="20%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody id="recentDocsBody">
                                    <tr><td colspan="5" class="text-center py-4">กำลังโหลดข้อมูล...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ChartJS Setup
        const ctx = document.getElementById('mainChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['สถานะเอกสาร'],
                datasets: [
                    { label: 'ทั้งหมด', data: [<?= $total_docs ?>], backgroundColor: '#6366f1' },
                    { label: 'รอรับ', data: [<?= $pending_docs ?>], backgroundColor: '#ffc107' },
                    { label: 'กำลังทำ', data: [<?= $process_docs ?>], backgroundColor: '#0dcaf0' },
                    { label: 'สำเร็จ', data: [<?= $success_docs ?>], backgroundColor: '#198754' },
                    { label: 'ยกเลิก', data: [<?= $cancel_docs ?>], backgroundColor: '#dc3545' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } }
                },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // AJAX Dashboard Logic
        let searchTimeout;

        function loadDashboardData() {
            let search = $('#search').val();
            let type_id = $('#type_id').val();
            let status = $('#status').val();

            $.ajax({
                url: 'dashboard.php',
                type: 'POST',
                data: {
                    action: 'fetch_dashboard_data',
                    search: search,
                    type_id: type_id,
                    status: status
                },
                success: function(response) {
                    $('#recentDocsBody').html(response);

                    // แจ้งเตือน SweetAlert หากค้นหาแล้วไม่เจอข้อมูล (เฉพาะกรณีที่มีการพิมพ์ค้นหา)
                    if (response.includes('ไม่พบข้อมูลเอกสาร') && search !== '') {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                        Toast.fire({
                            icon: 'info',
                            title: 'ไม่พบเอกสารที่ระบุ'
                        });
                    }
                },
                error: function() {
                    console.error('Error fetching data');
                }
            });
        }

        function resetFilter() {
            $('#search').val('');
            $('#type_id').val('');
            $('#status').val('');
            loadDashboardData();
        }

        $(document).ready(function() {
            // โหลดข้อมูลครั้งแรกทันที
            loadDashboardData();

            // Auto Search เมื่อพิมพ์ (Delay 500ms)
            $('#search').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(loadDashboardData, 500);
            });

            // Auto Filter เมื่อเลือก Dropdown
            $('#type_id, #status').on('change', function() {
                loadDashboardData();
            });
        });
    </script>
</body>
</html>