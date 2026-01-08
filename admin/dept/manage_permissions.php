<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// ตรวจสอบสิทธิ์การเข้าถึง (ใช้สิทธิ์ dept_view)
checkPageAccess($conn, 'dept_view'); 

$error = '';
$success = '';

// รับ ID ของแผนกที่ต้องการจัดการ
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$dept_id = intval($_GET['id']);

// ดึงข้อมูลแผนกจากตาราง departments
$dept_res = $conn->prepare("SELECT name FROM departments WHERE id = ?");
$dept_res->bind_param("i", $dept_id);
$dept_res->execute();
$dept = $dept_res->get_result()->fetch_assoc();

if (!$dept) {
    die("ไม่พบข้อมูลแผนก");
}

// บันทึกข้อมูลเมื่อมีการ Submit ฟอร์ม
if (isset($_POST['save_permissions'])) {
    $selected_perms = $_POST['perms'] ?? [];

    $conn->begin_transaction();
    try {
        $del = $conn->prepare("DELETE FROM department_permissions WHERE department_id = ?");
        $del->bind_param("i", $dept_id);
        $del->execute();

        if (!empty($selected_perms)) {
            $ins = $conn->prepare("INSERT INTO department_permissions (department_id, permission_id) VALUES (?, ?)");
            foreach ($selected_perms as $p_id) {
                $ins->bind_param("ii", $dept_id, $p_id);
                $ins->execute();
            }
        }

        $conn->commit();
        $success = "บันทึกสิทธิ์สำหรับแผนก " . $dept['name'] . " เรียบร้อยแล้ว";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ดึงรายการสิทธิ์ทั้งหมดจากตาราง permissions
$all_perms = $conn->query("SELECT * FROM permissions ORDER BY id ASC");

// ดึงรายการสิทธิ์ที่แผนกนี้มีอยู่แล้วจากตาราง department_permissions
$current_perms = [];
$check_res = $conn->prepare("SELECT permission_id FROM department_permissions WHERE department_id = ?");
$check_res->bind_param("i", $dept_id);
$check_res->execute();
$res = $check_res->get_result();
while($row = $res->fetch_assoc()) {
    $current_perms[] = $row['permission_id'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสิทธิ์แผนก - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f8f9fa; }
        .perm-card { cursor: pointer; transition: 0.2s; border: 1px solid #dee2e6; }
        .perm-card:hover { background-color: #f0fdf4; border-color: #198754; }
        .form-check-input { cursor: pointer; width: 1.2em; height: 1.2em; }
        .select-all-wrapper { background: #fff; border-radius: 10px; padding: 15px; margin-bottom: 20px; border-left: 5px solid #198754; }
    </style>
</head>
<body>
    <div class="d-flex" style="height: 100vh; overflow: hidden;">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4" style="overflow-y: auto;">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success">
                        <i class="bi bi-shield-lock-fill me-2"></i>จัดการสิทธิ์: แผนก<?php echo htmlspecialchars($dept['name']); ?>
                    </h3>
                    <a href="index.php" class="btn btn-secondary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
                    </a>
                </div>

                <?php if($success): ?>
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: 'สำเร็จ',
                            text: '<?php echo $success; ?>',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    </script>
                <?php endif; ?>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <form method="POST" id="permForm">
                            <div class="select-all-wrapper shadow-sm d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0 fw-bold text-success">เลือกเมนูที่ต้องการให้เข้าถึง</h6>
                                    <small class="text-muted">ติ๊กเลือกสิทธิ์เพื่อให้พนักงานในแผนกนี้ใช้งานหน้าเพจต่างๆ ได้</small>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                    <label class="form-check-label fw-bold ms-2" for="selectAll">เลือกสิทธิ์ทั้งหมด</label>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <?php while($p = $all_perms->fetch_assoc()): ?>
                                    <div class="col-md-4">
                                        <div class="p-3 rounded-3 perm-card h-100 shadow-sm bg-white" onclick="toggleCheckbox('perm_<?php echo $p['id']; ?>')">
                                            <div class="form-check">
                                                <input class="form-check-input perm-checkbox" type="checkbox" name="perms[]" 
                                                       value="<?php echo $p['id']; ?>" 
                                                       id="perm_<?php echo $p['id']; ?>"
                                                       <?php echo in_array($p['id'], $current_perms) ? 'checked' : ''; ?>
                                                       onclick="event.stopPropagation(); checkSelectAllStatus();">
                                                <label class="form-check-label fw-bold ms-2" for="perm_<?php echo $p['id']; ?>">
                                                    <?php echo $p['name']; ?>
                                                </label>
                                                <div class="small text-muted ms-2 mt-1">
                                                    <i class="bi bi-key me-1"></i>Slug: <?php echo $p['slug']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <hr class="my-4 opacity-25">
                            <div class="text-end">
                                <button type="submit" name="save_permissions" class="btn btn-success rounded-pill px-5 py-3 shadow">
                                    <i class="bi bi-save me-1"></i> บันทึกการตั้งค่าสิทธิ์แผนก
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ฟังก์ชันช่วยในการติ๊กถูกเมื่อคลิกที่ Card
        function toggleCheckbox(id) {
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
            checkSelectAllStatus();
        }

        // จัดการเหตุการณ์ Select All
        const selectAllBtn = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.perm-checkbox');

        selectAllBtn.addEventListener('change', function() {
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });

        // ตรวจสอบว่าถ้าเลือกครบทุกอันแล้วให้ Select All ติ๊กถูกด้วย
        function checkSelectAllStatus() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            selectAllBtn.checked = allChecked;
        }

        // รันครั้งแรกเพื่อเช็คสถานะ Select All
        checkSelectAllStatus();
    </script>
</body>
</html>