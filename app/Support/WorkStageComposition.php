<?php

namespace App\Support;

class WorkStageComposition
{
    /**
     * @return list<string>
     */
    public static function items(mixed $composition): array
    {
        if (is_array($composition)) {
            return self::cleanItems(array_map(
                static fn ($item) => is_scalar($item) ? (string) $item : '',
                $composition
            ));
        }

        $text = trim((string) $composition);

        if ($text === '') {
            return [];
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (str_contains($text, "\n")) {
            return self::cleanItems(preg_split('/\n+/u', $text) ?: []);
        }

        // Inline: "1) Первый 2) Второй 3) Третий"
        if (preg_match_all('/(?:^|\s)\d+[.)]\s+/u', $text) >= 2) {
            $parts = preg_split('/(?:^|\s)\d+[.)]\s+/u', $text) ?: [];

            return self::cleanItems($parts);
        }

        // Inline bullets: "• Первый • Второй" / "- Первый - Второй"
        if (preg_match_all('/(?:^|\s)[-–—*•]\s+/u', $text) >= 2) {
            $parts = preg_split('/(?:^|\s)[-–—*•]\s+/u', $text) ?: [];

            return self::cleanItems($parts);
        }

        // Semicolon-separated points on one line.
        if (str_contains($text, ';')) {
            $parts = self::cleanItems(preg_split('/\s*;\s*/u', $text) ?: []);

            if (count($parts) >= 2) {
                return $parts;
            }
        }

        // Comma-separated short points: "Пункт А, пункт Б, пункт В"
        if (str_contains($text, ',')) {
            $parts = self::cleanItems(preg_split('/\s*,\s*/u', $text) ?: []);

            if (count($parts) >= 2) {
                $lengths = array_map(static fn (string $part) => mb_strlen($part), $parts);
                $maxLen = max($lengths);
                $avgLen = array_sum($lengths) / count($lengths);

                if ($maxLen <= 140 && $avgLen <= 90) {
                    return $parts;
                }
            }
        }

        return self::cleanItems([$text]);
    }

    public static function format(mixed $composition): ?string
    {
        $items = self::items($composition);

        if ($items === []) {
            return null;
        }

        $numbered = [];

        foreach ($items as $index => $item) {
            $numbered[] = ($index + 1).') '.$item;
        }

        return implode("\n", $numbered);
    }

    /**
     * @param  list<string|null>  $chunks
     * @return list<string>
     */
    private static function cleanItems(array $chunks): array
    {
        $items = [];

        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);

            if ($chunk === '') {
                continue;
            }

            $chunk = preg_replace('/^(?:\d+[.)]\s*|[-–—*•]\s+)/u', '', $chunk) ?? $chunk;
            $chunk = trim($chunk);
            $chunk = rtrim($chunk, " \t.;");

            if ($chunk === '') {
                continue;
            }

            $items[] = $chunk;
        }

        return array_values($items);
    }
}
