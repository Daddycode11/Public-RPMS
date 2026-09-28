<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'eutech253@gmail.com';
    $mail->Password = 'zryiwafboroqoknh';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('eutech253@gmail.com', 'Test');
    $mail->addAddress('rpmsa00@gmail.com');

    $mail->Subject = 'PHPMailer Test';
    $mail->Body = 'If you received this, SMTP works.';

    $mail->send();
    echo "EMAIL SENT SUCCESSFULLY";
} catch (Exception $e) {
    echo "ERROR: {$mail->ErrorInfo}";
}
