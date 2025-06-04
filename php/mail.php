<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['floor_id'], $data['hours'], $data['minutes'], $data['seconds'])) {
    http_response_code(400);
    exit('Invalid input');
}

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    // Your Gmail credentials
    $mail->Username = '215516@student.upm.edu.my';
    $mail->Password = 'sycx supo yfln kxgs'; // Use Gmail App Password here!
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Sender & recipient
    $mail->setFrom('215516@student.upm.edu.my', 'Plate Pickup System');
    $mail->addAddress('loohuaiyuan@gmail.com');

    // Content
    $mail->isHTML(false);
    $mail->Subject = '⚠️ Plate Pickup Delay Alert';
    $mail->Body = "Floor {$data['floor_id']} has a plate waiting more than 8 hours.\n\n".
                  "Current time: {$data['hours']}h {$data['minutes']}m {$data['seconds']}s";

    $mail->send();
    echo 'Email sent';
} catch (Exception $e) {
    http_response_code(500);
    echo "Mailer Error: " . $mail->ErrorInfo;
}
?>
