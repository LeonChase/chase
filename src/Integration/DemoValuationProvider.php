<?php
namespace App\Integration;

final class DemoValuationProvider implements PropertyValuationProvider
{
    public function calculate(array $inputs): array
    {
        if (!is_numeric($inputs['monthlyRent'] ?? null) || !is_numeric($inputs['factor'] ?? null)) {
            throw new \InvalidArgumentException('Bitte Miete und Faktor als gültige Zahlen eingeben.');
        }
        $monthly = (float) ($inputs['monthlyRent'] ?? 0);
        $factor = (float) ($inputs['factor'] ?? 0);
        if (!is_finite($monthly) || !is_finite($factor) || $monthly <= 0 || $monthly > 10000000 || $factor <= 0 || $factor > 100) {
            throw new \InvalidArgumentException('Bitte eine Monatsnettokaltmiete bis 10.000.000 € und einen frei gewählten Faktor zwischen 0 und 100 eingeben.');
        }
        return ['monthlyRent' => $monthly, 'annualRent' => $monthly * 12, 'factor' => $factor, 'result' => round($monthly * 12 * $factor, 2), 'mode' => 'Mathematisches Demo-Beispiel', 'disclaimer' => 'Keine Markt- oder Verkehrswertermittlung. Keine Marktdaten. Der Faktor wird von Ihnen eingegeben und ist keine Empfehlung.'];
    }
}
