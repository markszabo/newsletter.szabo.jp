<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/MailerInterface.php';

class NewsletterService {
    private $db;
    private $mailer;
    private $baseUrl;

    public function __construct(Database $db, MailerInterface $mailer, $baseUrl = 'https://newsletter.szabo.jp') {
        $this->db = $db;
        $this->mailer = $mailer;
        $this->baseUrl = $baseUrl;
    }

    public function subscribe($email) {
        $email = strtolower(trim($email));
        $token = bin2hex(random_bytes(16));
        $this->db->addSubscriber($email, $token);

        $confirm_link = "{$this->baseUrl}/?action=confirm&token=$token";
        $this->mailer->send(
            $email,
            "Confirm your subscription to szabo.jp",
            "Thank you for subscribing to updates from <a href='https://szabo.jp/'>szabo.jp</a>.<br>
            Please confirm your subscription by clicking this link: <a href='$confirm_link'>Confirm Subscription</a><br><br>
            You will only ever receive updates about new posts on szabo.jp. You can unsubscribe at any time, and your data will never be shared with others.<br><br>
            Questions? Contact us at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>."
        );

        return $token;
    }

    public function confirmSubscription($token) {
        return $this->db->confirmSubscriber($token);
    }

    public function unsubscribe($token) {
        return $this->db->deleteSubscriber($token);
    }

    public function sendDigest($feedUrl, $sendDigestKeyHash, $providedKey) {
        if(empty($providedKey) || hash('sha256', $providedKey) != $sendDigestKeyHash) {
            return ['success' => false, 'error' => 'unauthorized'];
        }

        $feed = @simplexml_load_file($feedUrl);
        if ($feed === false) {
            return ['success' => false, 'error' => 'feed_load_failed'];
        }

        $new_post = null;
        $entry = $feed->entry[0];
        $updated = strtotime((string)$entry->updated);
        if ($updated > time() - 24*60*60*30) { // last post is expected to be within the last 24 hours
            $new_post = [
                'title' => (string)$entry->title,
                'link' => (string)$entry->link['href']
            ];
        }

        if (empty($new_post)) {
            return ['success' => true, 'message' => 'no_new_posts'];
        }

        $subscribers = $this->db->getConfirmedSubscribers();
        $sent_count = 0;

        foreach ($subscribers as $s) {
            $unsubscribe = "{$this->baseUrl}/?action=unsubscribe&token={$s['token']}";
            $msg = "A new posts was published on <a href='https://szabo.jp/'>szabo.jp</a>:<br>
                    <p><a href='{$new_post['link']}?s=newsletter'>{$new_post['title']}</a></p>
                    <hr>
                    <p>You are receiving this email because you subscribed to updates on szabo.jp. 
                    We will never send unrelated content or share your information. 
                    You can unsubscribe at any time by clicking below, which will also delete all of your data:</p>
                    <p><a href='$unsubscribe'>Unsubscribe</a></p>
                    <p>Questions? Contact us at <a href='mailto:newsletter@szabo.jp'>newsletter@szabo.jp</a>.</p>";
            if ($this->mailer->send($s['email'], "New post on szabo.jp: {$new_post['title']}", $msg)) {
                $sent_count++;
            }
        }

        return ['success' => true, 'message' => 'digest_sent', 'sent_count' => $sent_count, 'post' => $new_post];
    }

    public function getSubscriberByToken($token) {
        return $this->db->getSubscriberByToken($token);
    }

    public function getSubscriberByEmail($email) {
        return $this->db->getSubscriberByEmail($email);
    }
}
