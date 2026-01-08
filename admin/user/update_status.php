<?php
session_start();
include '../../config/db.php';

// ตรวจสอบสิทธิ์ (ถ้ามี)
if ($_SESSION['role'] !== 'Admin') {
    header('Location: ../../index.php');
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = intval($_GET['id']);
    $status = intval($_GET['status']); 

    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $id);

    if ($stmt->execute()) {
        $msg = ($status == 1) ? "เปิดใช้งานเรียบร้อยแล้ว" : "ระงับการใช้งานเรียบร้อยแล้ว";
        echo "<!DOCTYPE html>
        <html>
        <head>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <link href='https://fonts.googleapis.com/css2?family=Sarabun&display=swap' rel='stylesheet'>
            <style>body { font-family: \"Sarabun\", sans-serif; }</style>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: '$msg',
                    icon: 'success',
                    confirmButtonText: 'ตกลง'
                }).then(() => {
                    window.location.href = 'index.php';
                });
            </script>
        </body>
        </html>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "'); window.location.href='index.php';</script>";
    }
    $stmt->close();
} else {
    header('Location: index.php');
}
?>