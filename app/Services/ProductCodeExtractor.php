<?php

namespace App\Services;

final class ProductCodeExtractor
{
    public static function best(?string $storedCode, ?string ...$values): string
    {
        $stored = self::sanitize($storedCode);
        $storedKey = self::key($stored);
        $candidates = self::uniqueCandidates($values);

        if ($storedKey !== '') {
            $longerMatches = array_values(array_filter(
                $candidates,
                fn (string $candidate): bool => str_starts_with(self::key($candidate), $storedKey)
                    && strlen(self::key($candidate)) > strlen($storedKey)
            ));

            if ($longerMatches !== []) {
                return self::bestCandidate($longerMatches);
            }
        }

        $best = self::bestCandidate($candidates);

        if ($stored === '') {
            return $best;
        }

        return $stored;
    }

    public static function sanitize(?string $value): string
    {
        $text = mb_strtoupper(trim((string) $value), 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*([.\/-])\s*/u', '$1', $text) ?? $text;

        return trim($text, " \t\n\r\0\x0B-.,;/\\");
    }

    private static function uniqueCandidates(array $values): array
    {
        $bestByKey = [];

        foreach ($values as $value) {
            foreach (self::candidates($value) as $candidate) {
                $key = self::key($candidate);
                if ($key === '') {
                    continue;
                }

                $previous = $bestByKey[$key] ?? null;
                if ($previous === null || self::score($candidate) > self::score($previous)) {
                    $bestByKey[$key] = $candidate;
                }
            }
        }

        return array_values($bestByKey);
    }

    private static function candidates(?string $value): array
    {
        $text = rawurldecode((string) ($value ?? ''));
        $text = mb_strtoupper($text, 'UTF-8');
        $text = preg_replace('/[^A-Z0-9().\/-]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $patterns = [
            '/\b[A-Z]{1,8}-(?=[A-Z0-9().]*[A-Z])(?=[A-Z0-9().]*\d)[A-Z0-9().]+(?:-[A-Z0-9().]+){1,5}\b/u',
            '/\b[A-Z]{1,8}-(?=[A-Z0-9().]*[A-Z])(?=[A-Z0-9().]*\d)[A-Z0-9().]+\b/u',
            '/\b[A-Z]{2,12}\d[A-Z0-9().\/-]*\b/u',
        ];

        $candidates = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches) !== false) {
                foreach ($matches[0] as $match) {
                    $code = self::sanitize($match);
                    if (self::isValid($code)) {
                        $candidates[] = $code;
                    }
                }
            }
        }

        return $candidates;
    }

    private static function bestCandidate(array $candidates): string
    {
        if ($candidates === []) {
            return '';
        }

        usort($candidates, function (string $left, string $right): int {
            return self::score($right) <=> self::score($left)
                ?: strlen(self::key($right)) <=> strlen(self::key($left))
                ?: strcmp($left, $right);
        });

        return $candidates[0];
    }

    private static function key(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', self::sanitize($value)) ?? '';
    }

    private static function isValid(string $code): bool
    {
        $key = self::key($code);
        if (strlen($key) < 4 || strlen($key) > 48) {
            return false;
        }

        return preg_match('/[A-Z]/', $key) === 1 && preg_match('/\d/', $key) === 1;
    }

    private static function score(string $code): int
    {
        $code = self::sanitize($code);
        $segments = preg_split('/[-\s\/]+/', $code, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $score = min(40, strlen(self::key($code)));

        if (preg_match('/^[A-Z]{1,8}-(?=[A-Z0-9().]*[A-Z])(?=[A-Z0-9().]*\d)[A-Z0-9().]+(?:-[A-Z0-9().]+){1,5}$/', $code) === 1) {
            $score += 160;
        }

        if (str_contains($code, '(') || str_contains($code, ')')) {
            $score += 40;
        }

        if (count($segments) >= 3) {
            $score += 25 * min(4, count($segments) - 2);
        }

        if (str_contains($code, '-')) {
            $score += 15;
        }

        return $score;
    }
}
