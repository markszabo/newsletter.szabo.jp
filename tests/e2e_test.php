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
        
        // Set up confirmed subscribers
        $email1 = 'subscriber1@example.com';
        $email2 = 'subscriber2@example.com';
        $email3 = 'unconfirmed@example.com';
        
        $this->service->subscribe($email1);
        $this->service->subscribe($email2);
        $this->service->subscribe($email3);
        
        $sub1 = $this->service->getSubscriberByEmail($email1);
        $sub2 = $this->service->getSubscriberByEmail($email2);
        
        $this->service->confirmSubscription($sub1['token']);
        $this->service->confirmSubscription($sub2['token']);
        // Don't confirm email3
        
        $this->mailer->clear();
        
        // Create a mock feed file
        $feedContent = $this->createMockAtomFeed();
        $feedFile = sys_get_temp_dir() . '/test_feed.xml';
        file_put_contents($feedFile, $feedContent);
        
        // Send digest
        $key = 'test-key-123';
        $keyHash = hash('sha256', $key);
        $result = $this->service->sendDigest("file://$feedFile", $keyHash, $key);
        
        // Verify digest was sent only to confirmed subscribers
        $this->assertTrue($result['success'], "Digest should be sent successfully");
        $this->assertEquals('digest_sent', $result['message'], "Message should indicate digest was sent");
        $this->assertEquals(2, $result['sent_count'], "Should send to 2 confirmed subscribers");
        $this->assertEquals(2, $this->mailer->getEmailCount(), "Should send 2 emails");
        
        // Verify emails were sent to the right people
        $sentTo = array_map(function($e) { return $e['to']; }, $this->mailer->getSentEmails());
        $this->assertContains($email1, $sentTo, "Should send to subscriber 1");
        $this->assertContains($email2, $sentTo, "Should send to subscriber 2");
        $this->assertNotContains($email3, $sentTo, "Should NOT send to unconfirmed subscriber");
        
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
    
    private function createMockAtomFeed() {
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
        <title>New Test Post</title>
        <link href="https://szabo.jp/blog/test-post"/>
        <updated>$now</updated>
        <id>https://szabo.jp/blog/test-post</id>
        <content type="html">Test content</content>
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
