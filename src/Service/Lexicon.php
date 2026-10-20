<?php

namespace App\Service;

/** Read-only reference content extracted from the supplied CHASE lexicon PDF. */
final class Lexicon
{
    /** @var array{source: array, entries: array}|null */
    private ?array $content = null;

    public function __construct(private readonly string $projectDir)
    {
    }

    /** @return list<array{id: string, term: string, definition: string, page: int}> */
    public function all(): array
    {
        return $this->load()['entries'];
    }

    /**
     * Matches every query word in the term or definition, ranking a matching
     * term/prefix before definition-only matches. No external search service.
     *
     * @return list<array{id: string, term: string, definition: string, page: int}>
     */
    public function search(string $q): array
    {
        $query = $this->normalize(mb_substr(trim($q), 0, 200));
        if ($query === '') {
            return $this->all();
        }
        $words = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matches = [];
        foreach ($this->all() as $position => $entry) {
            $term = $this->normalize($entry['term']);
            $searchable = $term . ' ' . $this->normalize($entry['definition']);
            foreach ($words as $word) {
                if (!str_contains($searchable, $word)) {
                    continue 2;
                }
            }
            $rank = match (true) {
                $term === $query => 0,
                str_starts_with($term, $query) => 1,
                str_contains($term, $query) => 2,
                default => 3,
            };
            $matches[] = ['rank' => $rank, 'position' => $position, 'entry' => $entry];
        }
        usort($matches, static fn (array $a, array $b): int => [$a['rank'], $a['position']] <=> [$b['rank'], $b['position']]);

        return array_column($matches, 'entry');
    }

    /** @return array{id: string, term: string, definition: string, page: int}|null */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }

        return null;
    }

    /** Original source filename, SHA-256, printed page count and usage notices. */
    public function source(): array
    {
        return $this->load()['source'];
    }

    private function normalize(string $text): string
    {
        return strtr(mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)) ?? '', 'UTF-8'), [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        ]);
    }

    /** @return array{source: array, entries: array} */
    private function load(): array
    {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = ['source' => [
            'title' => 'CHASE Immobilienlexikon A–Z',
            'filename' => 'CHASE_Immobilienlexikon_A-Z.pdf',
            'available' => false,
            'entryCount' => 0,
            'pageCount' => 0,
        ], 'entries' => []];
        $file = $this->projectDir . '/data/lexicon.json';
        if (!is_file($file) || !is_readable($file)) {
            return $this->content;
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->content;
        }
        if (!is_array($data) || !is_array($data['source'] ?? null) || !is_array($data['entries'] ?? null)) {
            return $this->content;
        }
        $entries = [];
        foreach ($data['entries'] as $entry) {
            if (!is_array($entry) || !is_string($entry['id'] ?? null) || !is_string($entry['term'] ?? null)
                || !is_string($entry['definition'] ?? null) || !is_int($entry['page'] ?? null)
                || $entry['id'] === '' || $entry['term'] === '' || $entry['definition'] === '' || $entry['page'] < 1) {
                // An inconsistent local data file is unavailable rather than
                // offering a silently incomplete reference collection.
                return $this->content;
            }
            $entries[] = $entry;
        }
        if (count($entries) !== ($data['source']['entryCount'] ?? null)) {
            return $this->content;
        }
        $this->content = ['source' => $data['source'] + ['available' => true], 'entries' => $entries];

        return $this->content;
    }
}
