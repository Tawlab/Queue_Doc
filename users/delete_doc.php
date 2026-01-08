<?php
session_start();
include '../config/db.php';

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sender_id = $_SESSION['user_id'];

if ($id > 0) {
    // 1. ตรวจสอบก่อนลบ: ต้องเป็นเอกสารของตัวเอง (sender_id) และสถานะต้องเป็น 'draft' หรือ 'cancel' เท่านั้น
    // (ป้องกันไม่ให้ลบเอกสารที่ส่งไปแล้วหรือของคนอื่น)
    $check_sql = "SELECT id, file_path FROM documents WHERE id = ? AND sender_id = ? AND status IN ('draft', 'cancel')";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $id, $sender_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        
        // 2. ลบไฟล์แนบจาก Server (ถ้ามี)
        // ลบไฟล์หลัก
        if (!empty($row['file_path']) && file_exists("../uploads/" . $row['file_path'])) {
            @unlink("../uploads/" . $row['file_path']);
        }
        
        // ลบไฟล์แนบรอง (Attachments)
        $att_sql = "SELECT file_path FROM document_attachments WHERE document_id = ?";
        $att_stmt = $conn->prepare($att_sql);
        $att_stmt->bind_param("i", $id);
        $att_stmt->execute();
        $att_res = $att_stmt->get_result();
        while ($att = $att_res->fetch_assoc()) {
            if (!empty($att['file_path']) && file_exists("../uploads/" . $att['file_path'])) {
                @unlink("../uploads/" . $att['file_path']);
            }
        }

        // 3. ลบข้อมูลจากฐานข้อมูล (ตารางลูกจะถูกลบอัตโนมัติด้วย ON DELETE CASCADE)
        $del_stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $del_stmt->bind_param("i", $id);
        
        if ($del_stmt->execute()) {
            // ลบสำเร็จ ให้กลับไปหน้าเดิมและแจ้งเตือน
            echo "<script>
                // เช็คว่ามี SweetAlert หรือไม่ ถ้าไม่มีให้ใช้ alert ปกติ
                window.location.href = 'view_incoming.php?status=deleted';
            </script>";
        } else {
            echo "Error deleting record: " . $conn->error;
        }
    } else {
        echo "<script>alert('ไม่สามารถลบได้: เอกสารนี้ไม่ใช่แบบร่างหรือคุณไม่มีสิทธิ์'); window.history.back();</script>";
    }
} else {
    header('Location: view_incoming.php');
}
?>