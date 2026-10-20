<?php
namespace App\Integration;

/** Backend integration boundary; intentionally no credentials, network or client dependency. */
final class OpenAiTextProvider implements TextGenerationProvider
{
    public function generate(string $tool, array $facts, string $tone = 'sachlich', array $options = []): array
    {
        throw new \LogicException('OpenAI ist nicht verbunden. Backend-Key, Datenschutzfreigabe, Timeout und Token-/Budgetlimits müssen vor einer eigenen Integration eingerichtet und geprüft werden.');
    }
}
