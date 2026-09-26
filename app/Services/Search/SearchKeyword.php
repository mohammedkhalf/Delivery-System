<?php

namespace App\Services\Search;

use Illuminate\Support\Str;

class SearchKeyword
{
    private const FILLER = ['a', 'an', 'the', 'of', 'and', 'or', 'for', 'in', 'on', 'to', 'by', 'with', 'page', 'menu', 'my'];

    private const NO_MATCH = 90;

    private string $raw;

    private string $phrase;

    private array $tokens;

    public function __construct(mixed $keyword)
    {
        $this->raw = is_scalar($keyword) ? trim((string) $keyword) : '';
        $this->phrase = self::normalize($this->raw);
        $this->tokens = self::tokenize($this->phrase);
    }

    public static function normalize(?string $text): string
    {
        $text = strtolower((string) $text);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private static function tokenize(string $phrase): array
    {
        if ($phrase === '') {
            return [];
        }

        $words = explode(' ', $phrase);

        $tokens = array_filter($words, function ($word) {
            return $word !== ''
                && ! in_array($word, self::FILLER, true)
                && (strlen($word) > 1 || is_numeric($word));
        });

        $tokens = array_values(array_unique($tokens));

        return $tokens !== [] ? $tokens : array_values(array_unique(array_filter($words)));
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function phrase(): string
    {
        return $this->phrase;
    }

    public function tokens(): array
    {
        return $this->tokens;
    }

    public function isEmpty(): bool
    {
        return $this->raw === '';
    }

    public function isId(): bool
    {
        return is_numeric($this->raw) && $this->raw > 0;
    }

    public function mayBeContactNumber(): bool
    {
        if (! $this->isId()) {
            return false;
        }

        return str_starts_with($this->raw, '+')
            || str_starts_with($this->raw, '0')
            || strlen($this->raw) >= 10;
    }

    public function likeValue(): string
    {
        return addcslashes($this->raw, '%_\\');
    }

    public function score(string $routeName, string $uri, string $keywords = ''): ?int
    {
        if ($this->phrase === '' || $this->tokens === []) {
            return null;
        }

        $name = self::normalize($routeName);
        $path = self::normalize($uri);
        $blob = self::normalize($keywords);
        $nameAndPath = trim($name . ' ' . $path);

        if ($name === $this->phrase) {
            return 0;
        }
        if ($name !== '' && str_starts_with($name, $this->phrase)) {
            return 10;
        }
        if ($name !== '' && str_contains($name, $this->phrase)) {
            return 20;
        }
        if ($this->containsAll($name)) {
            return 30;
        }
        if ($path !== '' && str_contains($path, $this->phrase)) {
            return 40;
        }
        if ($this->containsAll($nameAndPath)) {
            return 50;
        }
        if (! $this->containsAll(trim($nameAndPath . ' ' . $blob))) {
            return null;
        }
        if ($blob !== '' && str_contains($blob, $this->phrase)) {
            return 60;
        }

        foreach ($this->tokens as $token) {
            if ($this->contains($nameAndPath, $token)) {
                return 70;
            }
        }

        return count($this->tokens) === 1 ? 80 : null;
    }

    public function matches(string $routeName, string $uri, string $keywords = ''): bool
    {
        return $this->score($routeName, $uri, $keywords) !== null;
    }

    private function containsAll(string $haystack): bool
    {
        if ($this->tokens === []) {
            return false;
        }

        foreach ($this->tokens as $token) {
            if (! $this->contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    private function contains(string $haystack, string $token): bool
    {
        if ($token === '' || str_contains($haystack, $token)) {
            return true;
        }

        foreach ([Str::singular($token), Str::plural($token)] as $variant) {
            if ($variant !== $token && $variant !== '' && str_contains($haystack, $variant)) {
                return true;
            }
        }

        return false;
    }

    public function rank(array $rows, ?int $limit = null): array
    {
        $scored = [];
        foreach ($rows as $index => $row) {
            $scored[] = [
                'order' => $index,
                // Curated shortcuts flagged `pinned` sort above everything (0 before 1).
                'pinned' => empty($row['pinned']) ? 1 : 0,
                'score' => $this->score($row['routeName'] ?? '', $row['URI'] ?? '', $row['keywords'] ?? '') ?? self::NO_MATCH,
                'row' => $row,
            ];
        }

        usort($scored, function ($a, $b) {
            return [$a['pinned'], $a['score'], $a['order']] <=> [$b['pinned'], $b['score'], $b['order']];
        });

        $ranked = array_map(function ($entry) {
            $row = $entry['row'];
            unset($row['keywords'], $row['pinned']);

            return $row;
        }, $scored);

        return $limit !== null ? array_slice($ranked, 0, $limit) : $ranked;
    }
}
