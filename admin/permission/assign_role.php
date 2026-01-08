<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์ 
checkPageAccess($conn, 'permission_edit');

$success = '';
$error = '';

//  รับค่า Role ID ที่เลือก 
$selected_role_id = isset($_REQUEST['role_id']) ? intval($_REQUEST['role_id']) : 0;

// ดึงรายการ Role ทั้งหมด
$roles_result = mysqli_query($conn, "SELECT * FROM roles ORDER BY id ASC");
$roles = [];
while ($row = mysqli_fetch_assoc($roles_result)) {
    $roles[] = $row;
}

// ถ้ายังไม่มีการเลือก Role ให้ใช้ Role แรกเป็นค่าเริ่มต้น
if ($selected_role_id == 0 && count($roles) > 0) {
    $selected_role_id = $roles[0]['id'];
}

// บันทึกข้อมูล (เมื่อกดปุ่ม Save)
if (isset($_POST['save_assignment'])) {
    $role_id = intval($_POST['role_id']);
    $perms = isset($_POST['permissions']) ? $_POST['permissions'] : []; // Array ของ Permission ID

    $conn->begin_transaction();
    try {
        // ลบสิทธิ์เก่าทั้งหมดของ Role นี้ออกก่อน
        $sql_del = "DELETE FROM role_permissions WHERE role_id = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $role_id);
        $stmt_del->execute();

        // วนลูปเพิ่มสิทธิ์ใหม่ที่ติ๊กเลือก
        if (!empty($perms)) {
            $sql_insert = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            
            foreach ($perms as $perm_id) {
                $stmt_insert->bind_param("ii", $role_id, $perm_id);
                $stmt_insert->execute();
            }
        }

        $conn->commit();
        $success = "บันทึกสิทธิ์เรียบร้อยแล้ว";
        
        // อัปเดต selected_role_id ให้ตรงกับที่บันทึก
        $selected_role_id = $role_id;

    } catch (Exception $e) {
        $conn->rollback();
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ดึงสิทธิ์ทั้งหมดที่มีในระบบ
$perms_sql = "SELECT * FROM permissions ORDER BY slug ASC";
$perms_result = mysqli_query($conn, $perms_sql);

// จัดกลุ่ม Permissions 
$grouped_perms = [];
while ($perm = mysqli_fetch_assoc($perms_result)) {
    $parts = explode('_', $perm['slug']);
    $group_name = ucfirst($parts[0]);
    $grouped_perms[$group_name][] = $perm;
}

// ดึงสิทธิ์ปัจจุบันของ Role ที่เลือก
$current_perms = [];
if ($selected_role_id > 0) {
    $curr_sql = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
    $stmt = $conn->prepare($curr_sql);
    $stmt->bind_param("i", $selected_role_id);
    $stmt->execute();
    $curr_result = $stmt->get_result();
    while ($row = $curr_result->fetch_assoc()) {
        $current_perms[] = $row['permission_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>กำหนดสิทธิ์ตามบทบาท - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> 
        body { font-family: 'Kanit', sans-serif; background-color: #f8f9fa; } 
        .perm-card { transition: all 0.3s; border: 1px solid #eee; }
        .perm-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="d-flex" style="height: 100vh; overflow: hidden;">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4" style="overflow-y: auto;">
            <div class="container-fluid">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success"><i class="bi bi-ui-checks-grid me-2"></i>กำหนดสิทธิ์การใช้งาน</h3>
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">

                        <?php if($success): ?>
                            <script>
                                Swal.fire({
                                    icon: 'success',
                                    title: 'บันทึกสำเร็จ',
                                    text: '<?php echo $success; ?>',
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            </script>
                        <?php endif; ?>

                        <?php if($error): ?>
                            <script>Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: '<?php echo $error; ?>' });</script>
                        <?php endif; ?>

                        <form method="POST" action="">
                            
                            <div class="bg-light p-3 rounded-3 mb-4 border">
                                <label class="form-label fw-bold text-secondary mb-2">เลือกบทบาทที่ต้องการจัดการ:</label>
                                <select name="role_id" class="form-select form-select-lg border-success" onchange="window.location.href='assign_role.php?role_id='+this.value">
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['id']; ?>" <?php echo ($selected_role_id == $r['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <hr class="mb-4">

                            <div class="row">
                                <?php foreach ($grouped_perms as $group => $items): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card perm-card h-100 rounded-3">
                                            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                                                <h5 class="fw-bold text-success mb-0">
                                                    <i class="bi bi-folder me-2"></i>หมวด <?php echo $group; ?>
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="list-group list-group-flush">
                                                    <?php foreach ($items as $p): ?>
                                                        <label class="list-group-item d-flex gap-3 align-items-center border-0 px-0">
                                                            <input class="form-check-input flex-shrink-0" type="checkbox" name="permissions[]" value="<?php echo $p['id']; ?>" 
                                                                style="font-size: 1.2rem;"
                                                                <?php echo (in_array($p['id'], $current_perms)) ? 'checked' : ''; ?>>
                                                            <span class="pt-1 form-checked-content">
                                                                <strong class="d-block text-dark"><?php echo htmlspecialchars($p['name']); ?></strong>
                                                                <small class="d-block text-muted font-monospace"><?php echo $p['slug']; ?></small>
                                                            </span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="fixed-bottom bg-white border-top p-3 shadow text-end" style="margin-left: 280px;"> <span class="text-muted me-3 small">กำลังแก้ไขสิทธิ์สำหรับบทบาท: 
                                    <strong class="text-success">
                                        <?php 
                                            foreach($roles as $r) { if($r['id'] == $selected_role_id) echo $r['name']; } 
                                        ?>
                                    </strong>
                                </span>
                                <button type="submit" name="save_assignment" class="btn btn-success rounded-pill px-5 shadow">
                                    <i class="bi bi-save me-2"></i> บันทึกการเปลี่ยนแปลง
                                </button>
                            </div>
                            
                            <div style="height: 60px;"></div>

                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>