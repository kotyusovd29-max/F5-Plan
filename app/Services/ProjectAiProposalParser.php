<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use App\Models\Project;
use App\Models\ProjectSkkField;
use Illuminate\Support\Facades\DB;

class ProjectAiProposalParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $content): array
    {
        $json = $this->extractJson($content);

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new F5AiException('ИИ вернул ответ в неверном формате. Попробуйте сгенерировать ещё раз.');
        }

        return $this->normalize($decoded);
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $normalized = [
            'skk_fields' => [
                'deals' => [],
                'contacts' => [],
                'companies' => [],
            ],
            'integrations' => [],
            'funnels' => [],
        ];

        if (isset($data['skk_fields']) && is_array($data['skk_fields'])) {
            foreach (ProjectSkkField::TYPES as $type) {
                $normalized['skk_fields'][$type] = $this->normalizeItems(
                    $data['skk_fields'][$type] ?? [],
                    ['name', 'comment']
                );
            }
        }

        if (isset($data['integrations']) && is_array($data['integrations'])) {
            $normalized['integrations'] = $this->normalizeItems($data['integrations'], ['name', 'comment']);
        }

        if (isset($data['funnels']) && is_array($data['funnels'])) {
            foreach ($data['funnels'] as $funnel) {
                if (! is_array($funnel)) {
                    continue;
                }

                $name = trim((string) ($funnel['name'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $stages = [];

                foreach ($funnel['stages'] ?? [] as $stage) {
                    if (! is_array($stage)) {
                        continue;
                    }

                    $stageName = trim((string) ($stage['name'] ?? ''));

                    if ($stageName === '') {
                        continue;
                    }

                    $stages[] = [
                        'name' => $stageName,
                        'action' => trim((string) ($stage['action'] ?? '')),
                        'comment' => trim((string) ($stage['comment'] ?? '')),
                    ];
                }

                $normalized['funnels'][] = [
                    'name' => $name,
                    'description' => trim((string) ($funnel['description'] ?? '')),
                    'stages' => $stages,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $fields
     * @return array<int, array<string, string>>
     */
    private function normalizeItems(array $items, array $fields): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = [];

            foreach ($fields as $field) {
                $row[$field] = trim((string) ($item[$field] ?? ''));
            }

            if (($row['name'] ?? '') === '') {
                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}
