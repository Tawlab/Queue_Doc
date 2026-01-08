<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์เข้าดูข้อมูล
checkPageAccess($conn, 'role_view');

$sql = "SELECT * FROM roles ORDER BY id ASC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการบทบาท - E-Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style> body { font-family: 'Kanit', sans-serif; background-color: #f8f9fa; } </style>
</head>
<body>
    <div class="d-flex" style="height: 100vh; overflow: hidden;">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="flex-grow-1 p-4" style="overflow-y: auto;">
            <div class="container-fluid">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-success"><i class="bi bi-shield-lock me-2"></i>จัดการบทบาท (Role)</h3>
                    <a href="add.php" class="btn btn-success rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มบทบาทใหม่
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4" width="5%">ID</th>
                                    <th width="25%">ชื่อบทบาท</th>
                                    <th>คำอธิบาย</th>
                                    <th class="text-end pe-4" width="30%">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="ps-4"><?php echo $row['id']; ?></td>
                                    <td>
                                        <span class="fw-bold text-success"><?php echo htmlspecialchars($row['name']); ?></span>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="text-end pe-4">
                                        <a href="../permission/assign_role.php?role_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-warning rounded-pill me-1" title="กำหนดสิทธิ์">
                                            <i class="bi bi-key-fill me-1"></i> กำหนดสิทธิ์
                                        </a>

                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill me-1" title="แก้ไข">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <button onclick="confirmDelete(<?php echo $row['id']; ?>)" class="btn btn-sm btn-outline-danger rounded-pill" title="ลบ">
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
                text: "หากลบ ข้อมูลสิทธิ์และการตั้งค่าที่เกี่ยวข้องจะหายไป",
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
</body>
</html>