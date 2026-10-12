<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectWorkStage;

class WorkStagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(ProjectWorkStage $stage, Project $project): array
    {
        $cost = $stage->cost !== null ? (float) $stage->cost : null;
        $durationDays = $stage->duration_days !== null ? (int) $stage->duration_days : null;

        $compositionItems = WorkStageComposition::items($stage->composition);

        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'composition' => WorkStageComposition::format($stage->composition) ?? $stage->composition,
            'composition_items' => $compositionItems,
            'cost' => $cost,
            'cost_label' => $cost !== null
                ? number_format($cost, $cost == floor($cost) ? 0 : 2, ',', ' ').' ₽'
                : null,
            'duration_days' => $durationDays,
            'duration_label' => $durationDays !== null
                ? $durationDays.' '.self::dayWord($durationDays)
                : null,
            'summary' => self::summary($stage),
            'update_url' => route('projects.work-stages.update', [$project, $stage]),
            'destroy_url' => route('projects.work-stages.destroy', [$project, $stage]),
        ];
    }

    private static function summary(ProjectWorkStage $stage): string
    {
        $parts = [];

        if (filled($stage->composition)) {
            $text = trim((string) $stage->composition);
            $parts[] = mb_strlen($text) > 80 ? mb_substr($text, 0, 80).'…' : $text;
        }

        if ($stage->duration_days !== null) {
            $days = (int) $stage->duration_days;
            $parts[] = $days.' '.self::dayWord($days);
        }

        if ($stage->cost !== null) {
            $cost = (float) $stage->cost;
            $parts[] = number_format($cost, $cost == floor($cost) ? 0 : 2, ',', ' ').' ₽';
        }

        return implode(' · ', $parts);
    }

    private static function dayWord(int $days): string
    {
        $mod10 = $days % 10;
        $mod100 = $days % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 'раб. день';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'раб. дня';
        }

        return 'раб. дней';
    }
}
