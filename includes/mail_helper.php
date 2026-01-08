<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ถอยออก 1 ระดับไปที่ root แล้วเข้าไปที่ Lib
require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

function sendEmail($toEmail, $subject, $htmlContent) {
    $mail = new PHPMailer(true);
    try {
        // --- ตั้งค่า SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'centertranferdata@gmail.com'; // อีเมลกลาง
        $mail->Password   = 'xcyb vjuf rkbq pwap';    // รหัสผ่านแอป 16 หลัก
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('centertranferdata@gmail.com', 'ระบบ E-Document');

        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlContent;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}