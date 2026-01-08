<?php
session_start();
include '../../config/db.php';

// เช็คว่าส่ง ID มาไหม
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // อัปเดตสถานะเป็น 'cancel' (ยกเลิก)
    $stmt = $conn->prepare("UPDATE documents SET status = 'cancel' WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // สำเร็จ -> แสดง SweetAlert
        echo "<!DOCTYPE html>
        <html lang='th'>
        <head>
            <meta charset='UTF-8'>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    title: 'ยกเลิกเอกสารแล้ว!',
                    text: 'สถานะเอกสารถูกเปลี่ยนเป็นยกเลิกเรียบร้อย',
                    icon: 'success',
                    confirmButtonText: 'ตกลง'
                }).then(() => {
                    window.location.href = 'index.php';
                });
            </script>
        </body>
        </html>";
    } else {
        // Error
        echo "<script>alert('Error: " . $stmt->error . "'); window.location='index.php';</script>";
    }
    $stmt->close();
} else {
    header('Location: index.php');
}
?>