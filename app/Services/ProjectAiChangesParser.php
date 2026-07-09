<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use App\Models\ProjectSkkField;

class ProjectAiChangesParser
{
    /**
     * @return array{message: string, changes: array<string, mixed>|null}
     */
    public function parse(string $content): array
    {
        $json = $this->extractJson($content);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new F5AiException('ИИ вернул ответ в неверном формате. Попробуйте переформулировать запрос.');
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

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($content, $start, $end - $start + 1);
        }

        return $content;
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

            foreach (['company_name', 'employees_count', 'client_tasks'] as $field) {
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
                ['name', 'comment'],
                true
            );
        }

        if (isset($changes['integrations']) && is_array($changes['integrations'])) {
            $normalized['integrations'] = $this->normalizeOperations(
                $changes['integrations'],
                ['name', 'comment']
            );
        }

        if (isset($changes['funnels']) && is_array($changes['funnels'])) {
            $normalized['funnels'] = $this->normalizeFunnelOperations($changes['funnels']);
        }

        if (isset($changes['stages']) && is_array($changes['stages'])) {
            $normalized['stages'] = $this->normalizeOperations(
                $changes['stages'],
                ['funnel_id', 'name', 'action', 'comment']
            );
        }

        return $normalized;
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
                if (! is_array($stage) || trim((string) ($stage['name'] ?? '')) === '') {
                    continue;
                }

                $stages[] = [
                    'name' => trim((string) $stage['name']),
                    'action' => trim((string) ($stage['action'] ?? '')),
                    'comment' => trim((string) ($stage['comment'] ?? '')),
                ];
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

                        if ($field === 'funnel_id') {
                            $row[$field] = (int) $value;
                        } elseif ($field === 'employees_count') {
                            $row[$field] = $value !== null ? (int) $value : null;
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
}
