<?php

namespace App\Support;

use App\Models\Project;

class ProjectStatus
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return Project::STATUSES;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return Project::STATUS_LABELS;
    }

    /**
     * Опции для селекта статуса (без отдельного пункта «все»).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(Project::STATUS_LABELS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * Фильтры списка проектов на главной.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function filterOptions(bool $includeDeleted = false): array
    {
        $filters = [
            ['value' => 'active', 'label' => 'Активные'],
            ['value' => 'all', 'label' => 'Все'],
        ];

        foreach (Project::STATUS_LABELS as $value => $label) {
            $filters[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        if ($includeDeleted) {
            $filters[] = [
                'value' => 'deleted',
                'label' => 'Удалённые',
            ];
        }

        return $filters;
    }

    public static function label(?string $status): string
    {
        return Project::STATUS_LABELS[$status] ?? (string) $status;
    }

    public static function isValid(?string $status): bool
    {
        return is_string($status) && in_array($status, Project::STATUSES, true);
    }
}
