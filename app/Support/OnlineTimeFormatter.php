<?php

namespace App\Support;

class OnlineTimeFormatter
{
    public static function format(?int $seconds): string
    {
        $seconds = max(0, (int) $seconds);

        if ($seconds < 60) {
            return $seconds.' сек';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $minutes > 0
                ? $hours.' ч '.$minutes.' мин'
                : $hours.' ч';
        }

        return $minutes.' мин';
    }
}
