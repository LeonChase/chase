<?php
namespace App\Integration;

/** No method in this adapter performs an external request. */
final class DisabledExternalProvider implements ImageProcessingProvider, DocumentExtractionProvider, NotificationProvider, SocialPublishingProvider
{
    public function process(string $privatePath, array $options = []): array { throw $this->unavailable(); }
    public function extract(string $privatePath): array { throw $this->unavailable(); }
    public function send(string $userId, string $subject, string $body): void { throw $this->unavailable(); }
    public function publish(string $channel, array $content): string { throw $this->unavailable(); }
    private function unavailable(): \LogicException { return new \LogicException('Externe API nicht verbunden. In dieser Demo werden keine Dateien übertragen und keine Nachrichten veröffentlicht oder versendet.'); }
}
