<?php
session_start();

// ถ้ามี User ID ใน Session แล้ว 
if (isset($_SESSION['user_id'])) {
    // ส่งไปหน้า Dashboard ของ User
    header("Location: user/index.php");
} else {
    // ถ้ายังไม่ล็อกอิน ส่งไปหน้า Login
    header("Location: auth/login.php");
}
exit();
?>