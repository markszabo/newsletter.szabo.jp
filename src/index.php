<?php

$service = require __DIR__ . '/bootstrap.php';

// Load configuration for digest key and other secrets
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
} else { // Fallback to environment variables for testing
    $SEND_DIGEST_KEY_HASH = getenv('SEND_DIGEST_KEY_HASH') ?: '';
}

// === Handle actions ===
$action = $_GET['action'] ?? '';

if ($action === 'subscribe' && !empty($_POST['email'])) {
    $service->subscribe($_POST['email']);
    echo "<p>Thanks for signing up! Please check your email to confirm your subscription.</p>
          <p>You will only ever receive updates about new posts on <a href='https://szabo.jp/'>szabo.jp</a>. 
          You can unsubscribe at any time, and your data will never be shared with others. 
          Your data will be managed by Mark Szabo, the creator of szabo.jp.</p>
          <p>If you have any questions, please reach out at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
    exit;
}

if ($action === 'confirm' && !empty($_GET['token'])) {
    $service->confirmSubscription($_GET['token']);
    echo "<p>Your subscription has been confirmed. 🎉</p>
          <p>From now on, you'll receive updates about new posts on <a href='https://szabo.jp/'>szabo.jp</a>.</p>
          <p>You can unsubscribe at any time, and all your data will be deleted if you do. 
          We never share your information with anyone.</p>
          <p>Questions? Contact us at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
    exit;
}

if ($action === 'unsubscribe' && !empty($_GET['token'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $service->unsubscribe($_GET['token']);
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
    $result = $service->sendDigest("https://szabo.jp/atom.xml", $SEND_DIGEST_KEY_HASH, $_GET['key'] ?? '');
    
    if (!$result['success']) {
        if ($result['error'] === 'unauthorized') {
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo '401 Unauthorized';
        } else {
            die("Failed to load feed.");
        }
        exit;
    }
    
    if ($result['message'] === 'no_new_posts') {
        echo "No new posts.";
    } else {
        echo "Digest sent.";
    }
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
      max-width: 600px;
      margin: 50px auto;
      font-family: sans-serif;
      padding: 20px;
    }
    input[type="email"] {
      width: 100%;
      padding: 10px;
      font-size: 16px;
      margin-bottom: 10px;
    }
    button {
      padding: 10px 20px;
      font-size: 16px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <h1>Subscribe to Updates</h1>
  <p>Get notified about new posts on <a href="https://szabo.jp/">szabo.jp</a>.</p>
  <form method="post" action="?action=subscribe">
    <input type="email" name="email" placeholder="Your email address" required>
    <button type="submit">Subscribe</button>
  </form>
  <p style="font-size: 0.9em; color: #666;">
    Your email will only be used to send you updates about new posts. 
    You can unsubscribe at any time, and your data will never be shared with others.
  </p>
</body>
</html>
