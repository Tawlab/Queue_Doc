<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

checkPageAccess($conn, 'permission_delete');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // ลบ Permissions
    $sql = "DELETE FROM permissions WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: index.php?msg=deleted");
    } else {
        echo "<script>alert('เกิดข้อผิดพลาดในการลบ'); window.location='index.php';</script>";
    }
} else {
    header("Location: index.php");
}
?>