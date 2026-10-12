<?php

namespace App\Services;

use App\Exceptions\AmoCrmException;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectAmocrmAccount;
use App\Models\ProjectSkkField;
use App\Support\AmoCrmMapper;
use App\Support\FunnelStageColors;
use Illuminate\Support\Facades\DB;

class AmoCrmPipelineImportService
{
    public function __construct(
        private readonly CanvasLayoutService $canvasLayoutService
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     is_main: bool,
     *     stages_total: int,
     *     stages_regular: int,
     *     stages_unsorted: int,
     *     stages_system: int,
     *     already_imported: bool,
     *     project_funnel_id: int|null
     * }>
     */
    public function listPipelines(Project $project): array
    {
        $client = $this->clientFor($project);
        $pipelines = $client->getPipelines();
        $existingByAmoId = $project->funnels()
            ->whereNotNull('amocrm_id')
            ->pluck('id', 'amocrm_id')
            ->all();

        $items = [];

        foreach ($pipelines as $pipeline) {
            if (! is_array($pipeline) || empty($pipeline['id'])) {
                continue;
            }

            $statuses = $pipeline['_embedded']['statuses'] ?? [];
            if (! is_array($statuses)) {
                $statuses = [];
            }

            $unsorted = 0;
            $system = 0;
            $regular = 0;

            foreach ($statuses as $status) {
                if (! is_array($status)) {
                    continue;
                }

                if ($this->isUnsortedStatus($status)) {
                    $unsorted++;
                } elseif ($this->isSystemClosedStatus($status)) {
                    $system++;
                } else {
                    $regular++;
                }
            }

            $amoId = (int) $pipeline['id'];
            $projectFunnelId = isset($existingByAmoId[$amoId]) ? (int) $existingByAmoId[$amoId] : null;

            $items[] = [
                'id' => $amoId,
                'name' => (string) ($pipeline['name'] ?? ('Воронка #'.$amoId)),
                'is_main' => (bool) ($pipeline['is_main'] ?? false),
                'stages_total' => $unsorted + $system + $regular,
                'stages_regular' => $regular,
                'stages_unsorted' => $unsorted,
                'stages_system' => $system,
                'already_imported' => $projectFunnelId !== null,
                'project_funnel_id' => $projectFunnelId,
            ];
        }

        usort($items, function (array $a, array $b): int {
            if ($a['is_main'] !== $b['is_main']) {
                return $a['is_main'] ? -1 : 1;
            }

            return strcmp($a['name'], $b['name']);
        });

        return $items;
    }

    /**
     * @return array{
     *     total: int,
     *     importable: int,
     *     already_imported: int,
     *     by_entity: array{deals: int, contacts: int, companies: int}
     * }
     */
    public function fieldsSummary(Project $project): array
    {
        $client = $this->clientFor($project);
        $existingIds = $project->skkFields()
            ->whereNotNull('amocrm_id')
            ->pluck('amocrm_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $byEntity = [
            ProjectSkkField::TYPE_DEALS => 0,
            ProjectSkkField::TYPE_CONTACTS => 0,
            ProjectSkkField::TYPE_COMPANIES => 0,
        ];
        $importable = 0;
        $alreadyImported = 0;

        foreach (AmoCrmMapper::ENTITY_MAP as $skkEntity => $amoEntity) {
            foreach ($client->getCustomFields($amoEntity) as $field) {
                if (! $this->isImportableCustomField($field)) {
                    continue;
                }

                $importable++;
                $byEntity[$skkEntity]++;

                if (in_array((int) ($field['id'] ?? 0), $existingIds, true)) {
                    $alreadyImported++;
                }
            }
        }

        return [
            'total' => $importable,
            'importable' => $importable,
            'already_imported' => $alreadyImported,
            'by_entity' => $byEntity,
        ];
    }

    /**
     * @param  list<int>  $pipelineIds
     * @return array{
     *     funnels_created: int,
     *     funnels_updated: int,
     *     stages_created: int,
     *     stages_updated: int,
     *     fields_created: int,
     *     fields_updated: int,
     *     fields_skipped: int
     * }
     */
    public function import(
        Project $project,
        array $pipelineIds,
        bool $includeUnsorted = false,
        bool $includeSystem = false,
        bool $includeFields = true
    ): array {
        $pipelineIds = array_values(array_unique(array_map('intval', $pipelineIds)));
        $pipelineIds = array_values(array_filter($pipelineIds, fn (int $id) => $id > 0));

        if ($pipelineIds === [] && ! $includeFields) {
            throw new AmoCrmException('Выберите хотя бы одну воронку или включите импорт полей СКК.');
        }

        $client = $this->clientFor($project);
        $pipelines = [];

        if ($pipelineIds !== []) {
            $pipelines = collect($client->getPipelines())
                ->filter(fn ($pipeline) => is_array($pipeline) && in_array((int) ($pipeline['id'] ?? 0), $pipelineIds, true))
                ->values()
                ->all();

            if ($pipelines === []) {
                throw new AmoCrmException('Выбранные воронки не найдены в amoCRM.');
            }
        }

        $stats = [
            'funnels_created' => 0,
            'funnels_updated' => 0,
            'stages_created' => 0,
            'stages_updated' => 0,
            'fields_created' => 0,
            'fields_updated' => 0,
            'fields_skipped' => 0,
        ];

        DB::transaction(function () use (
            $project,
            $client,
            $pipelines,
            $includeUnsorted,
            $includeSystem,
            $includeFields,
            &$stats
        ): void {
            foreach ($pipelines as $pipeline) {
                $this->importPipeline($project, $pipeline, $includeUnsorted, $includeSystem, $stats);
            }

            if ($includeFields) {
                $this->importSkkFields($project, $client, $stats);
            }
        });

        $this->canvasLayoutService->syncBlocks($project->fresh());

        return $stats;
    }

    /**
     * @param  array{
     *     funnels_created: int,
     *     funnels_updated: int,
     *     stages_created: int,
     *     stages_updated: int,
     *     fields_created: int,
     *     fields_updated: int,
     *     fields_skipped: int
     * }  $stats
     */
    private function importSkkFields(Project $project, AmoCrmClient $client, array &$stats): void
    {
        $existingByAmoId = $project->skkFields()
            ->whereNotNull('amocrm_id')
            ->get()
            ->keyBy(fn (ProjectSkkField $field) => (int) $field->amocrm_id);

        $nextSort = ((int) $project->skkFields()->max('sort_order')) + 1;

        foreach (AmoCrmMapper::ENTITY_MAP as $skkEntity => $amoEntity) {
            foreach ($client->getCustomFields($amoEntity) as $amoField) {
                if (! $this->isImportableCustomField($amoField)) {
                    $stats['fields_skipped']++;

                    continue;
                }

                $amoId = (int) ($amoField['id'] ?? 0);
                $name = trim((string) ($amoField['name'] ?? ''));
                $valueType = AmoCrmMapper::skkValueType((string) ($amoField['type'] ?? ''));

                if ($amoId <= 0 || $name === '' || $valueType === null) {
                    $stats['fields_skipped']++;

                    continue;
                }

                $options = null;

                if (in_array($valueType, [ProjectSkkField::VALUE_SELECT, ProjectSkkField::VALUE_MULTISELECT], true)) {
                    $options = $this->extractFieldOptions($amoField);

                    if ($options === null || $options === []) {
                        $stats['fields_skipped']++;

                        continue;
                    }
                }

                $payload = [
                    'entity_type' => $skkEntity,
                    'name' => $name,
                    'value_type' => $valueType,
                    'is_required' => (bool) ($amoField['is_required'] ?? false),
                    'show_in_card' => ! (bool) ($amoField['is_api_only'] ?? false),
                    'show_in_list' => false,
                    'show_in_filter' => false,
                    'options' => $options,
                    'funnel_stage_id' => null,
                    'comment' => null,
                    'amocrm_id' => $amoId,
                ];

                /** @var ProjectSkkField|null $existing */
                $existing = $existingByAmoId->get($amoId);

                if ($existing) {
                    $existing->update($payload);
                    $stats['fields_updated']++;

                    continue;
                }

                $project->skkFields()->create([
                    ...$payload,
                    'sort_order' => $nextSort,
                ]);
                $nextSort++;
                $stats['fields_created']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isImportableCustomField(array $field): bool
    {
        if (empty($field['id']) || trim((string) ($field['name'] ?? '')) === '') {
            return false;
        }

        if (($field['is_predefined'] ?? false) === true) {
            return false;
        }

        $type = (string) ($field['type'] ?? '');

        return AmoCrmMapper::skkValueType($type) !== null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>|null
     */
    private function extractFieldOptions(array $field): ?array
    {
        $enums = $field['enums'] ?? null;

        if (! is_array($enums)) {
            return null;
        }

        $options = [];

        foreach ($enums as $enum) {
            if (! is_array($enum)) {
                continue;
            }

            $value = trim((string) ($enum['value'] ?? ''));

            if ($value !== '') {
                $options[] = $value;
            }
        }

        return $options === [] ? null : array_values(array_unique($options));
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @param  array{
     *     funnels_created: int,
     *     funnels_updated: int,
     *     stages_created: int,
     *     stages_updated: int
     * }  $stats
     */
    private function importPipeline(
        Project $project,
        array $pipeline,
        bool $includeUnsorted,
        bool $includeSystem,
        array &$stats
    ): void {
        $amoId = (int) ($pipeline['id'] ?? 0);
        $name = trim((string) ($pipeline['name'] ?? ''));

        if ($amoId <= 0 || $name === '') {
            return;
        }

        $statuses = $pipeline['_embedded']['statuses'] ?? [];
        if (! is_array($statuses)) {
            $statuses = [];
        }

        $statuses = collect($statuses)
            ->filter(fn ($status) => is_array($status) && ! empty($status['id']))
            ->sortBy(fn (array $status) => (int) ($status['sort'] ?? 0))
            ->values()
            ->all();

        /** @var Funnel|null $funnel */
        $funnel = $project->funnels()->where('amocrm_id', $amoId)->first();

        if ($funnel) {
            $funnel->update([
                'name' => $name,
            ]);
            $stats['funnels_updated']++;
        } else {
            $sortOrder = ((int) $project->funnels()->max('sort_order')) + 1;
            $funnel = $project->funnels()->create([
                'name' => $name,
                'description' => null,
                'sort_order' => $sortOrder,
                'amocrm_id' => $amoId,
            ]);
            $stats['funnels_created']++;
        }

        $funnel->load('stages');
        $sortOrder = 0;

        foreach ($statuses as $status) {
            $isUnsorted = $this->isUnsortedStatus($status);
            $isSystem = $this->isSystemClosedStatus($status);

            if ($isUnsorted && ! $includeUnsorted) {
                continue;
            }

            if ($isSystem && ! $includeSystem) {
                continue;
            }

            $sortOrder++;
            $this->upsertStage($funnel, $status, $sortOrder, $stats);
        }
    }

    /**
     * @param  array<string, mixed>  $status
     * @param  array{
     *     funnels_created: int,
     *     funnels_updated: int,
     *     stages_created: int,
     *     stages_updated: int
     * }  $stats
     */
    private function upsertStage(Funnel $funnel, array $status, int $sortOrder, array &$stats): void
    {
        $amoStatusId = (int) ($status['id'] ?? 0);
        $name = trim((string) ($status['name'] ?? ''));

        if ($amoStatusId <= 0 || $name === '') {
            return;
        }

        $color = $this->normalizeColor($status['color'] ?? null)
            ?: FunnelStageColors::defaultForIndex($sortOrder - 1);
        $kind = $this->resolveKind($status);

        /** @var FunnelStage|null $stage */
        $stage = $funnel->stages->first(
            fn (FunnelStage $item) => (int) $item->amocrm_id === $amoStatusId
        );

        $payload = [
            'name' => $name,
            'color' => $color,
            'kind' => $kind,
            'sort_order' => $sortOrder,
            'amocrm_id' => $amoStatusId,
        ];

        if ($stage) {
            $stage->update($payload);
            $stats['stages_updated']++;

            return;
        }

        $funnel->stages()->create([
            ...$payload,
            'action' => null,
            'comment' => null,
            'stage_type' => null,
            'deadline' => null,
            'outcomes' => [],
            'widgets' => [],
        ]);
        $stats['stages_created']++;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function resolveKind(array $status): string
    {
        $name = (string) ($status['name'] ?? '');

        if (AmoCrmMapper::isSuccessStageName($name)) {
            return 'win';
        }

        if (AmoCrmMapper::isLostStageName($name)) {
            return 'lose';
        }

        return 'normal';
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function isUnsortedStatus(array $status): bool
    {
        if ((int) ($status['type'] ?? 0) === 1) {
            return true;
        }

        $name = mb_strtolower(trim((string) ($status['name'] ?? '')));

        return str_contains($name, 'неразобран');
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function isSystemClosedStatus(array $status): bool
    {
        if ($this->isUnsortedStatus($status)) {
            return false;
        }

        $name = (string) ($status['name'] ?? '');

        if (AmoCrmMapper::isSuccessStageName($name) || AmoCrmMapper::isLostStageName($name)) {
            return true;
        }

        return ($status['is_editable'] ?? true) === false;
    }

    private function normalizeColor(mixed $color): ?string
    {
        if (! is_string($color) || $color === '') {
            return null;
        }

        $color = trim($color);

        if (! str_starts_with($color, '#')) {
            $color = '#'.$color;
        }

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return null;
        }

        return strtolower($color);
    }

    private function clientFor(Project $project): AmoCrmClient
    {
        $account = $project->amocrmAccount;

        if (! $account instanceof ProjectAmocrmAccount) {
            throw new AmoCrmException('Сначала подключите аккаунт amoCRM к проекту.');
        }

        return new AmoCrmClient($account);
    }
}
