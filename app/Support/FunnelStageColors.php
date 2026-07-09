<?php

namespace App\Support;

class FunnelStageColors
{
    /** @var list<string> */
    public const PALETTE = [
        '#99ccff',
        '#ffff99',
        '#ffcc66',
        '#ff9999',
        '#99ff99',
        '#cc99ff',
        '#ff99cc',
        '#c0c0c0',
    ];

    /**
     * @return list<string>
     */
    public static function palette(): array
    {
        return self::PALETTE;
    }

    public static function defaultForIndex(int $index): string
    {
        $palette = self::PALETTE;

        return $palette[$index % count($palette)];
    }

    public static function resolve(?string $color, int $sortOrder): string
    {
        if (is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return strtolower($color);
        }

        return self::defaultForIndex(max(0, $sortOrder - 1));
    }
}
