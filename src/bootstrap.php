<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

require __DIR__ . '/Database.php';
require __DIR__ . '/MailerInterface.php';
require __DIR__ . '/PHPMailerAdapter.php';
require __DIR__ . '/NewsletterService.php';

// Load configuration
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
} else {
    // Fallback to environment variables for testing
    $DB_HOST = getenv('DB_HOST') ?: 'localhost';
    $DB_NAME = getenv('DB_NAME') ?: 'newsletter';
    $DB_USER = getenv('DB_USER') ?: 'root';
    $DB_PASS = getenv('DB_PASS') ?: '';
    $EMAIL_PASSWORD = getenv('EMAIL_PASSWORD') ?: '';
    $SEND_DIGEST_KEY_HASH = getenv('SEND_DIGEST_KEY_HASH') ?: '';
}

// Initialize dependencies
$db = new Database($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);
$mailer = new PHPMailerAdapter($EMAIL_PASSWORD);
$baseUrl = getenv('BASE_URL') ?: 'https://newsletter.szabo.jp';
$service = new NewsletterService($db, $mailer, $baseUrl);

return $service;
