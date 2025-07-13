<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/auth_helper.php';
require_once 'includes/check_session.php';
require_once 'includes/PHPMailer/PHPMailer.php';
require_once 'includes/PHPMailer/SMTP.php';
require_once 'includes/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


$name           = input_data($_POST['name']);
$email          = input_data($_POST['email']);
$message        = input_data($_POST['message']);
$content        = 'ada yang mau jadi partner ordermatix:<br/>';
$content       .= 'Name: '.$name.'<br/>';
$content       .= 'Email: '.$email.'<br/>';
$content       .= 'Message: '.$message.'<br/>';

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->SMTPSecure = 'ssl';
$mail->Host = "mail.ordermatix.com";
//$mail->SMTPDebug = 2;
$mail->Port = 465;
$mail->SMTPAuth = true;
$mail->Timeout = 60;
$mail->SMTPKeepAlive = true;

$mail->Username   = 'info@ordermatix.com';
$mail->Password   = 'D0d0lg4rut@888';
$mail->setFrom('info@ordermatix.com', 'PARTNER ORDERMATIX');
$mail->addAddress('ibnurizal@gmail.com', 'Godeg');
$mail->isHTML(true);
$mail->Subject = 'Partner Registration form Ordermatix website';
$mail->Body    = $content;

if(!$mail->send()) {
header('location:partner?q=2');
}else{
header('location:partner?q=3');
}

?>
