<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// เช็คสิทธิ์
checkPageAccess($conn, 'dept_delete');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        $sql = "DELETE FROM departments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // ลบสำเร็จ
            header("Location: index.php?msg=deleted");
        } else {
            throw new Exception("Execute failed");
        }
    } catch (mysqli_sql_exception $e) {
        // จับ Error FK 
        echo "<script>
            alert('ไม่สามารถลบแผนกนี้ได้ เนื่องจากมีผู้ใช้งานหรือเอกสารที่เกี่ยวข้องในระบบ');
            window.location.href = 'index.php';
        </script>";
    } catch (Exception $e) {
        echo "<script>
            alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "');
            window.location.href = 'index.php';
        </script>";
    }
} else {
    header("Location: index.php");
}
?>