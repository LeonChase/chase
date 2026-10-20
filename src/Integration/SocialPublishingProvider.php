<?php
namespace App\Integration;
interface SocialPublishingProvider { public function publish(string $channel, array $content): string; }
