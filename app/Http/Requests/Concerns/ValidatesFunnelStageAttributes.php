<?php

namespace App\Http\Requests\Concerns;

use App\Support\F5WidgetCatalog;
use App\Support\FunnelStageOutcomes;
use App\Support\FunnelStageWidgets;
use Illuminate\Validation\Rule;

trait ValidatesFunnelStageAttributes
{
    /**
     * @return array<string, mixed>
     */
    protected function funnelStageAttributeRules(?int $funnelId = null, string $prefix = ''): array
    {
        $p = $prefix;
        $slugs = F5WidgetCatalog::slugs();

        return [
            $p.'stage_type' => ['nullable', 'string', 'max:255'],
            $p.'deadline' => ['nullable', 'string', 'max:255'],
            $p.'kind' => ['nullable', 'string', Rule::in(FunnelStageOutcomes::KINDS)],
            $p.'outcomes' => ['nullable', 'array', 'max:30'],
            $p.'outcomes.*.result' => ['required_with:'.$p.'outcomes', 'string', 'max:1000'],
            $p.'outcomes.*.mode' => ['required_with:'.$p.'outcomes', 'string', Rule::in(FunnelStageOutcomes::MODES)],
            $p.'outcomes.*.label' => ['nullable', 'string', 'max:1000'],
            $p.'outcomes.*.widget_slug' => ['nullable', 'string', Rule::in($slugs)],
            $p.'outcomes.*.widget_comment' => ['nullable', 'string', 'max:2000'],
            $p.'outcomes.*.target_type' => ['nullable', 'string', Rule::in(FunnelStageOutcomes::TARGET_TYPES)],
            $p.'outcomes.*.target_widget_id' => ['nullable', 'uuid'],
            $p.'outcomes.*.target_stage_id' => array_values(array_filter([
                'nullable',
                'integer',
                $funnelId
                    ? Rule::exists('funnel_stages', 'id')->where('funnel_id', $funnelId)
                    : null,
            ])),
            $p.'outcomes.*.path_points' => ['nullable', 'array', 'max:8'],
            $p.'outcomes.*.path_points.*.x' => ['required_with:'.$p.'outcomes.*.path_points', 'integer', 'min:0'],
            $p.'outcomes.*.path_points.*.y' => ['required_with:'.$p.'outcomes.*.path_points', 'integer', 'min:0'],
            $p.'widgets' => ['nullable', 'array', 'max:20'],
            $p.'widgets.*.widget_slug' => ['required_with:'.$p.'widgets', 'string', Rule::in($slugs)],
            $p.'widgets.*.comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeFunnelStageAttributes(array $data): array
    {
        if (array_key_exists('kind', $data) && ($data['kind'] === null || $data['kind'] === '')) {
            $data['kind'] = 'normal';
        }

        if (array_key_exists('widgets', $data)) {
            $data['widgets'] = FunnelStageWidgets::normalize($data['widgets']);
        }

        if (array_key_exists('outcomes', $data)) {
            $stageWidgets = is_array($data['widgets'] ?? null) ? $data['widgets'] : [];
            $boardWidgetIds = $this->boardWidgetIdsForNormalization();
            $data['outcomes'] = FunnelStageOutcomes::normalize(
                $data['outcomes'],
                $stageWidgets,
                $boardWidgetIds
            );
        }

        return $data;
    }

    /**
     * @return list<string>|null
     */
    protected function boardWidgetIdsForNormalization(): ?array
    {
        $funnel = $this->route('funnel');

        if (! $funnel instanceof \App\Models\Funnel) {
            return null;
        }

        $widgets = is_array($funnel->board_widgets) ? $funnel->board_widgets : [];

        return array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? trim((string) ($row['id'] ?? '')) : '',
            $widgets
        )));
    }
}
