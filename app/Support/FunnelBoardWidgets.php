<?php

namespace App\Support;

use Illuminate\Support\Str;

class FunnelBoardWidgets
{
    public const NEXT_TYPES = ['stage', 'widget'];

    /**
     * @param  mixed  $widgets
     * @param  list<int>|null  $validStageIds
     * @return list<array{
     *     id: string,
     *     widget_slug: string,
     *     comment: string|null,
     *     canvas_pos_x: int,
     *     canvas_pos_y: int,
     *     next_type: string|null,
     *     next_id: int|string|null,
     *     path_points: list<array{x: int, y: int}>
     * }>
     */
    public static function normalize(mixed $widgets, ?array $validStageIds = null): array
    {
        if (! is_array($widgets)) {
            return [];
        }

        $normalized = [];
        $seenIds = [];

        foreach ($widgets as $row) {
            if (! is_array($row)) {
                continue;
            }

            $slug = FunnelStageWidgets::normalizeActionWidgetSlug($row['widget_slug'] ?? null);

            if ($slug === null) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));

            if ($id === '' || ! Str::isUuid($id)) {
                $id = (string) Str::uuid();
            }

            if (isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;

            $comment = trim((string) ($row['comment'] ?? ''));
            [$nextType, $nextId] = self::normalizeNext(
                $row['next_type'] ?? null,
                $row['next_id'] ?? null,
                $validStageIds
            );

            $normalized[] = [
                'id' => $id,
                'widget_slug' => $slug,
                'comment' => $comment !== '' ? mb_substr($comment, 0, 2000) : null,
                'canvas_pos_x' => max(0, (int) ($row['canvas_pos_x'] ?? 80)),
                'canvas_pos_y' => max(0, (int) ($row['canvas_pos_y'] ?? 80)),
                'next_type' => $nextType,
                'next_id' => $nextId,
                'path_points' => FunnelStageOutcomes::normalizePathPoints($row['path_points'] ?? null),
            ];

            if (count($normalized) >= 50) {
                break;
            }
        }

        // Drop widget→widget next pointing to missing widgets.
        $ids = collect($normalized)->pluck('id')->all();

        return array_map(function (array $row) use ($ids) {
            if ($row['next_type'] === 'widget' && ! in_array((string) $row['next_id'], $ids, true)) {
                $row['next_type'] = null;
                $row['next_id'] = null;
            }

            return $row;
        }, $normalized);
    }

    /**
     * @param  list<array<string, mixed>>  $widgets
     * @return list<array<string, mixed>>
     */
    public static function present(array $widgets): array
    {
        return array_values(array_filter(array_map(function (array $row) {
            $widget = F5WidgetCatalog::findBySlug($row['widget_slug'] ?? null);

            if ($widget === null) {
                return null;
            }

            return [
                ...$row,
                'name' => (string) ($widget['name'] ?? $row['widget_slug']),
                'url' => $widget['url'] ?? F5WidgetCatalog::resolveUrl($row['widget_slug']),
            ];
        }, self::normalize($widgets))));
    }

    /**
     * @param  list<array<string, mixed>>|null  $widgets
     * @param  array<string, string>  $idMap  old uuid => new uuid
     * @param  array<int, int>  $stageIdMap  old stage id => new stage id
     * @return list<array<string, mixed>>
     */
    public static function remap(?array $widgets, array $idMap, array $stageIdMap = []): array
    {
        $normalized = self::normalize($widgets);

        return array_map(function (array $row) use ($idMap, $stageIdMap) {
            $oldId = $row['id'];
            $row['id'] = $idMap[$oldId] ?? (string) Str::uuid();

            if ($row['next_type'] === 'stage' && $row['next_id'] !== null) {
                $row['next_id'] = $stageIdMap[(int) $row['next_id']] ?? null;
                if ($row['next_id'] === null) {
                    $row['next_type'] = null;
                }
            } elseif ($row['next_type'] === 'widget' && $row['next_id'] !== null) {
                $row['next_id'] = $idMap[(string) $row['next_id']] ?? null;
                if ($row['next_id'] === null) {
                    $row['next_type'] = null;
                }
            }

            return $row;
        }, $normalized);
    }

    /**
     * @param  list<int>|null  $validStageIds
     * @return array{0: string|null, 1: int|string|null}
     */
    private static function normalizeNext(mixed $type, mixed $id, ?array $validStageIds): array
    {
        $type = is_string($type) ? trim($type) : null;

        if ($type === '' || $type === null) {
            return [null, null];
        }

        if (! in_array($type, self::NEXT_TYPES, true)) {
            return [null, null];
        }

        if ($type === 'stage') {
            if (! is_numeric($id)) {
                return [null, null];
            }

            $stageId = (int) $id;

            if ($validStageIds !== null && ! in_array($stageId, $validStageIds, true)) {
                return [null, null];
            }

            return ['stage', $stageId];
        }

        $widgetId = trim((string) $id);

        if ($widgetId === '' || ! Str::isUuid($widgetId)) {
            return [null, null];
        }

        return ['widget', $widgetId];
    }
}
