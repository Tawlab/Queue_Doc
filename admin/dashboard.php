<?php
session_start();
include '../config/db.php';

// จัดการตัวกรองเวลา (Time Filter)
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

// รับค่าตัวกรองจาก URL และสร้างเงื่อนไข SQL
$search   = $_GET['search'] ?? '';
$f_type   = $_GET['type_id'] ?? '';
$f_status = $_GET['status'] ?? '';

$where_list = " WHERE 1=1";
if (!empty($search))   $where_list .= " AND (d.document_no LIKE '%$search%' OR d.title LIKE '%$search%')";
if (!empty($f_type))   $where_list .= " AND d.document_type_id = '$f_type'";
if (!empty($f_status)) $where_list .= " AND d.status = '$f_status'";

// ดึงรายการเอกสารล่าสุด 
$recent_docs = $conn->query("SELECT d.*, t.type_name 
                             FROM documents d 
                             LEFT JOIN document_types t ON d.document_type_id = t.id 
                             $where_list
                             ORDER BY d.id DESC LIMIT 5");

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
                    <form method="GET" class="row g-2">
                        <input type="hidden" name="time_filter" value="<?php echo htmlspecialchars($time_filter); ?>">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="เลขที่/ชื่อเรื่อง..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="type_id" class="form-select form-select-sm">
                                <option value="">-- ทุกประเภท --</option>
                                <?php while ($t = $types->fetch_assoc()): ?>
                                    <option value="<?= $t['id'] ?>" <?= $f_type == $t['id'] ? 'selected' : '' ?>><?= $t['type_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">-- ทุกสถานะ --</option>
                                <option value="pending" <?= $f_status == 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                                <option value="process" <?= $f_status == 'process' ? 'selected' : '' ?>>รับเรื่องแล้ว</option>
                                <option value="success" <?= $f_status == 'success' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                                <option value="cancel" <?= $f_status == 'cancel' ? 'selected' : '' ?>>ยกเลิก</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100">กรอง</button>
                            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">ล้าง</a>
                        </div>
                    </form>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-3">
                        <h6 class="fw-bold m-0"><i class="bi bi-list-ul me-2"></i>รายการเอกสารล่าสุด</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3" width="15%">เลขที่รับ</th>
                                        <th width="45%">เรื่อง</th>
                                        <th class="text-center" width="15%">สถานะ</th>
                                        <th class="text-center" width="25%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $recent_docs->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary"><?= $row['document_no'] ?></td>
                                            <td><?= htmlspecialchars($row['title']) ?></td>
                                            <td class="text-center">
                                                <?php
                                                $badges = ['pending' => 'bg-warning', 'process' => 'bg-info', 'success' => 'bg-success', 'cancel' => 'bg-danger'];
                                                $texts = ['pending' => 'รอ', 'process' => 'รับเรื่อง', 'success' => 'เสร็จ', 'cancel' => 'ยกเลิก'];
                                                ?>
                                                <span class="badge rounded-pill <?= $badges[$row['status']] ?>"><?= $texts[$row['status']] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <a href="../users/view_details.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                                    <i class="bi bi-eye"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['สถานะเอกสาร'],
                datasets: [{
                        label: 'ทั้งหมด',
                        data: [<?= $total_docs ?>],
                        backgroundColor: '#6366f1'
                    },
                    {
                        label: 'รอรับ',
                        data: [<?= $pending_docs ?>],
                        backgroundColor: '#ffc107'
                    },
                    {
                        label: 'กำลังทำ',
                        data: [<?= $process_docs ?>],
                        backgroundColor: '#0dcaf0'
                    },
                    {
                        label: 'สำเร็จ',
                        data: [<?= $success_docs ?>],
                        backgroundColor: '#198754'
                    },
                    {
                        label: 'ยกเลิก',
                        data: [<?= $cancel_docs ?>],
                        backgroundColor: '#dc3545'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>