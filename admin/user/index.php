<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์การเข้าถึง
checkPageAccess($conn, 'user_view');

// ดึงข้อมูล User ทั้งหมด
$sql = "SELECT u.*, d.name AS dept_name, r.name AS role_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        LEFT JOIN user_roles ur ON u.id = ur.user_id 
        LEFT JOIN roles r ON ur.role_id = r.id 
        ORDER BY u.id DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="d-flex" style="height: 100vh; overflow: hidden;">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4" style="overflow-y: auto;">
            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success"><i class="bi bi-people-fill me-2"></i>จัดการผู้ใช้งาน</h3>
                    <a href="add.php" class="btn btn-success rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มผู้ใช้งานใหม่
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">ชื่อ-นามสกุล</th>
                                    <th>Username</th>
                                    <th>แผนก</th>
                                    <th>บทบาท</th>
                                    <th>สถานะ</th>
                                    <th class="text-end pe-4">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-secondary">
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><span class="badge bg-info text-dark bg-opacity-10 border border-info"><?php echo $row['dept_name']; ?></span></td>
                                        <td><span class="badge bg-warning text-dark bg-opacity-10 border border-warning"><?php echo $row['role_name']; ?></span></td>
                                        <td class="text-center">
                                            <?php if ($row['is_active']): ?>
                                                <button onclick="toggleUserStatus(<?php echo $row['id']; ?>, 0, '<?php echo $row['username']; ?>')"
                                                    class="btn btn-sm btn-success rounded-pill px-3">
                                                    <i class="bi bi-check-circle-fill me-1"></i> ใช้งาน
                                                </button>
                                            <?php else: ?>
                                                <button onclick="toggleUserStatus(<?php echo $row['id']; ?>, 1, '<?php echo $row['username']; ?>')"
                                                    class="btn btn-sm btn-danger rounded-pill px-3">
                                                    <i class="bi bi-x-circle-fill me-1"></i> ระงับ
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill me-1">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-sm btn-outline-danger rounded-pill">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

    <script>
        function confirmDelete(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลจะถูกลบถาวร ไม่สามารถกู้คืนได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `delete.php?id=${id}`;
                }
            })
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ปุ่มสถานะ
        function toggleUserStatus(userId, newStatus, userName) {
            const actionText = newStatus === 1 ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';
            const icon = newStatus === 1 ? 'question' : 'warning';
            const confirmButtonColor = newStatus === 1 ? '#198754' : '#d33';

            Swal.fire({
                title: 'ยืนยันการเปลี่ยนแปลง?',
                text: `คุณต้องการ ${actionText} ผู้ใช้: ${userName} ใช่หรือไม่?`,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: confirmButtonColor,
                cancelButtonColor: '#6e7881',
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ส่งค่าไปยังไฟล์ประมวลผล
                    window.location.href = `update_status.php?id=${userId}&status=${newStatus}`;
                }
            });
        }
    </script>
</body>

</html>