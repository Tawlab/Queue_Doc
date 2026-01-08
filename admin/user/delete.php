<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์การลบ 
checkPageAccess($conn, 'user_delete');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // ป้องกันการลบตัวเอง
    if ($id == $_SESSION['user_id']) {
        echo "<script>
            alert('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
            window.location.href = 'index.php';
        </script>";
        exit();
    }

    // ลบข้อมูล
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    try {
        if ($stmt->execute()) {
            // ลบสำเร็จ
            header("Location: index.php?msg=deleted");
        } else {
            throw new Exception("Execute failed");
        }
    } catch (Exception $e) {
        // ลบไม่สำเร็จ 
        echo "<script>
            alert('ไม่สามารถลบผู้ใช้งานนี้ได้ เนื่องจากมีประวัติการทำงานในระบบ \\n(ระบบแนะนำให้แก้ไขสถานะเป็น \"ระงับ\" แทน)');
            window.location.href = 'index.php';
        </script>";
    }
} else {
    header("Location: index.php");
}
?>