<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

require 'config.php';

// === Database Connection ===
$pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// === Create table if not exists ===
$pdo->exec("
    CREATE TABLE IF NOT EXISTS subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        confirmed TINYINT(1) DEFAULT 0,
        token VARCHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// === Helper: Send Mail ===
function sendMail($email_password, $to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.ininet.hu';
        $mail->SMTPAuth = true;
        $mail->Username = 'newsletter@szabo.jp';
        $mail->Password = $email_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('newsletter@szabo.jp', 'Mark from szabo.jp');
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

// === Handle actions ===
$action = $_GET['action'] ?? '';

if ($action === 'subscribe' && !empty($_POST['email'])) {
    $email = strtolower(trim($_POST['email']));
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO subscribers (email, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token=?");
    $stmt->execute([$email, $token, $token]);

    $confirm_link = "https://newsletter.szabo.jp/?action=confirm&token=$token";
    sendMail(
        $EMAIL_PASSWORD,
        $email,
        "Confirm your subscription to szabo.jp",
        "Thank you for subscribing to updates from <a href='https://szabo.jp/'>szabo.jp</a>.<br>
        Please confirm your subscription by clicking this link: <a href='$confirm_link'>Confirm Subscription</a><br><br>
        You will only ever receive updates about new posts on szabo.jp. You can unsubscribe at any time, and your data will never be shared with others.<br><br>
        Questions? Contact us at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>."
    );
    echo "<p>Thanks for signing up! Please check your email to confirm your subscription.</p>
          <p>You will only ever receive updates about new posts on <a href='https://szabo.jp/'>szabo.jp</a>. 
          You can unsubscribe at any time, and your data will never be shared with others. 
          Your data will be managed by Mark Szabo, the creator of szabo.jp.</p>
          <p>If you have any questions, please reach out at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
    exit;
}

if ($action === 'confirm' && !empty($_GET['token'])) {
    $stmt = $pdo->prepare("UPDATE subscribers SET confirmed=1 WHERE token=?");
    $stmt->execute([$_GET['token']]);
    echo "<p>Your subscription has been confirmed. 🎉</p>
          <p>From now on, you’ll receive updates about new posts on <a href='https://szabo.jp/'>szabo.jp</a>.</p>
          <p>You can unsubscribe at any time, and all your data will be deleted if you do. 
          We never share your information with anyone.</p>
          <p>Questions? Contact us at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
    exit;
}

if ($action === 'unsubscribe' && !empty($_GET['token'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("DELETE FROM subscribers WHERE token=?");
        $stmt->execute([$_GET['token']]);
        echo "<p>You have been unsubscribed, and all of your data has been permanently deleted from our system.</p>
              <p>Thank you for reading <a href='https://szabo.jp/'>szabo.jp</a>! We hope to see you again in the future.</p>
              <p>If you have any questions, please reach out at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
        exit;
    }
    echo "<p>You are about to unsubscribe from updates on <a href='https://szabo.jp/'>szabo.jp</a>.</p>
          <p>If you confirm, all of your data will be permanently deleted, and you will no longer receive any emails from us.</p>
          <form method='post'><button type='submit'>Confirm Unsubscribe</button></form>
          <p>Questions? Contact us at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
    exit;
}

if ($action === 'send-digest') {
    if(empty($_GET['key']) || hash('sha256', $_GET['key']) != $SEND_DIGEST_KEY_HASH) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo '401 Unauthorized';
        exit;
    }
    $feed_url = "https://szabo.jp/atom.xml";
    $feed = @simplexml_load_file($feed_url);

    if ($feed === false) {
        die("Failed to load feed.");
    }

    $new_post;
    $entry = $feed->entry[0];
    $updated = strtotime((string)$entry->updated);
    if ($updated > time() - 24*60*60) {
        $new_post = [
            'title' => (string)$entry->title,
            'link' => (string)$entry->link['href']
            ];
    }

    if (empty($new_post)) {
        echo "No new posts.";
        exit;
    }

    $stmt = $pdo->query("SELECT email, token FROM subscribers WHERE confirmed=1");
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subscribers as $s) {
        $unsubscribe = "https://newsletter.szabo.jp/?action=unsubscribe&token={$s['token']}";
        $msg = "A new posts was published on <a href='https://szabo.jp/'>szabo.jp</a>:<br>
                <p><a href='{$new_post['link']}?s=newsletter'>{$new_post['title']}</a></p>
                <hr>
                <p>You are receiving this email because you subscribed to updates on szabo.jp. 
                We will never send unrelated content or share your information. 
                You can unsubscribe at any time by clicking below, which will also delete all of your data:</p>
                <p><a href='$unsubscribe'>Unsubscribe</a></p>
                <p>Questions? Contact us at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
        sendMail($EMAIL_PASSWORD, $s['email'], "New post on szabo.jp: {$new_post['title']}", $msg);
    }
    echo "Digest sent.";
    exit;
}

// === Default page (subscription form) ===
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Subscribe to szabo.jp</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Helvetica, Arial, sans-serif;
      background: #fafafa;
      color: #222;
      max-width: 650px;
      margin: 40px auto;
      padding: 0 16px;
      line-height: 1.6;
    }
    h1 {
      font-size: 1.8rem;
      margin-bottom: 0.5em;
      color: #000;
    }
    p {
      margin-bottom: 1.2em;
    }
    form {
      margin-top: 1em;
      display: flex;
      gap: 8px;
    }
    input[type="email"] {
      flex: 1;
      padding: 10px 12px;
      font-size: 1rem;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    button {
      padding: 10px 18px;
      font-size: 1rem;
      background: #000;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    button:hover {
      background: #444;
    }
    a {
      color: #000;
      text-decoration: underline;
    }
    footer {
      margin-top: 3em;
      font-size: 0.9rem;
      color: #666;
    }
  </style>
</head>
<body>
  <h1>Subscribe to szabo.jp</h1>
  <p>Enter your email to get notified when new posts are published on <a href="https://szabo.jp/">szabo.jp</a>.</p>

  <form method="post" action="?action=subscribe">
    <input type="email" name="email" required placeholder="Enter your email">
    <button type="submit">Subscribe</button>
  </form>

  <p>You will only ever receive updates about new posts on szabo.jp. You can unsubscribe at any time, and your data will never be shared with others. Your data is managed by Mark Szabo, the creator of szabo.jp.</p>

  <footer>
    <p>Questions? Contact <a href="mailto:newsletter@szabo.jp">newsletter@szabo.jp</a>.</p>
  </footer>
</body>
</html>
