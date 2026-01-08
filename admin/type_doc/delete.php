<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

checkPageAccess($conn, 'type_doc_delete');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        $sql = "DELETE FROM document_types WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            header("Location: index.php?msg=deleted");
        } else {
            throw new Exception("Execute failed");
        }
    } catch (mysqli_sql_exception $e) {
        // กรณีมีเอกสารใช้งานประเภทนี้อยู่ 
        echo "<script>
            alert('ไม่สามารถลบประเภทเอกสารนี้ได้ เนื่องจากมีเอกสารในระบบที่ใช้งานประเภทนี้อยู่');
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