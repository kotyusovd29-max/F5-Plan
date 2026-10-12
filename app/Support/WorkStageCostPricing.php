<?php

namespace App\Support;

class WorkStageCostPricing
{
    public static function hourlyRate(): int
    {
        return max(1, (int) config('f5ai.work_stage_hourly_rate', 5100));
    }

    /**
     * Суммы из текста запроса/КП, которые нужно сохранить как есть.
     *
     * @return list<float>
     */
    public static function extractExplicitAmounts(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $amounts = [];

        if (preg_match_all(
            '/(?<![\d.])(\d{1,3}(?:[ \x{00A0}]\d{3})+|\d{4,})(?:[.,]\d+)?(?!\d)/u',
            $text,
            $matches
        )) {
            foreach ($matches[1] as $raw) {
                $normalized = str_replace(["\u{00A0}", ' '], '', $raw);
                $normalized = str_replace(',', '.', $normalized);

                if (! is_numeric($normalized)) {
                    continue;
                }

                $value = round((float) $normalized, 2);

                // Отсекаем годы, id и мелкие числа — интересуют суммы сметы.
                if ($value < 1000) {
                    continue;
                }

                $amounts[] = $value;
            }
        }

        return array_values(array_unique($amounts));
    }

    /**
     * @param  array<string, mixed>|null  $changes
     * @return array<string, mixed>|null
     */
    public static function normalizeChanges(?array $changes, string $sourceText): ?array
    {
        if ($changes === null || ! isset($changes['work_stages']) || ! is_array($changes['work_stages'])) {
            return $changes;
        }

        $explicit = self::extractExplicitAmounts($sourceText);
        $section = $changes['work_stages'];

        foreach (['create', 'update'] as $action) {
            if (! isset($section[$action]) || ! is_array($section[$action])) {
                continue;
            }

            foreach ($section[$action] as $index => $row) {
                if (! is_array($row) || ! array_key_exists('cost', $row)) {
                    continue;
                }

                $section[$action][$index]['cost'] = self::normalizeCost($row['cost'], $explicit);
            }
        }

        $changes['work_stages'] = $section;

        return $changes;
    }

    /**
     * @param  list<float>  $explicitAmounts
     */
    public static function normalizeCost(mixed $cost, array $explicitAmounts = []): ?float
    {
        if ($cost === null || $cost === '') {
            return null;
        }

        if (! is_numeric($cost)) {
            return null;
        }

        $value = round((float) $cost, 2);

        if ($value <= 0) {
            return null;
        }

        foreach ($explicitAmounts as $amount) {
            if (abs($value - $amount) < 0.51) {
                return round((float) $amount, 2);
            }
        }

        $rate = self::hourlyRate();

        if (self::isMultipleOf($value, $rate)) {
            return $value;
        }

        $hours = max(1, (int) round($value / $rate));

        return (float) ($hours * $rate);
    }

    public static function promptRules(): string
    {
        $rate = self::hourlyRate();
        $examples = implode(', ', [
            number_format($rate, 0, '', ' ').' (1 ч)',
            number_format($rate * 2, 0, '', ' ').' (2 ч)',
            number_format($rate * 5, 0, '', ' ').' (5 ч)',
            number_format($rate * 10, 0, '', ' ').' (10 ч)',
        ]);

        return <<<RULES
Стоимость этапов работ (work_stages.cost):
- Базовая ставка команды F5: {$rate} ₽ за 1 час.
- Если пользователь (или КП) УЖЕ указал конкретную сумму этапа — ставь именно её, без пересчёта.
- Если суммы нет, а есть оценка в часах (или нужно посчитать по объёму) — cost = число_часов × {$rate}. Примеры: {$examples}.
- Суммы должны быть кратны {$rate}, чтобы по сумме было видно число часов (cost / {$rate}): 5100, 10200, 15300, 51000 и т.д.
- cost — число в рублях без пробелов и «₽», или null если оценить нельзя.
RULES;
    }

    private static function isMultipleOf(float $value, int $divisor): bool
    {
        if ($divisor <= 0) {
            return false;
        }

        $quotient = $value / $divisor;

        return abs($quotient - round($quotient)) < 0.0001;
    }
}
