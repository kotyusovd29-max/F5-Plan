<?php

namespace App\Support;

class FunnelStageWidgets
{
    /**
     * @param  mixed  $widgets
     * @return list<array{widget_slug: string, comment: string|null}>
     */
    public static function normalize(mixed $widgets): array
    {
        if (! is_array($widgets)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($widgets as $row) {
            if (! is_array($row)) {
                continue;
            }

            $slug = trim((string) ($row['widget_slug'] ?? ''));

            if ($slug === '' || $slug === F5WidgetCatalog::CUSTOM_SLUG) {
                continue;
            }

            if (! in_array($slug, F5WidgetCatalog::slugs(), true)) {
                continue;
            }

            if (isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $comment = trim((string) ($row['comment'] ?? ''));

            $normalized[] = [
                'widget_slug' => $slug,
                'comment' => $comment !== '' ? mb_substr($comment, 0, 2000) : null,
            ];

            if (count($normalized) >= 20) {
                break;
            }
        }

        return $normalized;
    }

    public static function normalizeActionWidgetSlug(mixed $slug, array $widgets = []): ?string
    {
        $slug = trim((string) ($slug ?? ''));

        if ($slug === '' || $slug === F5WidgetCatalog::CUSTOM_SLUG) {
            return null;
        }

        if (! in_array($slug, F5WidgetCatalog::slugs(), true)) {
            return null;
        }

        if ($widgets !== []) {
            $attached = collect($widgets)->pluck('widget_slug')->all();

            if (! in_array($slug, $attached, true)) {
                return null;
            }
        }

        return $slug;
    }

    /**
     * @param  list<array<string, mixed>>|null  $widgets
     * @return list<array{widget_slug: string, name: string, comment: string|null, url: string|null}>
     */
    public static function present(?array $widgets): array
    {
        return array_values(array_filter(array_map(function (array $row) {
            $slug = (string) ($row['widget_slug'] ?? '');
            $widget = F5WidgetCatalog::findBySlug($slug);

            if ($widget === null) {
                return null;
            }

            return [
                'widget_slug' => $slug,
                'name' => (string) ($widget['name'] ?? $slug),
                'comment' => $row['comment'] ?? null,
                'url' => $widget['url'] ?? F5WidgetCatalog::resolveUrl($slug),
            ];
        }, self::normalize($widgets))));
    }
}
