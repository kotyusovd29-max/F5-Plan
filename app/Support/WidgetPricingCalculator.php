<?php

namespace App\Support;

use App\Models\ProjectIntegration;

class WidgetPricingCalculator
{
    public const MIN_BILLING_USERS = 5;

    /** Лицензии amoCRM считаются от 1 пользователя. */
    public const MIN_AMOCRM_LICENSE_USERS = 1;

    public const ANNUAL_PERIOD_KEY = 12;

    public const ANNUAL_PAID_MONTHS = 10;

    public const ANNUAL_FREE_MONTHS = 3;

    public const SEMI_ANNUAL_PERIOD_KEY = 6;

    public const SEMI_ANNUAL_PAID_MONTHS = 6;

    public const SEMI_ANNUAL_FREE_MONTHS = 1;

    /** Безлимитные подписки: короткий период — 7 месяцев. */
    public const UNLIMITED_SHORT_MONTHS = 7;

    /** Безлимитные подписки: длинный период 12+1 — оплата за 12 мес. */
    public const UNLIMITED_LONG_PAID_MONTHS = 12;

    public const UNLIMITED_LONG_FREE_MONTHS = 1;

    /**
     * Dual estimate periods for XLSX: 6+1 and 10+3 (paid months only, no monthly totals).
     *
     * @param  list<array{name: string, widget_slug?: string|null, billing_quantity?: int|null, comment?: string|null}>  $integrations
     * @param  list<array<string, mixed>>  $catalogWidgets
     * @return array{
     *     billingUsers: int,
     *     lines: list<array{
     *         name: string,
     *         comment: ?string,
     *         pricePerUser: ?float,
     *         priceLabel: string,
     *         quantity: int|string,
     *         quantityLabel: string,
     *         costSixPlusOne: ?float,
     *         costTenPlusThree: ?float,
     *         model: string
     *     }>,
     *     skipped: list<array{name: string, comment: ?string, reason: string, priceLabel: string}>,
     *     totalSixPlusOne: float,
     *     totalTenPlusThree: float
     * }
     */
    public static function calculateEstimatePeriods(
        array $integrations,
        array $catalogWidgets,
        int|string|null $usersCount
    ): array {
        $six = self::calculate($integrations, $catalogWidgets, $usersCount, self::SEMI_ANNUAL_PERIOD_KEY);
        $annual = self::calculate($integrations, $catalogWidgets, $usersCount, self::ANNUAL_PERIOD_KEY);

        $annualByName = [];
        foreach ($annual['lines'] as $line) {
            $annualByName[$line['name']] = $line;
        }

        $lines = [];
        foreach ($six['lines'] as $line) {
            $match = $annualByName[$line['name']] ?? null;
            $pricePerUser = null;
            $quantity = $six['billingUsers'];
            $quantityLabel = 'польз.';

            if ($line['model'] === 'per_channel_monthly') {
                $quantityLabel = 'каналов';
                if (preg_match('/×\s*(\d+)/u', $line['calcLabel'], $m) === 1) {
                    $quantity = (int) $m[1];
                }
                if (preg_match('/([\d\s.,]+)\s*₽/u', $line['priceLabel'], $m) === 1) {
                    $pricePerUser = self::parseRussianAmount($m[1]);
                }
            } elseif ($line['model'] === 'custom_subscription') {
                $quantity = '—';
                $quantityLabel = '';
                if (preg_match('/([\d\s.,]+)\s*₽/u', $line['priceLabel'], $m) === 1) {
                    $pricePerUser = self::parseRussianAmount($m[1]);
                }
            } elseif (in_array($line['model'], ['fixed_period', 'custom_one_time'], true)) {
                $quantity = '—';
                $quantityLabel = '';
                $pricePerUser = null;
            } else {
                $pricePerUser = self::parseWidgetMonthlyPrice($line['priceLabel']);
            }

            $lines[] = [
                'name' => $line['name'],
                'comment' => $line['comment'],
                'pricePerUser' => $pricePerUser,
                'priceLabel' => $line['priceLabel'],
                'quantity' => $quantity,
                'quantityLabel' => $quantityLabel,
                'costSixPlusOne' => $line['linePeriod'],
                'costTenPlusThree' => $match['linePeriod'] ?? null,
                'model' => $line['model'],
            ];
        }

        return [
            'billingUsers' => $six['billingUsers'],
            'lines' => $lines,
            'skipped' => $six['skipped'],
            'totalSixPlusOne' => $six['periodTotal'],
            'totalTenPlusThree' => $annual['periodTotal'],
        ];
    }

    /**
     * Dual estimate for amoCRM license tariffs: 6+1 and 10+3.
     *
     * @return array{
     *     billingUsers: int,
     *     sourceUrl: string,
     *     sourceNote: string,
     *     selectedSlug: ?string,
     *     tariffs: list<array{
     *         slug: string,
     *         name: string,
     *         price_per_user: float,
     *         costSixPlusOne: float,
     *         costTenPlusThree: float
     *     }>
     * }
     */
    public static function amocrmLicenseEstimatePeriods(
        int|string|null $usersCount,
        ?string $selectedSlug = null
    ): array {
        $six = self::calculateAmocrmLicenses($usersCount, self::SEMI_ANNUAL_PERIOD_KEY);
        $annual = self::calculateAmocrmLicenses($usersCount, self::ANNUAL_PERIOD_KEY);

        $annualBySlug = [];
        foreach ($annual['tariffs'] as $tariff) {
            $annualBySlug[$tariff['slug']] = $tariff;
        }

        $selected = self::normalizeAmocrmLicenseTariffSlug($selectedSlug);

        $tariffs = [];
        foreach ($six['tariffs'] as $tariff) {
            if ($selected !== null && $tariff['slug'] !== $selected) {
                continue;
            }

            $match = $annualBySlug[$tariff['slug']] ?? null;
            $tariffs[] = [
                'slug' => $tariff['slug'],
                'name' => $tariff['name'],
                'price_per_user' => $tariff['price_per_user'],
                'costSixPlusOne' => $tariff['linePeriod'],
                'costTenPlusThree' => $match['linePeriod'] ?? 0.0,
            ];
        }

        return [
            'billingUsers' => $six['billingUsers'],
            'sourceUrl' => $six['sourceUrl'],
            'sourceNote' => $six['sourceNote'],
            'selectedSlug' => $selected,
            'tariffs' => $tariffs,
        ];
    }

    /**
     * @return list<string>
     */
    public static function amocrmLicenseTariffSlugs(): array
    {
        $slugs = [];

        foreach (config('f5_estimate.amocrm_licenses.tariffs', []) as $tariff) {
            $slug = trim((string) ($tariff['slug'] ?? ''));

            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    public static function normalizeAmocrmLicenseTariffSlug(?string $slug): ?string
    {
        $normalized = trim((string) $slug);

        if ($normalized === '') {
            return null;
        }

        return in_array($normalized, self::amocrmLicenseTariffSlugs(), true) ? $normalized : null;
    }

    /**
     * @return array{
     *     billingUsers: int,
     *     months: int,
     *     paidMonths: int,
     *     freeMonths: int,
     *     isAnnualBonus: bool,
     *     periodLabel: string,
     *     periodShortLabel: string,
     *     sourceUrl: string,
     *     sourceNote: string,
     *     tariffs: list<array{
     *         slug: string,
     *         name: string,
     *         price_per_user: float,
     *         lineMonthly: float,
     *         linePeriod: float,
     *         calcLabel: string
     *     }>
     * }
     */
    public static function calculateAmocrmLicenses(int|string|null $usersCount, int $months = self::ANNUAL_PERIOD_KEY): array
    {
        $config = config('f5_estimate.amocrm_licenses', []);
        $billingUsers = self::resolveAmocrmLicenseUsers($usersCount);
        $periodMonths = max(1, $months);
        $paidMonths = self::resolvePaidMonths($periodMonths);
        $isAnnualBonus = $periodMonths === self::ANNUAL_PERIOD_KEY;
        $freeMonths = $isAnnualBonus ? self::ANNUAL_FREE_MONTHS : (
            $periodMonths === self::SEMI_ANNUAL_PERIOD_KEY ? self::SEMI_ANNUAL_FREE_MONTHS : 0
        );
        $periodLabel = $isAnnualBonus
            ? 'за '.self::ANNUAL_PAID_MONTHS.'+'.self::ANNUAL_FREE_MONTHS.' мес.'
            : ($periodMonths === 1 ? 'в месяц' : "за {$periodMonths} мес.");
        $periodShortLabel = $isAnnualBonus
            ? self::ANNUAL_PAID_MONTHS.'+'.self::ANNUAL_FREE_MONTHS
            : (string) $periodMonths;

        $tariffs = [];

        foreach ($config['tariffs'] ?? [] as $tariff) {
            $price = (float) ($tariff['price_per_user'] ?? 0);
            $slug = (string) ($tariff['slug'] ?? '');
            $name = (string) ($tariff['name'] ?? '');

            if ($slug === '' || $name === '' || $price <= 0) {
                continue;
            }

            $lineMonthly = $price * $billingUsers;
            $linePeriod = $lineMonthly * $paidMonths;
            $userCalc = self::formatRub($price).' × '.$billingUsers;
            $calcLabel = $paidMonths > 1
                ? $userCalc.' × '.$paidMonths.' мес.'.($freeMonths > 0 ? ' (+'.$freeMonths.' в подарок)' : '')
                : $userCalc;

            $tariffs[] = [
                'slug' => $slug,
                'name' => $name,
                'price_per_user' => $price,
                'lineMonthly' => $lineMonthly,
                'linePeriod' => $linePeriod,
                'calcLabel' => $calcLabel,
            ];
        }

        return [
            'billingUsers' => $billingUsers,
            'months' => $periodMonths,
            'paidMonths' => $paidMonths,
            'freeMonths' => $freeMonths,
            'isAnnualBonus' => $isAnnualBonus,
            'periodLabel' => $periodLabel,
            'periodShortLabel' => $periodShortLabel,
            'sourceUrl' => (string) ($config['source_url'] ?? 'https://www.amocrm.ru/buy/'),
            'sourceNote' => (string) ($config['source_note'] ?? ''),
            'tariffs' => $tariffs,
        ];
    }

    /**
     * @return list<array{
     *     label: string,
     *     title: string,
     *     condition: string,
     *     shortMonths: int,
     *     longMonths: int,
     *     longPeriodLabel: string,
     *     tariffs: list<array{
     *         name: string,
     *         price_per_user: float,
     *         users: int,
     *         costSevenMonths: float,
     *         costLongPeriod: float
     *     }>
     * }>
     */
    public static function unlimitedSubscriptionRows(int|string|null $usersCount): array
    {
        $users = self::resolveBillingUsers($usersCount);
        $variants = config('f5_estimate.unlimited_subscriptions', []);
        $result = [];

        foreach ($variants as $variant) {
            $longMonths = max(1, (int) ($variant['long_months'] ?? self::UNLIMITED_LONG_PAID_MONTHS));
            $longPeriodLabel = (string) ($variant['long_period_label'] ?? 'на 12+1 месяцев (вкл. 1 мес. в подарок)');
            $tariffs = [];

            foreach ($variant['tariffs'] ?? [] as $tariff) {
                $price = (float) ($tariff['price_per_user'] ?? 0);
                $tariffs[] = [
                    'name' => (string) ($tariff['name'] ?? ''),
                    'price_per_user' => $price,
                    'users' => $users,
                    'costSevenMonths' => $price * $users * self::UNLIMITED_SHORT_MONTHS,
                    'costLongPeriod' => $price * $users * $longMonths,
                    // backward-compatible alias for older callers/tests
                    'costTwelvePlusOne' => $price * $users * $longMonths,
                ];
            }

            $result[] = [
                'label' => (string) ($variant['label'] ?? ''),
                'title' => (string) ($variant['title'] ?? ''),
                'condition' => (string) ($variant['condition'] ?? ''),
                'shortMonths' => self::UNLIMITED_SHORT_MONTHS,
                'longMonths' => $longMonths,
                'longPeriodLabel' => $longPeriodLabel,
                'tariffs' => $tariffs,
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{name: string, widget_slug?: string|null, billing_quantity?: int|null, comment?: string|null}>  $integrations
     * @param  list<array<string, mixed>>  $catalogWidgets
     * @return array{
     *     billingUsers: int,
     *     months: int,
     *     paidMonths: int,
     *     freeMonths: int,
     *     isAnnualBonus: bool,
     *     periodLabel: string,
     *     monthlyTotal: float,
     *     periodTotal: float,
     *     lines: list<array{
     *         name: string,
     *         comment: ?string,
     *         priceLabel: string,
     *         calcLabel: string,
     *         lineMonthly: float,
     *         linePeriod: float,
     *         model: string
     *     }>,
     *     skipped: list<array{name: string, comment: ?string, reason: string, priceLabel: string}>
     * }
     */
    public static function calculate(
        array $integrations,
        array $catalogWidgets,
        int|string|null $usersCount,
        int $months = self::ANNUAL_PERIOD_KEY
    ): array {
        $widgetsBySlug = [];
        foreach ($catalogWidgets as $widget) {
            $slug = (string) ($widget['slug'] ?? '');
            if ($slug !== '') {
                $widgetsBySlug[$slug] = $widget;
            }
        }

        $usersInput = max(0, (int) $usersCount);
        $billingUsers = self::resolveBillingUsers($usersCount);
        $periodMonths = max(1, $months);
        $paidMonths = self::resolvePaidMonths($periodMonths);
        $isAnnualBonus = $periodMonths === self::ANNUAL_PERIOD_KEY;
        $freeMonths = $isAnnualBonus ? self::ANNUAL_FREE_MONTHS : 0;
        $periodLabel = $isAnnualBonus
            ? 'за '.self::ANNUAL_PAID_MONTHS.'+'.self::ANNUAL_FREE_MONTHS.' мес.'
            : ($periodMonths === 1 ? 'в месяц' : "за {$periodMonths} мес.");

        $lines = [];
        $skipped = [];
        $monthlyTotal = 0.0;
        $periodTotal = 0.0;

        foreach ($integrations as $integration) {
            $name = (string) ($integration['name'] ?? '');
            $comment = filled($integration['comment'] ?? null) ? (string) $integration['comment'] : null;
            $slug = $integration['widget_slug'] ?? null;

            if ($slug === null || $slug === '' || $slug === F5WidgetCatalog::CUSTOM_SLUG) {
                $customLine = self::resolveCustomIntegrationLine(
                    $integration,
                    $name,
                    $comment,
                    $paidMonths,
                    $isAnnualBonus,
                    $freeMonths
                );

                if ($customLine === null) {
                    $skipped[] = [
                        'name' => $name,
                        'comment' => $comment,
                        'reason' => 'Своя интеграция / без тарифа',
                        'priceLabel' => '',
                    ];

                    continue;
                }

                $monthlyTotal += $customLine['lineMonthly'];
                $periodTotal += $customLine['linePeriod'];
                $lines[] = $customLine;

                continue;
            }

            $widget = $widgetsBySlug[$slug] ?? null;

            if ($widget === null) {
                $skipped[] = [
                    'name' => $name,
                    'comment' => $comment,
                    'reason' => 'Нет в каталоге',
                    'priceLabel' => '',
                ];

                continue;
            }

            $pricing = is_array($widget['pricing'] ?? null) ? $widget['pricing'] : null;
            $model = is_array($pricing) ? (string) ($pricing['model'] ?? 'per_user_monthly') : 'per_user_monthly';
            $priceLabel = (string) ($widget['price'] ?? '');

            if ($model === 'per_channel_monthly') {
                $unitMonthly = (float) ($pricing['unit_monthly'] ?? 0);
                $quantity = self::resolveChannelQuantity($pricing, $integration['billing_quantity'] ?? null);
                $quantityLabel = (string) ($pricing['quantity_label'] ?? 'каналов');

                if ($unitMonthly <= 0) {
                    $skipped[] = [
                        'name' => $name,
                        'comment' => $comment,
                        'reason' => $priceLabel !== '' ? $priceLabel : 'Цена не фиксированная',
                        'priceLabel' => $priceLabel,
                    ];

                    continue;
                }

                $lineMonthly = $unitMonthly * $quantity;
                $linePeriod = $lineMonthly * $paidMonths;
                $monthlyTotal += $lineMonthly;
                $periodTotal += $linePeriod;

                $channelCalc = self::formatRub($unitMonthly).' × '.$quantity.' '.$quantityLabel;
                $calcLabel = $paidMonths > 1
                    ? $channelCalc.' × '.$paidMonths.' мес.'.($isAnnualBonus ? ' (+'.$freeMonths.' бесплатно)' : '')
                    : $channelCalc;

                $lines[] = [
                    'name' => $name,
                    'comment' => $comment,
                    'priceLabel' => $priceLabel !== '' ? $priceLabel : self::formatRub($unitMonthly).' / канал · мес',
                    'calcLabel' => $calcLabel,
                    'lineMonthly' => $lineMonthly,
                    'linePeriod' => $linePeriod,
                    'model' => $model,
                ];

                continue;
            }

            if ($model === 'fixed_period') {
                $fixed = self::resolveFixedPeriodCost($pricing['packages'] ?? null, $periodMonths);

                if ($fixed === null) {
                    $skipped[] = [
                        'name' => $name,
                        'comment' => $comment,
                        'reason' => 'Нет пакета на выбранный период',
                        'priceLabel' => $priceLabel,
                    ];

                    continue;
                }

                $monthlyTotal += $fixed['monthlyTotal'];
                $periodTotal += $fixed['periodTotal'];

                $packageHint = $isAnnualBonus
                    ? 'пакет на '.self::ANNUAL_PAID_MONTHS.'+'.self::ANNUAL_FREE_MONTHS
                    : (in_array($periodMonths, [6, 12], true)
                        ? "пакет на {$periodMonths} мес."
                        : 'из годового пакета');

                $lines[] = [
                    'name' => $name,
                    'comment' => $comment,
                    'priceLabel' => $priceLabel,
                    'calcLabel' => $packageHint,
                    'lineMonthly' => $fixed['monthlyTotal'],
                    'linePeriod' => $fixed['periodTotal'],
                    'model' => $model,
                ];

                continue;
            }

            $perUserMonthly = self::parseWidgetMonthlyPrice($priceLabel);

            if ($perUserMonthly === null) {
                $skipped[] = [
                    'name' => $name,
                    'comment' => $comment,
                    'reason' => $priceLabel !== '' ? $priceLabel : 'Цена не фиксированная',
                    'priceLabel' => $priceLabel,
                ];

                continue;
            }

            $lineMonthly = $perUserMonthly * $billingUsers;
            $linePeriod = $lineMonthly * $paidMonths;
            $monthlyTotal += $lineMonthly;
            $periodTotal += $linePeriod;

            $userCalc = self::formatRub($perUserMonthly).' × '.$billingUsers;
            $calcLabel = $paidMonths > 1
                ? $userCalc.' × '.$paidMonths.' мес.'.($isAnnualBonus ? ' (+'.$freeMonths.' бесплатно)' : '')
                : $userCalc;

            $lines[] = [
                'name' => $name,
                'comment' => $comment,
                'priceLabel' => $priceLabel,
                'calcLabel' => $calcLabel,
                'lineMonthly' => $lineMonthly,
                'linePeriod' => $linePeriod,
                'model' => 'per_user_monthly',
            ];
        }

        return [
            'billingUsers' => $billingUsers,
            'months' => $periodMonths,
            'paidMonths' => $paidMonths,
            'freeMonths' => $freeMonths,
            'isAnnualBonus' => $isAnnualBonus,
            'periodLabel' => $periodLabel,
            'monthlyTotal' => $monthlyTotal,
            'periodTotal' => $periodTotal,
            'lines' => $lines,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, mixed>  $integration
     * @return array{
     *     name: string,
     *     comment: ?string,
     *     priceLabel: string,
     *     calcLabel: string,
     *     lineMonthly: float,
     *     linePeriod: float,
     *     model: string
     * }|null
     */
    public static function resolveCustomIntegrationLine(
        array $integration,
        string $name,
        ?string $comment,
        int $paidMonths,
        bool $isAnnualBonus,
        int $freeMonths
    ): ?array {
        $rawCost = $integration['cost'] ?? null;

        if ($rawCost === null || $rawCost === '') {
            return null;
        }

        $cost = round((float) $rawCost, 2);

        if ($cost < 0) {
            return null;
        }

        $paymentType = (string) ($integration['payment_type'] ?? ProjectIntegration::PAYMENT_SUBSCRIPTION);

        if ($paymentType === ProjectIntegration::PAYMENT_ONE_TIME) {
            return [
                'name' => $name,
                'comment' => $comment,
                'priceLabel' => 'Единовременно',
                'calcLabel' => 'разовая сумма',
                'lineMonthly' => 0.0,
                'linePeriod' => $cost,
                'model' => 'custom_one_time',
            ];
        }

        $lineMonthly = $cost;
        $linePeriod = $lineMonthly * $paidMonths;
        $calcLabel = $paidMonths > 1
            ? self::formatRub($lineMonthly).' × '.$paidMonths.' мес.'.($isAnnualBonus ? ' (+'.$freeMonths.' бесплатно)' : '')
            : self::formatRub($lineMonthly);

        return [
            'name' => $name,
            'comment' => $comment,
            'priceLabel' => self::formatRub($lineMonthly).' / мес',
            'calcLabel' => $calcLabel,
            'lineMonthly' => $lineMonthly,
            'linePeriod' => $linePeriod,
            'model' => 'custom_subscription',
        ];
    }

    public static function resolveBillingUsers(int|string|null $usersCount): int
    {
        $parsed = max(0, (int) $usersCount);

        return max(self::MIN_BILLING_USERS, $parsed > 0 ? $parsed : self::MIN_BILLING_USERS);
    }

    public static function resolveAmocrmLicenseUsers(int|string|null $usersCount): int
    {
        $parsed = max(0, (int) $usersCount);

        return max(self::MIN_AMOCRM_LICENSE_USERS, $parsed > 0 ? $parsed : self::MIN_AMOCRM_LICENSE_USERS);
    }

    public static function resolvePaidMonths(int $periodMonths): int
    {
        $months = max(1, $periodMonths);

        return $months === self::ANNUAL_PERIOD_KEY ? self::ANNUAL_PAID_MONTHS : $months;
    }

    /**
     * @param  array<string, mixed>|null  $pricing
     */
    public static function resolveChannelQuantity(?array $pricing, mixed $quantity): int
    {
        $min = max(1, (int) ($pricing['min_quantity'] ?? 1));
        $fallback = max($min, (int) ($pricing['default_quantity'] ?? $min));
        $parsed = (int) $quantity;

        if ($parsed < 1) {
            return $fallback;
        }

        return max($min, $parsed);
    }

    /**
     * @param  array<string|int, mixed>|null  $packages
     * @return array{periodTotal: float, monthlyTotal: float}|null
     */
    public static function resolveFixedPeriodCost(?array $packages, int $months): ?array
    {
        if ($packages === null) {
            return null;
        }

        $periodMonths = max(1, $months);
        $six = isset($packages[6]) ? (float) $packages[6] : null;
        $twelve = isset($packages[12]) ? (float) $packages[12] : null;

        if ($periodMonths === 6 && $six !== null && $six > 0) {
            return ['periodTotal' => $six, 'monthlyTotal' => $six / 6];
        }

        if ($periodMonths === 12 && $twelve !== null && $twelve > 0) {
            return ['periodTotal' => $twelve, 'monthlyTotal' => $twelve / 12];
        }

        if ($periodMonths === 1 && $twelve !== null && $twelve > 0) {
            return ['periodTotal' => $twelve / 12, 'monthlyTotal' => $twelve / 12];
        }

        if ($twelve !== null && $twelve > 0) {
            $monthly = $twelve / 12;

            return ['periodTotal' => $monthly * $periodMonths, 'monthlyTotal' => $monthly];
        }

        if ($six !== null && $six > 0) {
            $monthly = $six / 6;

            return ['periodTotal' => $monthly * $periodMonths, 'monthlyTotal' => $monthly];
        }

        return null;
    }

    public static function parseWidgetMonthlyPrice(?string $priceStr): ?float
    {
        if ($priceStr === null || trim($priceStr) === '' || trim($priceStr) === '—') {
            return null;
        }

        $normalized = mb_strtolower(trim($priceStr));

        foreach (['бонусный', 'токен', '/ час', '/ мин', 'расчет по', 'канал', '/ 6 мес', '/ 12 мес'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return null;
            }
        }

        if (preg_match('/([\d\s.,]+)\s*₽?\s*в\s*год/u', $normalized, $yearlyMatch) === 1) {
            $yearly = self::parseRussianAmount($yearlyMatch[1]);

            return $yearly !== null ? $yearly / 12 : null;
        }

        if (preg_match('/([\d\s.,]+)\s*₽?/u', $normalized, $monthlyMatch) === 1) {
            return self::parseRussianAmount($monthlyMatch[1]);
        }

        return null;
    }

    public static function parseRussianAmount(string $value): ?float
    {
        $cleaned = str_replace([' ', "\u{00A0}"], '', $value);
        $cleaned = str_replace(',', '.', $cleaned);
        $amount = (float) $cleaned;

        return is_finite($amount) ? $amount : null;
    }

    public static function formatRub(float $amount): string
    {
        $decimals = abs($amount - round($amount)) < 0.001 ? 0 : 2;

        return number_format($amount, $decimals, ',', ' ').' ₽';
    }
}
