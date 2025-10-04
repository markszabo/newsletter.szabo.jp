<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/MailerInterface.php';

class PHPMailerAdapter implements MailerInterface {
    private $emailPassword;
    private $host;
    private $port;
    private $username;
    private $fromEmail;
    private $fromName;

    public function __construct($emailPassword, $host = 'mail.ininet.hu', $port = 587, $username = 'newsletter@szabo.jp', $fromEmail = 'newsletter@szabo.jp', $fromName = 'Mark from szabo.jp') {
        $this->emailPassword = $emailPassword;
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function send($to, $subject, $body) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->emailPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->port;

            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
