<?php

interface MailerInterface {
    public function send($to, $subject, $body);
}
