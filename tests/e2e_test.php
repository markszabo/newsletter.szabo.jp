<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/NewsletterService.php';
require_once __DIR__ . '/MockMailer.php';

class NewsletterE2ETest {
    private $db;
    private $mailer;
    private $service;
    private $baseUrl = 'http://localhost:8080';
    
    public function __construct() {
        // Use environment variables for database connection
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbPort = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: 'newsletter_test';
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPass = getenv('DB_PASS') ?: 'testpass';
        
        $this->db = new Database($dbHost, $dbName, $dbUser, $dbPass);
        $this->mailer = new MockMailer();
        $this->service = new NewsletterService($this->db, $this->mailer, $this->baseUrl);
    }
    
    public function setUp() {
        // Clear database
        $pdo = $this->db->getPDO();
        $pdo->exec("TRUNCATE TABLE subscribers");
        
        // Clear mock mailer
        $this->mailer->clear();
    }
    
    public function testFullSubscriptionFlow() {
        echo "Running: testFullSubscriptionFlow\n";
        
        // Step 1: User subscribes
        $email = 'test@example.com';
        $token = $this->service->subscribe($email);
        
        // Verify subscription email was sent
        $this->assertEquals(1, $this->mailer->getEmailCount(), "Should send 1 confirmation email");
        $confirmEmail = $this->mailer->getLastEmail();
        $this->assertEquals($email, $confirmEmail['to'], "Email should be sent to subscriber");
        $this->assertContains('Confirm your subscription', $confirmEmail['subject'], "Subject should mention confirmation");
        $this->assertContains('action=confirm', $confirmEmail['body'], "Body should contain confirmation link");
        
        // Verify user exists but is not confirmed
        $subscriber = $this->service->getSubscriberByEmail($email);
        $this->assertNotNull($subscriber, "Subscriber should exist in database");
        $this->assertEquals(0, $subscriber['confirmed'], "Subscriber should not be confirmed yet");
        
        // Step 2: User confirms subscription
        $this->service->confirmSubscription($subscriber['token']);
        
        // Verify user is now confirmed
        $subscriber = $this->service->getSubscriberByEmail($email);
        $this->assertEquals(1, $subscriber['confirmed'], "Subscriber should be confirmed");
        
        echo "✓ testFullSubscriptionFlow passed\n\n";
    }
    
    public function testDigestSending() {
        echo "Running: testDigestSending\n";
        
        // Step 1: Sign up 3 users
        $email1 = 'subscriber1@example.com';
        $email2 = 'subscriber2@example.com';
        $email3 = 'unconfirmed@example.com';
        
        $this->service->subscribe($email1);
        $this->service->subscribe($email2);
        $this->service->subscribe($email3);
        
        // Step 2: Confirm 2 of the users (not the third)
        $sub1 = $this->service->getSubscriberByEmail($email1);
        $sub2 = $this->service->getSubscriberByEmail($email2);
        
        $this->service->confirmSubscription($sub1['token']);
        $this->service->confirmSubscription($sub2['token']);
        // Don't confirm email3
        
        // Verify confirmation status
        $confirmedSub1 = $this->service->getSubscriberByEmail($email1);
        $confirmedSub2 = $this->service->getSubscriberByEmail($email2);
        $unconfirmedSub3 = $this->service->getSubscriberByEmail($email3);
        
        $this->assertEquals(1, $confirmedSub1['confirmed'], "Subscriber 1 should be confirmed");
        $this->assertEquals(1, $confirmedSub2['confirmed'], "Subscriber 2 should be confirmed");
        $this->assertEquals(0, $unconfirmedSub3['confirmed'], "Subscriber 3 should NOT be confirmed");
        
        $this->mailer->clear();
        
        // Step 3: Create a mock feed and send first digest
        $feedContent = $this->createMockAtomFeed('First Post');
        $feedFile = sys_get_temp_dir() . '/test_feed.xml';
        file_put_contents($feedFile, $feedContent);
        
        $key = 'test-key-123';
        $keyHash = hash('sha256', $key);
        $result = $this->service->sendDigest("file://$feedFile", $keyHash, $key);
        
        // Step 4: Verify only 2 users (confirmed ones) received the digest, not the third
        $this->assertTrue($result['success'], "First digest should be sent successfully");
        $this->assertEquals('digest_sent', $result['message'], "Message should indicate digest was sent");
        $this->assertEquals(2, $result['sent_count'], "Should send to 2 confirmed subscribers");
        $this->assertEquals(2, $this->mailer->getEmailCount(), "Should send 2 emails for first digest");
        
        $sentTo = array_map(function($e) { return $e['to']; }, $this->mailer->getSentEmails());
        $this->assertContains($email1, $sentTo, "First digest: Should send to subscriber 1");
        $this->assertContains($email2, $sentTo, "First digest: Should send to subscriber 2");
        $this->assertNotContains($email3, $sentTo, "First digest: Should NOT send to unconfirmed subscriber 3");
        
        // Verify email content
        $emails = $this->mailer->getSentEmails();
        foreach ($emails as $email) {
            $this->assertContains('First Post', $email['body'], "Email should contain the post title");
        }
        
        // Step 5: Unsubscribe the second user
        $this->service->unsubscribe($sub2['token']);
        
        // Verify user 2 is deleted
        $deletedSub2 = $this->service->getSubscriberByEmail($email2);
        $this->assertFalse($deletedSub2, "Subscriber 2 should be deleted after unsubscribe");
        
        $this->mailer->clear();
        
        // Step 6: Create a second digest with new content
        $feedContent2 = $this->createMockAtomFeed('Second Post');
        file_put_contents($feedFile, $feedContent2);
        
        $result2 = $this->service->sendDigest("file://$feedFile", $keyHash, $key);
        
        // Step 7: Verify only the first user gets the second digest
        $this->assertTrue($result2['success'], "Second digest should be sent successfully");
        $this->assertEquals('digest_sent', $result2['message'], "Second digest should be sent");
        $this->assertEquals(1, $result2['sent_count'], "Should send to only 1 subscriber now");
        $this->assertEquals(1, $this->mailer->getEmailCount(), "Should send only 1 email for second digest");
        
        $sentTo2 = array_map(function($e) { return $e['to']; }, $this->mailer->getSentEmails());
        $this->assertContains($email1, $sentTo2, "Second digest: Should send to subscriber 1");
        $this->assertNotContains($email2, $sentTo2, "Second digest: Should NOT send to unsubscribed subscriber 2");
        $this->assertNotContains($email3, $sentTo2, "Second digest: Should NOT send to unconfirmed subscriber 3");
        
        // Verify second email content
        $secondEmail = $this->mailer->getLastEmail();
        $this->assertContains('Second Post', $secondEmail['body'], "Second email should contain the new post title");
        $this->assertEquals($email1, $secondEmail['to'], "Second email should be sent to subscriber 1");
        
        // Clean up
        unlink($feedFile);
        
        echo "✓ testDigestSending passed\n\n";
    }
    
    public function testUnsubscribeFlow() {
        echo "Running: testUnsubscribeFlow\n";
        
        // Subscribe and confirm
        $email = 'unsubscriber@example.com';
        $this->service->subscribe($email);
        $subscriber = $this->service->getSubscriberByEmail($email);
        $this->service->confirmSubscription($subscriber['token']);
        
        // Verify user exists
        $this->assertNotNull($subscriber, "Subscriber should exist");
        
        // Unsubscribe
        $this->service->unsubscribe($subscriber['token']);
        
        // Verify user is deleted
        $deletedSubscriber = $this->service->getSubscriberByEmail($email);
        $this->assertFalse($deletedSubscriber, "Subscriber should be deleted");
        
        echo "✓ testUnsubscribeFlow passed\n\n";
    }
    
    public function testUnauthorizedDigestAccess() {
        echo "Running: testUnauthorizedDigestAccess\n";
        
        $keyHash = hash('sha256', 'correct-key');
        $result = $this->service->sendDigest("https://szabo.jp/atom.xml", $keyHash, 'wrong-key');
        
        $this->assertFalse($result['success'], "Should fail with wrong key");
        $this->assertEquals('unauthorized', $result['error'], "Should return unauthorized error");
        
        echo "✓ testUnauthorizedDigestAccess passed\n\n";
    }
    
    public function testDuplicateSubscription() {
        echo "Running: testDuplicateSubscription\n";
        
        $email = 'duplicate@example.com';
        
        // Subscribe once
        $token1 = $this->service->subscribe($email);
        $this->mailer->clear();
        
        // Subscribe again
        $token2 = $this->service->subscribe($email);
        
        // Should send a new confirmation email
        $this->assertEquals(1, $this->mailer->getEmailCount(), "Should send new confirmation email");
        
        // Should update the token
        $subscriber = $this->service->getSubscriberByEmail($email);
        $this->assertEquals($subscriber['token'], $token2, "Token should be updated");
        
        echo "✓ testDuplicateSubscription passed\n\n";
    }
    
    // Helper methods
    private function assertEquals($expected, $actual, $message) {
        if ($expected !== $actual) {
            throw new Exception("Assertion failed: $message\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
        }
    }
    
    private function assertNotNull($value, $message) {
        if ($value === null) {
            throw new Exception("Assertion failed: $message\nValue should not be null");
        }
    }
    
    private function assertTrue($value, $message) {
        if ($value !== true) {
            throw new Exception("Assertion failed: $message\nExpected true, got: " . var_export($value, true));
        }
    }
    
    private function assertFalse($value, $message) {
        if ($value !== false) {
            throw new Exception("Assertion failed: $message\nExpected false, got: " . var_export($value, true));
        }
    }
    
    private function assertContains($needle, $haystack, $message) {
        if (is_array($haystack)) {
            if (!in_array($needle, $haystack)) {
                throw new Exception("Assertion failed: $message\nValue '$needle' not found in array: " . var_export($haystack, true));
            }
        } else {
            if (strpos($haystack, $needle) === false) {
                throw new Exception("Assertion failed: $message\nString '$needle' not found in: $haystack");
            }
        }
    }
    
    private function assertNotContains($needle, $haystack, $message) {
        if (in_array($needle, $haystack)) {
            throw new Exception("Assertion failed: $message\nValue '$needle' should not be in array");
        }
    }
    
    private function createMockAtomFeed($postTitle = 'New Test Post') {
        $now = date('c');
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Test Blog</title>
    <link href="https://szabo.jp/atom.xml" rel="self"/>
    <link href="https://szabo.jp/"/>
    <updated>$now</updated>
    <id>https://szabo.jp/</id>
    <entry>
        <title>$postTitle</title>
        <link href="https://szabo.jp/blog/test-post"/>
        <updated>$now</updated>
        <id>https://szabo.jp/blog/test-post</id>
        <content type="html">Test content for $postTitle</content>
    </entry>
</feed>
XML;
    }
}

// Run tests
try {
    $test = new NewsletterE2ETest();
    
    echo "=== Running Newsletter E2E Tests ===\n\n";
    
    $test->setUp();
    $test->testFullSubscriptionFlow();
    
    $test->setUp();
    $test->testDigestSending();
    
    $test->setUp();
    $test->testUnsubscribeFlow();
    
    $test->setUp();
    $test->testUnauthorizedDigestAccess();
    
    $test->setUp();
    $test->testDuplicateSubscription();
    
    echo "=== All tests passed! ✓ ===\n";
    exit(0);
    
} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
