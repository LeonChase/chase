<?php
namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/** Whitelisted, server-side property input validation. No network or persistence side effects. */
final class PropertyFormProcessor
{
    public function process(Request $request, array $existingData = []): array
    {
        $input = $request->request->all();
        $data = $existingData;
        $errors = [];
        foreach (PropertyFormSchema::fields() as $name => $field) {
            if (!array_key_exists($name, $input)) {
                if ($field['required'] && trim((string)($existingData[$name] ?? '')) === '') $errors[] = $field['label'].' ist erforderlich.';
                continue;
            }
            $value = $input[$name];
            if (!is_scalar($value) && $value !== null) {
                $errors[] = 'Bitte für „'.$field['label'].'“ einen einzelnen Wert angeben.';
                continue;
            }
            $value = trim((string) $value);
            if (!mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
                $errors[] = '„'.$field['label'].'“ enthält ungültige Zeichen.';
                continue;
            }
            if ($name === 'features') {
                $data[$name] = $this->parseLines($value, $field['label'], $errors);
                continue;
            }
            if (mb_strlen($value) > $field['maxlength']) $errors[] = '„'.$field['label'].'“ darf höchstens '.$field['maxlength'].' Zeichen lang sein.';
            if ($field['required'] && $value === '') $errors[] = $field['label'].' ist erforderlich.';
            switch ($field['type']) {
                case 'number':
                    $data[$name] = $this->number($value, $field, $errors);
                    break;
                case 'checkbox':
                    $normalized = strtolower($value);
                    if (!in_array($normalized, ['0','1','false','true','off','on','no','yes',''], true)) $errors[] = 'Bitte für „'.$field['label'].'“ eine gültige Auswahl treffen.';
                    else $data[$name] = in_array($normalized, ['1','true','on','yes'], true);
                    break;
                case 'select':
                    // Accept the lowercase condition strings used in the original fixtures.
                    $canonical = $value;
                    if ($value !== '' && !in_array($value, $field['options'], true)) {
                        $matches = array_filter($field['options'], fn(string $option) => mb_strtolower($option) === mb_strtolower($value));
                        if ($matches) $canonical = array_values($matches)[0];
                        else $errors[] = 'Bitte für „'.$field['label'].'“ eine gültige Auswahl treffen.';
                    }
                    $data[$name] = $canonical;
                    break;
                case 'email':
                    if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte für „'.$field['label'].'“ eine gültige E-Mail-Adresse angeben.';
                    $data[$name] = $value;
                    break;
                case 'date':
                    if ($value !== '' && !$this->validDate($value)) $errors[] = 'Bitte für „'.$field['label'].'“ ein gültiges Datum angeben.';
                    $data[$name] = $value;
                    break;
                default:
                    $data[$name] = $value;
            }
        }
        // Compatibility for existing clients; highlights are no longer a separate user tool.
        if (array_key_exists('sellingPoints', $input)) {
            if (is_string($input['sellingPoints'])) $data['sellingPoints'] = $this->parseLines($input['sellingPoints'], 'Bestätigte Merkmale', $errors);
            else $errors[] = 'Bestätigte Merkmale müssen als Text übergeben werden.';
        }
        if (array_key_exists('modernisations', $input)) $data['modernisations'] = $this->modernisations($input['modernisations'], $errors);
        // A hidden presence flag lets the UI intentionally clear every table row.
        elseif (($input['modernisations_present'] ?? null) === '1') $data['modernisations'] = [];
        if (array_key_exists('readyForReview', $input)) {
            $ready = $input['readyForReview'];
            if (!is_scalar($ready) || !in_array(strtolower((string)$ready), ['0','1','false','true','off','on','no','yes',''], true)) $errors[] = 'Bitte für die interne Freigabe eine gültige Auswahl treffen.';
            else $data['readyForReview'] = in_array(strtolower((string)$ready), ['1','true','on','yes'], true);
        }
        if (($data['energyCreatedAt'] ?? '') !== '' && ($data['energyValidUntil'] ?? '') !== '' && $this->validDate((string)$data['energyCreatedAt']) && $this->validDate((string)$data['energyValidUntil']) && $data['energyValidUntil'] < $data['energyCreatedAt']) {
            $errors[] = 'Die Gültigkeit des Energieausweises darf nicht vor dem Ausstellungsdatum enden.';
        }
        return ['data'=>$data, 'errors'=>array_values(array_unique($errors))];
    }

    private function number(string $value, array $field, array &$errors): int|float|string|null
    {
        if ($value === '') return null;
        $normalized = str_replace(',', '.', $value);
        if (str_contains($value, '.') && str_contains($value, ',') && preg_match('/^-?\d{1,3}(?:\.\d{3})+,\d+$/D', $value)) $normalized = str_replace(',', '.', str_replace('.', '', $value));
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $normalized) || !is_finite((float)$normalized)) {
            $errors[] = 'Bitte für „'.$field['label'].'“ eine gültige Zahl angeben.';
            return $value;
        }
        $number = (float) $normalized;
        if ($number < $field['min'] || $number > $field['max']) $errors[] = '„'.$field['label'].'“ muss zwischen '.$this->format($field['min']).' und '.$this->format($field['max']).' liegen.';
        if ($field['step'] === '1' && floor($number) !== $number) $errors[] = '„'.$field['label'].'“ muss eine ganze Zahl sein.';
        return $field['step'] === '1' ? (int)$number : $number;
    }

    private function parseLines(string $value, string $label, array &$errors): array
    {
        if (mb_strlen($value) > 8000) $errors[] = '„'.$label.'“ darf höchstens 8.000 Zeichen lang sein.';
        $lines = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/u', $value) ?: []), fn(string $line) => $line !== '')));
        if (count($lines) > 30) $errors[] = '„'.$label.'“ darf höchstens 30 Einträge enthalten.';
        foreach ($lines as $line) if (mb_strlen($line) > 250) $errors[] = 'Ein Eintrag in „'.$label.'“ darf höchstens 250 Zeichen lang sein.';
        return $lines;
    }

    private function modernisations(mixed $input, array &$errors): array
    {
        if (!is_array($input)) {
            $errors[] = 'Modernisierungen müssen als Tabellenzeilen übergeben werden.';
            return [];
        }
        if (count($input) > 12) $errors[] = 'Bitte höchstens 12 Modernisierungen erfassen.';
        $rows = [];
        foreach (array_values(array_slice($input, 0, 12)) as $index => $row) {
            $line = $index + 1;
            if (!is_array($row)) {
                $errors[] = 'Modernisierung '.$line.': ungültige Tabellenzeile.';
                continue;
            }
            $values = [];
            $invalid = false;
            foreach (['component','year','details'] as $key) {
                if (!is_scalar($row[$key] ?? '') && ($row[$key] ?? null) !== null) {
                    $errors[] = 'Modernisierung '.$line.': ungültige Eingabe.';
                    $invalid = true;
                    break;
                }
                $values[$key] = trim((string)($row[$key] ?? ''));
                if (!mb_check_encoding($values[$key], 'UTF-8') || str_contains($values[$key], "\0")) $invalid = true;
            }
            if ($invalid) {
                $errors[] = 'Modernisierung '.$line.': bitte gültige Textwerte angeben.';
                continue;
            }
            // Empty suggested rows are not facts; the UI may preselect their component.
            if ($values['year'] === '' && $values['details'] === '') continue;
            if (!in_array($values['component'], PropertyFormSchema::MODERNISATION_COMPONENTS, true)) $errors[] = 'Modernisierung '.$line.': bitte einen Bauteil auswählen.';
            if (mb_strlen($values['details']) > 1000) $errors[] = 'Modernisierung '.$line.': der Hinweis darf höchstens 1.000 Zeichen lang sein.';
            if ($values['year'] !== '' && (!preg_match('/^\d{4}$/D', $values['year']) || (int)$values['year'] < 1900 || (int)$values['year'] > (int)date('Y') + 5)) $errors[] = 'Modernisierung '.$line.': bitte ein plausibles Jahr ab 1900 angeben.';
            $values['year'] = $values['year'] === '' ? null : (preg_match('/^\d{4}$/D', $values['year']) ? (int)$values['year'] : $values['year']);
            $rows[] = $values;
        }
        return $rows;
    }

    private function validDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    private function format(int|float $value): string
    {
        return number_format($value, floor($value) === (float)$value ? 0 : 2, ',', '.');
    }
}
