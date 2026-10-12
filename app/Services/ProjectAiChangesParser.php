<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use App\Models\ProjectSkkField;
use App\Support\F5WidgetCatalog;
use App\Support\WorkStageComposition;
use App\Support\FunnelStageOutcomes;
use App\Support\FunnelStageWidgets;

class ProjectAiChangesParser
{
    /**
     * @return array{message: string, changes: array<string, mixed>|null}
     */
    public function parse(string $content, ?string $invalidFormatMessage = null): array
    {
        $decoded = $this->decodeJsonPayload($content);

        if (! is_array($decoded)) {
            throw new F5AiException(
                $invalidFormatMessage
                    ?? 'ИИ вернул ответ в неверном формате. Попробуйте переформулировать запрос.'
            );
        }

        $message = trim((string) ($decoded['message'] ?? $decoded['reply'] ?? ''));

        if ($message === '' && isset($decoded['changes'])) {
            $message = 'Подготовил изменения для проекта.';
        }

        if ($message === '' && ! isset($decoded['changes'])) {
            $message = trim($content);
        }

        if (! isset($decoded['changes']) || $decoded['changes'] === null) {
            return [
                'message' => $message,
                'changes' => null,
            ];
        }

        return [
            'message' => $message,
            'changes' => $this->normalizeChanges($decoded['changes']),
        ];
    }

    private function extractJson(string $content): string
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches)) {
            return trim($matches[1]);
        }

        $balanced = $this->extractBalancedJsonObject($content);

        if ($balanced !== null) {
            return $balanced;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($content, $start, $end - $start + 1);
        }

        return $content;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(string $content): ?array
    {
        $candidates = array_values(array_unique(array_filter([
            $this->extractJson($content),
            $this->extractBalancedJsonObject($content),
            trim($content),
        ])));

        foreach ($candidates as $candidate) {
            foreach ($this->jsonRepairVariants($candidate) as $variant) {
                $decoded = json_decode($variant, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function jsonRepairVariants(string $json): array
    {
        $json = trim($json);
        $variants = [$json];

        $withoutBom = preg_replace('/^\xEF\xBB\xBF/', '', $json) ?? $json;

        if ($withoutBom !== $json) {
            $variants[] = $withoutBom;
        }

        $withoutTrailingCommas = preg_replace('/,\s*([\]}])/', '$1', $json) ?? $json;

        if ($withoutTrailingCommas !== $json) {
            $variants[] = $withoutTrailingCommas;
        }

        $combined = preg_replace('/,\s*([\]}])/', '$1', $withoutBom) ?? $withoutBom;

        if ($combined !== $json) {
            $variants[] = $combined;
        }

        return array_values(array_unique($variants));
    }

    private function extractBalancedJsonObject(string $content): ?string
    {
        $start = strpos($content, '{');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($content);

        for ($index = $start; $index < $length; $index++) {
            $char = $content[$index];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $depth++;

                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function normalizeChanges(array $changes): array
    {
        $normalized = [];

        if (isset($changes['project']) && is_array($changes['project'])) {
            $fields = [];

            foreach (['company_name', 'website', 'business_sphere', 'current_services', 'employees_count', 'client_tasks'] as $field) {
                if (array_key_exists($field, $changes['project'])) {
                    $fields[$field] = $changes['project'][$field];
                } elseif (isset($changes['project']['fields'][$field])) {
                    $fields[$field] = $changes['project']['fields'][$field];
                }
            }

            if ($fields !== []) {
                $normalized['project'] = ['fields' => $fields];
            }
        }

        if (isset($changes['skk_fields']) && is_array($changes['skk_fields'])) {
            $normalized['skk_fields'] = $this->normalizeOperations(
                $changes['skk_fields'],
                ['name', 'comment', 'value_type', 'is_required', 'show_in_card', 'show_in_list', 'show_in_filter', 'options', 'funnel_stage_id'],
                true
            );
        }

        if (isset($changes['integrations']) && is_array($changes['integrations'])) {
            $normalized['integrations'] = $this->normalizeIntegrationOperations($changes['integrations']);
        }

        if (isset($changes['funnels']) && is_array($changes['funnels'])) {
            $normalized['funnels'] = $this->normalizeFunnelOperations($changes['funnels']);
        }

        if (isset($changes['stages']) && is_array($changes['stages'])) {
            $normalized['stages'] = $this->normalizeOperations(
                $changes['stages'],
                ['funnel_id', 'name', 'action', 'comment', 'stage_type', 'deadline', 'kind', 'outcomes', 'widgets']
            );
        }

        if (isset($changes['work_stages']) && is_array($changes['work_stages'])) {
            $normalized['work_stages'] = $this->normalizeWorkStageOperations($changes['work_stages']);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function normalizeWorkStageOperations(array $section): array
    {
        $normalized = [
            'create' => [],
            'update' => [],
            'delete' => [],
        ];

        foreach ($section['delete'] ?? [] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : $item;

            if ($id) {
                $normalized['delete'][] = (int) $id;
            }
        }

        foreach ($section['update'] ?? [] as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }

            $row = ['id' => (int) $item['id']];

            if (array_key_exists('name', $item)) {
                $name = trim((string) $item['name']);

                if ($name !== '') {
                    $row['name'] = $name;
                }
            }

            if (array_key_exists('composition', $item)) {
                $row['composition'] = $this->normalizeWorkStageComposition($item['composition']);
            }

            if (array_key_exists('cost', $item)) {
                $row['cost'] = $this->normalizeCost($item['cost']);
            }

            if (array_key_exists('duration_days', $item)) {
                $row['duration_days'] = $this->normalizeDurationDays($item['duration_days']);
            }

            $normalized['update'][] = $row;
        }

        foreach ($section['create'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $normalized['create'][] = [
                'name' => $name,
                'composition' => $this->normalizeWorkStageComposition($item['composition'] ?? null),
                'cost' => $this->normalizeCost($item['cost'] ?? null),
                'duration_days' => $this->normalizeDurationDays($item['duration_days'] ?? null),
            ];
        }

        return $normalized;
    }

    private function normalizeWorkStageComposition(mixed $value): ?string
    {
        return WorkStageComposition::format($value);
    }

    private function normalizeCost(mixed $value): ?float
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

    private function normalizeDurationDays(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            if (preg_match('/(\d+)/u', $value, $matches) !== 1) {
                return null;
            }

            $value = $matches[1];
        }

        if (! is_numeric($value)) {
            return null;
        }

        $days = (int) round((float) $value);

        return $days >= 1 ? $days : null;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function normalizeFunnelOperations(array $section): array
    {
        $normalized = [
            'create' => [],
            'update' => [],
            'delete' => [],
        ];

        foreach ($section['delete'] ?? [] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : $item;

            if ($id) {
                $normalized['delete'][] = (int) $id;
            }
        }

        foreach ($section['update'] ?? [] as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }

            $row = ['id' => (int) $item['id']];

            foreach (['name', 'description'] as $field) {
                if (array_key_exists($field, $item)) {
                    $row[$field] = is_string($item[$field]) ? trim($item[$field]) : $item[$field];
                }
            }

            $normalized['update'][] = $row;
        }

        foreach ($section['create'] ?? [] as $item) {
            if (! is_array($item) || trim((string) ($item['name'] ?? '')) === '') {
                continue;
            }

            $row = [
                'name' => trim((string) $item['name']),
                'description' => trim((string) ($item['description'] ?? '')),
            ];

            $stages = [];

            foreach ($item['stages'] ?? [] as $stage) {
                if (! is_array($stage)) {
                    continue;
                }

                $normalizedStage = $this->normalizeStagePayload($stage);

                if ($normalizedStage === null) {
                    continue;
                }

                $stages[] = $normalizedStage;
            }

            if ($stages !== []) {
                $row['stages'] = $stages;
            }

            $normalized['create'][] = $row;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function normalizeIntegrationOperations(array $section): array
    {
        $normalized = [
            'create' => [],
            'update' => [],
            'delete' => [],
        ];

        foreach ($section['delete'] ?? [] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : $item;

            if ($id) {
                $normalized['delete'][] = (int) $id;
            }
        }

        foreach ($section['update'] ?? [] as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }

            $payload = ['id' => (int) $item['id']];

            foreach (['widget_slug', 'name', 'comment'] as $field) {
                if (array_key_exists($field, $item)) {
                    $value = $item[$field];
                    $payload[$field] = $field === 'widget_slug' && ($value === null || $value === '')
                        ? null
                        : (is_string($value) ? trim($value) : $value);
                }
            }

            $resolved = F5WidgetCatalog::normalizeIntegrationInput($payload);

            if ($resolved['name'] === '') {
                continue;
            }

            $normalized['update'][] = [
                'id' => (int) $item['id'],
                ...$resolved,
            ];
        }

        foreach ($section['create'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $payload = [];

            foreach (['widget_slug', 'name', 'comment'] as $field) {
                if (array_key_exists($field, $item)) {
                    $value = $item[$field];
                    $payload[$field] = $field === 'widget_slug' && ($value === null || $value === '')
                        ? null
                        : (is_string($value) ? trim($value) : $value);
                }
            }

            $resolved = F5WidgetCatalog::normalizeIntegrationInput($payload);

            if ($resolved['name'] === '') {
                continue;
            }

            $normalized['create'][] = $resolved;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function normalizeOperations(array $section, array $fields, bool $withEntityType = false): array
    {
        $normalized = [
            'create' => [],
            'update' => [],
            'delete' => [],
        ];

        foreach (['create', 'update', 'delete'] as $action) {
            foreach ($section[$action] ?? [] as $item) {
                if ($action === 'delete') {
                    $id = is_array($item) ? ($item['id'] ?? null) : $item;

                    if ($id) {
                        $normalized['delete'][] = (int) $id;
                    }

                    continue;
                }

                if (! is_array($item)) {
                    continue;
                }

                $row = [];

                if ($action === 'update' && isset($item['id'])) {
                    $row['id'] = (int) $item['id'];
                }

                if ($withEntityType && isset($item['entity_type'])) {
                    $entityType = (string) $item['entity_type'];

                    if (in_array($entityType, ProjectSkkField::TYPES, true)) {
                        $row['entity_type'] = $entityType;
                    }
                }

                foreach ($fields as $field) {
                    if (array_key_exists($field, $item)) {
                        $value = $item[$field];

                        if ($field === 'funnel_id' || $field === 'funnel_stage_id') {
                            $row[$field] = $value !== null && $value !== '' ? (int) $value : null;
                        } elseif ($field === 'employees_count') {
                            $row[$field] = $value !== null ? (int) $value : null;
                        } elseif (in_array($field, ['is_required', 'show_in_card', 'show_in_list', 'show_in_filter'], true)) {
                            $row[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                        } elseif ($field === 'options') {
                            $row[$field] = ProjectSkkField::normalizeOptions($value);
                        } elseif ($field === 'outcomes') {
                            $row[$field] = $this->normalizeStageOutcomes(
                                $value,
                                FunnelStageWidgets::normalize($item['widgets'] ?? [])
                            );
                        } elseif ($field === 'widgets') {
                            $row[$field] = FunnelStageWidgets::normalize($value);
                        } elseif ($field === 'kind') {
                            $kind = is_string($value) ? trim($value) : (string) $value;
                            $row[$field] = in_array($kind, FunnelStageOutcomes::KINDS, true) ? $kind : 'normal';
                        } else {
                            $row[$field] = is_string($value) ? trim($value) : $value;
                        }
                    }
                }

                if ($action === 'create' && ($row['name'] ?? '') === '' && ! $withEntityType) {
                    continue;
                }

                if ($action === 'create' && $withEntityType && (($row['name'] ?? '') === '' || empty($row['entity_type']))) {
                    continue;
                }

                if ($action === 'update' && empty($row['id'])) {
                    continue;
                }

                $normalized[$action][] = $row;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return array<string, mixed>|null
     */
    private function normalizeStagePayload(array $stage): ?array
    {
        $name = trim((string) ($stage['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $row = [
            'name' => $name,
            'action' => trim((string) ($stage['action'] ?? '')),
            'comment' => trim((string) ($stage['comment'] ?? '')),
        ];

        if (array_key_exists('stage_type', $stage)) {
            $row['stage_type'] = trim((string) $stage['stage_type']);
        }

        if (array_key_exists('deadline', $stage)) {
            $row['deadline'] = trim((string) $stage['deadline']);
        }

        if (array_key_exists('kind', $stage)) {
            $kind = trim((string) $stage['kind']);
            $row['kind'] = in_array($kind, FunnelStageOutcomes::KINDS, true) ? $kind : 'normal';
        }

        if (array_key_exists('widgets', $stage)) {
            $row['widgets'] = FunnelStageWidgets::normalize($stage['widgets']);
        }

        if (array_key_exists('outcomes', $stage)) {
            $row['outcomes'] = $this->normalizeStageOutcomes(
                $stage['outcomes'],
                $row['widgets'] ?? FunnelStageWidgets::normalize($stage['widgets'] ?? [])
            );
        }

        return $row;
    }

    /**
     * @param  list<array{widget_slug?: string}>  $stageWidgets
     * @return list<array<string, mixed>>
     */
    private function normalizeStageOutcomes(mixed $value, array $stageWidgets = []): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result = trim((string) ($row['result'] ?? ''));
            $mode = (string) ($row['mode'] ?? 'move');

            if ($result === '' || ! in_array($mode, FunnelStageOutcomes::MODES, true)) {
                continue;
            }

            $normalizedRow = FunnelStageOutcomes::normalize([$row])[0] ?? null;

            if ($normalizedRow === null) {
                continue;
            }

            $targetName = trim((string) ($row['target_stage_name'] ?? ''));

            if ($targetName !== '') {
                $normalizedRow['target_stage_name'] = mb_substr($targetName, 0, 255);
            }

            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }
}
