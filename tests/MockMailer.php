<?php

require_once __DIR__ . '/../src/MailerInterface.php';

class MockMailer implements MailerInterface {
    private $sentEmails = [];

    public function send($to, $subject, $body) {
        $this->sentEmails[] = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'timestamp' => time()
        ];
        return true;
    }

    public function getSentEmails() {
        return $this->sentEmails;
    }

    public function getLastEmail() {
        return end($this->sentEmails) ?: null;
    }

    public function getEmailsSentTo($email) {
        return array_filter($this->sentEmails, function($e) use ($email) {
            return $e['to'] === $email;
        });
    }

    public function clear() {
        $this->sentEmails = [];
    }

    public function getEmailCount() {
        return count($this->sentEmails);
    }
}
