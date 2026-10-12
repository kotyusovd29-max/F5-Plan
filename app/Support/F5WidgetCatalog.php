<?php

namespace App\Support;

use App\Models\F5Widget;
use App\Models\ProjectIntegration;
use Illuminate\Support\Facades\Cache;
use Throwable;

class F5WidgetCatalog
{
    public const CUSTOM_SLUG = 'custom';

    private const CACHE_KEY = 'f5_widget_catalog_active';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('f5_widgets', []);
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return self::config()['categories'] ?? [];
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function widgets(): array
    {
        $fromDb = self::widgetsFromDatabase();

        if ($fromDb !== null) {
            return self::withIcons($fromDb);
        }

        return self::withIcons(self::config()['widgets'] ?? []);
    }

    /**
     * @param  list<array<string, mixed>>  $widgets
     * @return list<array<string, mixed>>
     */
    private static function withIcons(array $widgets): array
    {
        $icons = config('f5_widget_icons', []);

        return array_map(static function (array $widget) use ($icons): array {
            $slug = (string) ($widget['slug'] ?? '');

            if (! filled($widget['icon'] ?? null) && filled($icons[$slug] ?? null)) {
                $widget['icon'] = (string) $icons[$slug];
            }

            return $widget;
        }, $widgets);
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $slugs = array_values(array_filter(
            array_column(self::widgets(), 'slug'),
            fn ($slug): bool => is_string($slug) && $slug !== ''
        ));

        if (! in_array(self::CUSTOM_SLUG, $slugs, true)) {
            $slugs[] = self::CUSTOM_SLUG;
        }

        return $slugs;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(?string $slug): ?array
    {
        if ($slug === null || $slug === '' || $slug === self::CUSTOM_SLUG) {
            return null;
        }

        foreach (self::widgets() as $widget) {
            if (($widget['slug'] ?? '') === $slug) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * Актуальный URL виджета: сначала из каталога по slug, затем сохранённый fallback.
     */
    public static function resolveUrl(?string $slug, ?string $storedUrl = null): ?string
    {
        $widget = self::findBySlug($slug);
        $catalogUrl = filled($widget['url'] ?? null) ? (string) $widget['url'] : null;

        if ($catalogUrl !== null) {
            return $catalogUrl;
        }

        return filled($storedUrl) ? (string) $storedUrl : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(): array
    {
        return [
            'source_url' => self::config()['source_url'] ?? 'https://cmdf5.ru/widjety-amocrm',
            'categories' => self::categories(),
            'widgets' => array_values(array_filter(
                self::widgets(),
                fn (array $widget): bool => ($widget['slug'] ?? '') !== self::CUSTOM_SLUG
            )),
        ];
    }

    /**
     * @return list<array{slug: string, name: string, category: string, description: string}>
     */
    public static function forAiContext(): array
    {
        $categories = self::categories();

        return array_values(array_map(function (array $widget) use ($categories): array {
            $categoryKey = (string) ($widget['category'] ?? '');

            return [
                'slug' => (string) ($widget['slug'] ?? ''),
                'name' => (string) ($widget['name'] ?? ''),
                'category' => $categories[$categoryKey] ?? $categoryKey,
                'description' => (string) ($widget['description'] ?? ''),
            ];
        }, array_filter(
            self::widgets(),
            fn (array $widget): bool => ($widget['slug'] ?? '') !== self::CUSTOM_SLUG
        )));
    }

    public static function integrationPromptRules(): string
    {
        return <<<'TEXT'
Интеграции и виджеты F5:
- Для виджетов из каталога f5_widgets указывай widget_slug (точный slug из каталога) и comment (сценарий в проекте).
- name и widget_url подставятся автоматически из каталога — не придумывай их сам.
- Если нужной интеграции нет в каталоге f5_widgets — укажи widget_slug: null и name (свободное название), comment.
- Не дублируй виджеты, уже указанные в integrations проекта с тем же widget_slug.
- Предпочитай виджеты команды F5 из каталога, а не вымышленные названия.
TEXT;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByName(string $name): ?array
    {
        $needle = mb_strtolower(trim($name));

        if ($needle === '') {
            return null;
        }

        foreach (self::widgets() as $widget) {
            if (($widget['slug'] ?? '') === self::CUSTOM_SLUG) {
                continue;
            }

            $widgetName = mb_strtolower(trim((string) ($widget['name'] ?? '')));

            if ($widgetName === $needle) {
                return $widget;
            }
        }

        foreach (self::widgets() as $widget) {
            if (($widget['slug'] ?? '') === self::CUSTOM_SLUG) {
                continue;
            }

            $widgetName = mb_strtolower(trim((string) ($widget['name'] ?? '')));

            if ($widgetName !== '' && (str_contains($needle, $widgetName) || str_contains($widgetName, $needle))) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * @param  array{widget_slug?: ?string, name?: ?string, comment?: ?string, billing_quantity?: mixed}  $input
     * @return array{widget_slug: ?string, widget_url: ?string, name: string, comment: ?string, billing_quantity: ?int}
     */
    public static function normalizeIntegrationInput(array $input): array
    {
        $slug = isset($input['widget_slug']) && $input['widget_slug'] !== '' && $input['widget_slug'] !== null
            ? (string) $input['widget_slug']
            : null;

        if ($slug !== null && $slug !== self::CUSTOM_SLUG && self::findBySlug($slug) !== null) {
            return self::resolveForStorage([
                'widget_slug' => $slug,
                'comment' => $input['comment'] ?? null,
                'billing_quantity' => $input['billing_quantity'] ?? null,
            ]);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $byName = $name !== '' ? self::findByName($name) : null;

        if ($byName !== null) {
            return self::resolveForStorage([
                'widget_slug' => $byName['slug'],
                'comment' => $input['comment'] ?? null,
                'billing_quantity' => $input['billing_quantity'] ?? null,
            ]);
        }

        return self::resolveForStorage([
            'widget_slug' => self::CUSTOM_SLUG,
            'name' => $name,
            'comment' => $input['comment'] ?? null,
            'billing_quantity' => $input['billing_quantity'] ?? null,
            'payment_type' => $input['payment_type'] ?? null,
            'cost' => $input['cost'] ?? null,
        ]);
    }

    /**
     * @param  array{
     *     widget_slug?: ?string,
     *     name?: ?string,
     *     comment?: ?string,
     *     billing_quantity?: mixed,
     *     payment_type?: mixed,
     *     cost?: mixed
     * }  $input
     * @return array{
     *     widget_slug: ?string,
     *     widget_url: ?string,
     *     name: string,
     *     comment: ?string,
     *     billing_quantity: ?int,
     *     payment_type: ?string,
     *     cost: ?float
     * }
     */
    public static function resolveForStorage(array $input): array
    {
        $slug = isset($input['widget_slug']) && $input['widget_slug'] !== ''
            ? $input['widget_slug']
            : null;
        $comment = $input['comment'] ?? null;
        $billingQuantity = self::normalizeBillingQuantity($input['billing_quantity'] ?? null, $slug);

        if ($slug !== null && $slug !== self::CUSTOM_SLUG) {
            $widget = self::findBySlug($slug);

            if ($widget !== null) {
                return [
                    'widget_slug' => $slug,
                    'widget_url' => $widget['url'] ?? null,
                    'name' => $widget['name'],
                    'comment' => $comment,
                    'billing_quantity' => $billingQuantity,
                    'payment_type' => null,
                    'cost' => null,
                ];
            }
        }

        $paymentType = self::normalizePaymentType($input['payment_type'] ?? null);
        $cost = self::normalizeCost($input['cost'] ?? null);

        return [
            'widget_slug' => null,
            'widget_url' => null,
            'name' => trim((string) ($input['name'] ?? '')),
            'comment' => $comment,
            'billing_quantity' => null,
            'payment_type' => $paymentType,
            'cost' => $cost,
        ];
    }

    private static function normalizePaymentType(mixed $value): string
    {
        $type = trim((string) $value);

        return in_array($type, ProjectIntegration::PAYMENT_TYPES, true)
            ? $type
            : ProjectIntegration::PAYMENT_SUBSCRIPTION;
    }

    private static function normalizeCost(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([' ', "\u{00A0}", '₽', 'руб.', 'руб', 'RUB'], '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, 2);

        return $number < 0 ? null : $number;
    }

    /**
     * @return list<array<string, mixed>>|null null = DB empty / unavailable, use config
     */
    private static function widgetsFromDatabase(): ?array
    {
        try {
            if (! F5Widget::query()->exists()) {
                return null;
            }

            return Cache::remember(self::CACHE_KEY, now()->addHour(), function (): array {
                return F5Widget::query()
                    ->active()
                    ->ordered()
                    ->get()
                    ->map(fn (F5Widget $widget) => $widget->toCatalogArray())
                    ->values()
                    ->all();
            });
        } catch (Throwable) {
            return null;
        }
    }

    private static function normalizeBillingQuantity(mixed $value, ?string $slug): ?int
    {
        if ($slug === null || $slug === self::CUSTOM_SLUG) {
            return null;
        }

        $widget = self::findBySlug($slug);
        $pricing = is_array($widget['pricing'] ?? null) ? $widget['pricing'] : null;
        $model = is_array($pricing) ? (string) ($pricing['model'] ?? '') : '';

        if ($model !== 'per_channel_monthly') {
            return null;
        }

        $min = max(1, (int) ($pricing['min_quantity'] ?? 1));
        $default = max($min, (int) ($pricing['default_quantity'] ?? $min));

        if ($value === null || $value === '') {
            return $default;
        }

        $quantity = (int) $value;

        return max($min, $quantity);
    }
}
