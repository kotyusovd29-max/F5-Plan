@php
    use App\Models\ProjectSkkField;

    $sections = [];

    if (! empty($changes['project']['fields'])) {
        $sections[] = ['title' => 'Основная информация', 'items' => collect($changes['project']['fields'])->map(fn ($v, $k) => "$k: $v")->values()->all()];
    }

    foreach (['skk_fields' => 'Поля', 'integrations' => 'Интеграции', 'funnels' => 'Воронки', 'stages' => 'Этапы'] as $key => $title) {
        if (empty($changes[$key])) {
            continue;
        }

        $items = [];

        foreach (['create' => 'Создать', 'update' => 'Изменить', 'delete' => 'Удалить'] as $action => $label) {
            $rows = $changes[$key][$action] ?? [];

            if ($rows === []) {
                continue;
            }

            if ($action === 'delete') {
                $items[] = $label.': #'.implode(', #', $rows);
                continue;
            }

            foreach ($rows as $row) {
                if ($key === 'skk_fields') {
                    $entity = ProjectSkkField::TYPE_LABELS[$row['entity_type'] ?? ''] ?? ($row['entity_type'] ?? '');
                    $items[] = $label.($row['id'] ?? null ? " #{$row['id']}" : '')." ({$entity}): ".($row['name'] ?? '');
                } elseif ($key === 'funnels') {
                    $line = $label.($row['id'] ?? null ? " #{$row['id']}" : '').': '.($row['name'] ?? '');
                    $stageCount = count($row['stages'] ?? []);
                    if ($stageCount > 0) {
                        $line .= " ({$stageCount} этапов)";
                    }
                    $items[] = $line;
                } elseif ($key === 'stages') {
                    $items[] = $label.($row['id'] ?? null ? " #{$row['id']}" : '').' воронка #'.($row['funnel_id'] ?? '?').': '.($row['name'] ?? '');
                } else {
                    $items[] = $label.($row['id'] ?? null ? " #{$row['id']}" : '').': '.($row['name'] ?? '');
                }
            }
        }

        if ($items !== []) {
            $sections[] = ['title' => $title, 'items' => $items];
        }
    }
@endphp

@if ($sections === [])
    <p class="ai-changes-preview__empty">Нет структурированных изменений.</p>
@else
    <div class="ai-changes-preview">
        @foreach ($sections as $section)
            <div class="ai-changes-preview__block">
                <h4 class="ai-changes-preview__title">{{ $section['title'] }}</h4>
                <ul class="ai-changes-preview__list">
                    @foreach ($section['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif
