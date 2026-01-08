<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

checkPageAccess($conn, 'role_delete');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        $sql = "DELETE FROM roles WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: index.php?msg=deleted");
        } else {
            throw new Exception("Execute failed");
        }
    } catch (mysqli_sql_exception $e) {
        // จับ Error Foreign Key 
        echo "<script>
            alert('ไม่สามารถลบบทบาทนี้ได้ เนื่องจากมีผู้ใช้งาน (User) สังกัดบทบาทนี้อยู่\\nกรุณาย้ายผู้ใช้งานไปบทบาทอื่นก่อน');
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