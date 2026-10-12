<?php

namespace App\Support;

use Illuminate\Support\Str;

class FunnelStageOutcomes
{
    public const MODES = ['move', 'stay', 'lose', 'win'];

    public const KINDS = ['normal', 'win', 'lose'];

    public const TARGET_TYPES = ['stage', 'widget'];

    /**
     * @param  mixed  $outcomes
     * @param  list<array{widget_slug?: string}>|null  $stageWidgets  unused; kept for call-site compatibility
     * @param  list<string>|null  $validBoardWidgetIds
     * @return list<array{
     *     result: string,
     *     mode: string,
     *     target_type: string|null,
     *     target_stage_id: int|null,
     *     target_sort_order: int|null,
     *     target_widget_id: string|null,
     *     label: string|null,
     *     widget_slug: string|null,
     *     widget_comment: string|null,
     *     path_points: list<array{x: int, y: int}>
     * }>
     */
    public static function normalize(
        mixed $outcomes,
        ?array $stageWidgets = null,
        ?array $validBoardWidgetIds = null
    ): array {
        unset($stageWidgets);

        if (! is_array($outcomes)) {
            return [];
        }

        $normalized = [];

        foreach ($outcomes as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result = trim((string) ($row['result'] ?? ''));
            $mode = (string) ($row['mode'] ?? 'move');

            if ($result === '' || ! in_array($mode, self::MODES, true)) {
                continue;
            }

            $targetStageId = $row['target_stage_id'] ?? null;
            $targetSort = $row['target_sort_order'] ?? null;
            $label = trim((string) ($row['label'] ?? ''));
            $targetWidgetId = trim((string) ($row['target_widget_id'] ?? ''));
            $targetType = is_string($row['target_type'] ?? null) ? trim((string) $row['target_type']) : null;

            if ($mode === 'stay') {
                $targetType = null;
                $targetStageId = null;
                $targetSort = null;
                $targetWidgetId = '';
            } elseif ($targetType === 'widget' || $targetWidgetId !== '') {
                $targetType = 'widget';
                $targetStageId = null;
                $targetSort = null;

                if ($targetWidgetId === '' || ! Str::isUuid($targetWidgetId)) {
                    $targetType = null;
                    $targetWidgetId = '';
                } elseif ($validBoardWidgetIds !== null && ! in_array($targetWidgetId, $validBoardWidgetIds, true)) {
                    $targetType = null;
                    $targetWidgetId = '';
                }
            } elseif ($targetType === 'stage' || is_numeric($targetStageId) || is_numeric($targetSort)) {
                $targetType = 'stage';
                $targetWidgetId = '';
                $targetStageId = is_numeric($targetStageId) ? (int) $targetStageId : null;
                $targetSort = is_numeric($targetSort) ? (int) $targetSort : null;
            } else {
                $targetType = null;
                $targetStageId = null;
                $targetSort = null;
                $targetWidgetId = '';
            }

            $widgetSlug = $targetType === 'widget'
                ? null
                : FunnelStageWidgets::normalizeActionWidgetSlug($row['widget_slug'] ?? null);
            $widgetComment = trim((string) ($row['widget_comment'] ?? ''));

            $normalized[] = [
                'result' => mb_substr($result, 0, 1000),
                'mode' => $mode,
                'target_type' => $targetType,
                'target_stage_id' => $targetStageId,
                'target_sort_order' => $targetSort,
                'target_widget_id' => $targetWidgetId !== '' ? $targetWidgetId : null,
                'label' => $label !== '' ? mb_substr($label, 0, 1000) : null,
                'widget_slug' => $widgetSlug,
                'widget_comment' => $widgetSlug !== null && $widgetComment !== ''
                    ? mb_substr($widgetComment, 0, 2000)
                    : null,
                'path_points' => $mode === 'stay' ? [] : self::normalizePathPoints($row['path_points'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{x: int, y: int}>
     */
    public static function normalizePathPoints(mixed $points): array
    {
        if (! is_array($points)) {
            return [];
        }

        $normalized = [];

        foreach ($points as $point) {
            if (! is_array($point)) {
                continue;
            }

            if (! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'x' => max(0, (int) $point['x']),
                'y' => max(0, (int) $point['y']),
            ];

            if (count($normalized) >= 8) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>|null  $outcomes
     * @param  array<int, int>  $idMap  old stage id => new stage id
     * @param  array<string, string>  $widgetIdMap  old widget uuid => new uuid
     * @return list<array<string, mixed>>
     */
    public static function remapIds(?array $outcomes, array $idMap, array $widgetIdMap = []): array
    {
        return array_map(function (array $row) use ($idMap, $widgetIdMap) {
            if (($row['target_type'] ?? null) === 'widget' || filled($row['target_widget_id'] ?? null)) {
                $old = (string) ($row['target_widget_id'] ?? '');
                $row['target_widget_id'] = $widgetIdMap[$old] ?? null;
                $row['target_type'] = $row['target_widget_id'] !== null ? 'widget' : null;
                $row['target_stage_id'] = null;
            } else {
                $target = $row['target_stage_id'] ?? null;

                if ($target !== null) {
                    $row['target_stage_id'] = $idMap[(int) $target] ?? null;
                    $row['target_type'] = $row['target_stage_id'] !== null ? 'stage' : null;
                }
            }

            return $row;
        }, self::normalize($outcomes));
    }

    /**
     * @param  list<array<string, mixed>>|null  $outcomes
     * @param  array<int, int>  $sortOrderToId
     * @return list<array<string, mixed>>
     */
    public static function toProjectIds(?array $outcomes, array $sortOrderToId): array
    {
        return array_map(function (array $row) use ($sortOrderToId) {
            if (($row['target_type'] ?? null) === 'widget') {
                return $row;
            }

            $sort = $row['target_sort_order'] ?? null;
            $id = $row['target_stage_id'] ?? null;

            if ($id === null && $sort !== null) {
                $row['target_stage_id'] = $sortOrderToId[(int) $sort] ?? null;
            }

            if ($row['target_stage_id'] !== null) {
                $row['target_type'] = 'stage';
            }

            return $row;
        }, self::normalize($outcomes));
    }

    /**
     * @param  list<array<string, mixed>>|null  $outcomes
     * @param  array<int, int>  $idToSortOrder
     * @return list<array<string, mixed>>
     */
    public static function toSortOrders(?array $outcomes, array $idToSortOrder): array
    {
        return array_map(function (array $row) use ($idToSortOrder) {
            if (($row['target_type'] ?? null) === 'widget') {
                $row['target_stage_id'] = null;
                $row['target_sort_order'] = null;

                return $row;
            }

            $id = $row['target_stage_id'] ?? null;

            if ($id !== null) {
                $row['target_sort_order'] = $idToSortOrder[(int) $id] ?? $row['target_sort_order'] ?? null;
                $row['target_stage_id'] = null;
            }

            return $row;
        }, self::normalize($outcomes));
    }
}
