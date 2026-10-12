<?php

namespace App\Support;

class AmoCrmMapper
{
    /** @var list<string> */
    public const STATUS_COLORS = [
        '#fffeb2',
        '#fffd7f',
        '#fff000',
        '#ffeab2',
        '#ffdc7f',
        '#ffce5a',
        '#ffdbdb',
        '#ffc8c8',
        '#ff8f92',
        '#d6eaff',
        '#c1e0ff',
        '#98cbff',
        '#ebffb1',
        '#deff81',
        '#87f2c0',
        '#f9deff',
        '#f3beff',
        '#ccc8f9',
        '#eb93ff',
        '#f2f3f4',
        '#e6e8ea',
    ];

    public const ENTITY_MAP = [
        'deals' => 'leads',
        'contacts' => 'contacts',
        'companies' => 'companies',
    ];

    public const VALUE_TYPE_MAP = [
        'text' => 'text',
        'number' => 'numeric',
        'date' => 'date',
        'select' => 'select',
        'multiselect' => 'multiselect',
        'toggle' => 'checkbox',
    ];

    public static function normalizeSubdomain(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = preg_replace('#/+$#', '', $value) ?? $value;
        $value = preg_replace('#\.amocrm\.(ru|com)$#', '', $value) ?? $value;
        $value = preg_replace('#\.kommo\.com$#', '', $value) ?? $value;

        return explode('/', $value)[0] ?? $value;
    }

    public static function nearestStatusColor(?string $hex): string
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            return self::STATUS_COLORS[0];
        }

        $hex = strtolower($hex);

        if (in_array($hex, self::STATUS_COLORS, true)) {
            return $hex;
        }

        $target = self::hexToRgb($hex);
        $best = self::STATUS_COLORS[0];
        $bestDistance = PHP_INT_MAX;

        foreach (self::STATUS_COLORS as $candidate) {
            $rgb = self::hexToRgb($candidate);
            $distance = ($target[0] - $rgb[0]) ** 2
                + ($target[1] - $rgb[1]) ** 2
                + ($target[2] - $rgb[2]) ** 2;

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $best;
    }

    public static function isSuccessStageName(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === 'успешно реализовано' || $normalized === 'won') {
            return true;
        }

        return str_contains($normalized, 'успеш')
            && (
                str_contains($normalized, 'реализован')
                || str_contains($normalized, 'заверш')
                || str_contains($normalized, 'выиг')
            );
    }

    public static function isLostStageName(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === 'закрыто и не реализовано' || $normalized === 'lost') {
            return true;
        }

        return str_contains($normalized, 'не реализован')
            || str_contains($normalized, 'проигр')
            || (
                str_contains($normalized, 'отказ')
                && ! str_contains($normalized, 'без отказ')
            )
            || (
                str_contains($normalized, 'закрыт')
                && str_contains($normalized, 'не ')
                && ! str_contains($normalized, 'успеш')
            );
    }

    public static function amoEntityType(string $skkEntityType): ?string
    {
        return self::ENTITY_MAP[$skkEntityType] ?? null;
    }

    public static function skkEntityType(string $amoEntityType): ?string
    {
        $map = array_flip(self::ENTITY_MAP);

        return $map[$amoEntityType] ?? null;
    }

    public static function amoFieldType(string $valueType): string
    {
        return self::VALUE_TYPE_MAP[$valueType] ?? 'text';
    }

    /**
     * @return 'text'|'number'|'date'|'select'|'multiselect'|'toggle'|null
     */
    public static function skkValueType(string $amoFieldType): ?string
    {
        return match ($amoFieldType) {
            'text', 'textarea', 'url', 'streetaddress', 'tracking_data', 'monetary' => 'text',
            'numeric' => 'number',
            'date', 'birthday', 'date_time' => 'date',
            'select', 'radiobutton', 'category' => 'select',
            'multiselect' => 'multiselect',
            'checkbox' => 'toggle',
            default => null,
        };
    }

    /**
     * Текст подсказки этапа для amoCRM (до 1000 символов).
     */
    public static function stageTipText(
        ?string $action,
        ?string $comment,
        ?string $stageType = null,
        ?string $deadline = null
    ): ?string {
        $parts = [];
        $action = trim((string) $action);
        $comment = trim((string) $comment);
        $stageType = trim((string) $stageType);
        $deadline = trim((string) $deadline);

        if ($stageType !== '') {
            $parts[] = 'Тип: '.$stageType;
        }

        if ($deadline !== '') {
            $parts[] = 'Срок: '.$deadline;
        }

        if ($action !== '') {
            $parts[] = 'Автозадача: '.$action;
        }

        if ($comment !== '') {
            $parts[] = $comment;
        }

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode("\n\n", $parts), 0, 1000);
    }

    /**
     * Подсказки этапа для всех уровней пользователей amo (новичок / кандидат / мастер).
     *
     * @return list<array{level: string, description: string}>|null
     */
    public static function stageDescriptions(
        ?string $action,
        ?string $comment,
        ?string $stageType = null,
        ?string $deadline = null
    ): ?array {
        $text = self::stageTipText($action, $comment, $stageType, $deadline);

        if ($text === null) {
            return null;
        }

        return [
            ['level' => 'newbie', 'description' => $text],
            ['level' => 'candidate', 'description' => $text],
            ['level' => 'master', 'description' => $text],
        ];
    }

    public static function stageSort(int $sortOrder): int
    {
        return max(10, $sortOrder * 10);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
