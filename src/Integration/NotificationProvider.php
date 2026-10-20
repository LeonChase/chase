<?php
namespace App\Integration;
interface NotificationProvider { public function send(string $userId, string $subject, string $body): void; }
