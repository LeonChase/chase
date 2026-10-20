<?php
require dirname(__DIR__).'/vendor/autoload.php';
if (class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
    (new \Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
// Test accounts and database are synthetic and isolated from the local dev demo.
if (($_ENV['APP_ENV'] ?? '') !== 'test' || !str_contains($_ENV['DATABASE_URL'] ?? '', '/var/phpunit.db')) {
    throw new \LogicException('Tests may only use the isolated var/phpunit.db database.');
}
