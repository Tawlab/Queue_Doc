<?php
session_start();
include '../config/db.php';

// 1. ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$my_dept_id = $_SESSION['department_id'];
$my_user_id = $_SESSION['user_id'];
$alert_script = "";

// 2. รับค่าตัวกรอง
$filter_status = $_GET['filter'] ?? 'pending'; 
$search_query  = $_GET['search'] ?? '';       
$date_filter   = $_GET['date'] ?? '';          
$type_filter   = $_GET['type'] ?? '';          

$page_title = "เอกสาร";

// 3. กำหนดหัวข้อหน้า (แยกตาม Filter)
switch ($filter_status) {
    case 'pending': $page_title = "เอกสารรอดำเนินการ"; break;
    case 'process': $page_title = "เอกสารกำลังดำเนินการ"; break;
    case 'success': $page_title = "เอกสารเสร็จสิ้น"; break;
    case 'draft':   $page_title = "แบบร่างเอกสาร"; break;
    case 'all':     $page_title = "เอกสารทั้งหมด"; break;
    default:        $page_title = "เอกสารรอดำเนินการ"; $filter_status = 'pending';
}

// 4. สร้าง SQL Query แบบ Dynamic (Logic ใหม่)
$sql = "SELECT d.id, d.document_no, d.external_no, d.title, d.status, d.priority, d.created_at, d.book_name,
               u.first_name, u.last_name, t.type_name 
        FROM documents d
        LEFT JOIN users u ON d.sender_id = u.id
        LEFT JOIN document_types t ON d.document_type_id = t.id
        WHERE ";

$params = [];
$types = "";

// --- สร้างเงื่อนไข WHERE ตาม Filter ---
if ($filter_status == 'all') {
    // กรณี "ทั้งหมด": เอา (ส่งถึงฉัน และยังไม่ส่งต่อ) หรือ (ฉันเป็นคนร่าง)
    $sql .= "( (d.to_department_id = ? AND (d.is_forwarded = 0 OR d.is_forwarded IS NULL)) 
               OR 
               (d.sender_id = ? AND d.status = 'draft') )";
    $params[] = $my_dept_id;
    $params[] = $my_user_id;
    $types .= "ii";

} elseif ($filter_status == 'draft') {
    // กรณี "แบบร่าง": ดูจาก sender_id และสถานะ draft
    $sql .= "d.sender_id = ? AND d.status = 'draft'";
    $params[] = $my_user_id;
    $types .= "i";

} else {
    // กรณีอื่นๆ (Pending, Process, Success): ดูจาก to_department_id และสถานะ
    $sql .= "d.to_department_id = ? AND d.status = ? AND (d.is_forwarded = 0 OR d.is_forwarded IS NULL)";
    $params[] = $my_dept_id;
    $params[] = $filter_status;
    $types .= "is";
}

// 5. เพิ่มเงื่อนไขค้นหา/กรอง (Search Filters)
if (!empty($search_query)) {
    $sql .= " AND (d.document_no LIKE ? OR d.external_no LIKE ? OR d.title LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}
if (!empty($date_filter)) {
    $sql .= " AND DATE(d.created_at) = ?";
    $params[] = $date_filter;
    $types .= "s";
}
if (!empty($type_filter)) {
    $sql .= " AND d.document_type_id = ?";
    $params[] = $type_filter;
    $types .= "i";
}

$sql .= " ORDER BY d.created_at DESC";

// 6. ประมวลผล Query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ดึงประเภทเอกสารสำหรับ Dropdown
$types_result = $conn->query("SELECT * FROM document_types ORDER BY type_name ASC");

// ฟังก์ชันสร้าง URL
function build_url($new_params = []) {
    $params = $_GET;
    foreach ($new_params as $key => $value) { $params[$key] = $value; }
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background: #f4f7f6; }
        .main-content { padding: 30px; width: 100%; height: 100vh; overflow-y: auto; }
        .table-hover tbody tr:hover { background-color: #ffffff; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); }
        .btn-action { font-size: 0.85rem; border-radius: 20px; padding: 5px 15px; }
        .nav-pills .nav-link { color: #6c757d; border-radius: 20px; padding: 8px 20px; margin-right: 5px; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: white; box-shadow: 0 4px 6px rgba(13, 110, 253, 0.2); }
        .filter-card { background: #fff; border-radius: 15px; border: 1px solid #eef0f2; padding: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="flex-shrink-0"><?php include '../includes/sidebar.php'; ?></div>
        <div class="main-content flex-grow-1">
            <div class="container-fluid">
                
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <h3 class="fw-bold text-primary m-0"><i class="bi bi-inbox-fill me-2"></i><?= $page_title ?></h3>
                    
                    <div class="bg-white p-1 rounded-pill shadow-sm d-inline-block border">
                        <nav class="nav nav-pills">
                            <a class="nav-link <?= $filter_status == 'pending' ? 'active' : '' ?>" href="<?= build_url(['filter'=>'pending']) ?>">
                                <i class="bi bi-hourglass-split me-1"></i> รอรับ
                            </a>
                            <a class="nav-link <?= $filter_status == 'process' ? 'active' : '' ?>" href="<?= build_url(['filter'=>'process']) ?>">
                                <i class="bi bi-gear-fill me-1"></i> กำลังทำ
                            </a>
                            <a class="nav-link <?= $filter_status == 'success' ? 'active' : '' ?>" href="<?= build_url(['filter'=>'success']) ?>">
                                <i class="bi bi-check-circle-fill me-1"></i> เสร็จสิ้น
                            </a>
                            <a class="nav-link <?= $filter_status == 'draft' ? 'active' : '' ?>" href="<?= build_url(['filter'=>'draft']) ?>">
                                <i class="bi bi-pencil-square me-1"></i> แบบร่าง
                            </a>
                            <a class="nav-link <?= $filter_status == 'all' ? 'active' : '' ?>" href="<?= build_url(['filter'=>'all']) ?>">
                                <i class="bi bi-collection-fill me-1"></i> ทั้งหมด
                            </a>
                        </nav>
                    </div>
                </div>

                <div class="filter-card">
                    <form method="GET" action="">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter_status) ?>">
                        
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small text-muted fw-bold">ค้นหา</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0" 
                                           placeholder="เลขที่รับ / เลขที่ภายนอก / เรื่อง" 
                                           value="<?= htmlspecialchars($search_query) ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted fw-bold">วันที่รับเอกสาร</label>
                                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted fw-bold">ประเภทเอกสาร</label>
                                <select name="type" class="form-select">
                                    <option value="">-- ทั้งหมด --</option>
                                    <?php while($t = $types_result->fetch_assoc()): ?>
                                        <option value="<?= $t['id'] ?>" <?= $type_filter == $t['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['type_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary w-100 shadow-sm"><i class="bi bi-funnel"></i> กรอง</button>
                                    <?php if(!empty($search_query) || !empty($date_filter) || !empty($type_filter)): ?>
                                        <a href="?filter=<?= $filter_status ?>" class="btn btn-outline-secondary w-50" title="ล้างค่า"><i class="bi bi-x-lg"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light border-bottom">
                                    <tr>
                                        <th class="ps-4 py-3" width="15%">วันที่</th>
                                        <th class="py-3" width="45%">รายละเอียด</th>
                                        <th class="py-3" width="15%">ประเภท</th>
                                        <th class="py-3" width="10%">สถานะ</th>
                                        <th class="py-3 text-center" width="15%">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr class="border-bottom">
                                                <td class="ps-4 text-muted small">
                                                    <div><i class="bi bi-calendar3 me-1"></i> <?php echo date('d/m/Y', strtotime($row['created_at'])); ?></div>
                                                    <div class="text-secondary opacity-75" style="font-size: 0.8rem;"><?php echo date('H:i', strtotime($row['created_at'])); ?> น.</div>
                                                </td>
                                                <td>
                                                    <?php if ($row['priority'] == 1): ?>
                                                        <span class="badge bg-warning text-dark mb-1">ด่วน</span>
                                                    <?php elseif ($row['priority'] == 2): ?>
                                                        <span class="badge bg-danger mb-1">ด่วนที่สุด</span>
                                                    <?php endif; ?>

                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['title']); ?></div>
                                                    
                                                    <div class="d-flex gap-2 mt-1">
                                                        <span class="badge bg-light text-secondary border small">
                                                            ภายใน: <?php echo htmlspecialchars($row['document_no']); ?>
                                                        </span>
                                                        <?php if(!empty($row['external_no'])): ?>
                                                            <span class="badge bg-white text-muted border small">
                                                                ภายนอก: <?php echo htmlspecialchars($row['external_no']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark bg-opacity-10 border border-info">
                                                        <?php echo htmlspecialchars($row['type_name']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if($row['status']=='draft'): ?>
                                                        <span class="badge bg-secondary">แบบร่าง</span>
                                                    <?php else: ?>
                                                        <?php
                                                        $status_badge = 'bg-secondary';
                                                        $status_text = $row['status'];
                                                        switch($row['status']) {
                                                            case 'pending': $status_badge = 'bg-warning text-dark'; $status_text = 'รอดำเนินการ'; break;
                                                            case 'process': $status_badge = 'bg-info text-dark'; $status_text = 'กำลังดำเนินการ'; break;
                                                            case 'success': $status_badge = 'bg-success'; $status_text = 'เสร็จสิ้น'; break;
                                                            case 'cancel': $status_badge = 'bg-danger'; $status_text = 'ยกเลิก'; break;
                                                        }
                                                        ?>
                                                        <span class="badge rounded-pill <?php echo $status_badge; ?>">
                                                            <?php echo $status_text; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex gap-2 justify-content-center">
                                                        <?php if ($row['status'] == 'draft'): ?>
                                                            <a href="send_doc.php?draft_id=<?php echo $row['id']; ?>" 
                                                               class="btn btn-sm btn-warning shadow-sm" title="แก้ไข/ส่ง">
                                                                <i class="bi bi-pencil-square"></i> แก้ไข
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="view_details.php?id=<?php echo $row['id']; ?>" 
                                                               class="btn btn-sm btn-outline-info btn-action" title="ดูรายละเอียด">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if ($row['status'] != 'success' && $row['status'] != 'cancel' && $row['status'] != 'archive'): ?>
                                                                <a href="edit_status.php?id=<?php echo $row['id']; ?>" 
                                                                   class="btn btn-sm btn-primary btn-action" title="อัปเดตสถานะ">
                                                                    <i class="bi bi-pencil-square"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                                <br>ไม่พบข้อมูลเอกสาร
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
</body>
</html>