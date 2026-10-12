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

class AmoCrmExportService
{
    /**
     * @return array{
     *     pipelines_created: int,
     *     pipelines_skipped: int,
     *     stages_created: int,
     *     stages_skipped: int,
     *     tips_synced: int,
     *     fields_created: int,
     *     fields_skipped: int,
     *     errors: list<string>
     * }
     */
    /**
     * @param  array{
     *     funnel_ids?: list<int>|null,
     *     include_fields?: bool,
     *     field_ids?: list<int>|null
     * }  $options
     */
    public function export(Project $project, array $options = []): array
    {
        $account = $project->amocrmAccount;

        if (! $account instanceof ProjectAmocrmAccount) {
            throw new AmoCrmException('Сначала подключите аккаунт amoCRM к проекту.');
        }

        $client = new AmoCrmClient($account);

        $result = [
            'pipelines_created' => 0,
            'pipelines_skipped' => 0,
            'stages_created' => 0,
            'stages_skipped' => 0,
            'tips_synced' => 0,
            'fields_created' => 0,
            'fields_skipped' => 0,
            'errors' => [],
        ];

        $project->load(['funnels.stages', 'skkFields.funnelStage.funnel']);

        $funnelIds = array_key_exists('funnel_ids', $options) ? $options['funnel_ids'] : null;
        $includeFields = (bool) ($options['include_fields'] ?? true);
        $fieldIds = array_key_exists('field_ids', $options) ? $options['field_ids'] : null;

        $funnels = $project->funnels;

        if (is_array($funnelIds)) {
            $idSet = array_fill_keys(array_map('intval', $funnelIds), true);
            $funnels = $funnels->filter(fn (Funnel $funnel) => isset($idSet[(int) $funnel->id]))->values();
        }

        foreach ($funnels as $funnel) {
            try {
                $this->exportFunnel($client, $funnel, $result);
            } catch (AmoCrmException $exception) {
                $result['errors'][] = "Воронка «{$funnel->name}»: ".$exception->getMessage();
            }
        }

        if ($includeFields) {
            $project->load(['funnels.stages', 'skkFields.funnelStage.funnel']);

            $fields = $project->skkFields;

            if (is_array($fieldIds)) {
                $fieldSet = array_fill_keys(array_map('intval', $fieldIds), true);
                $fields = $fields->filter(fn (ProjectSkkField $field) => isset($fieldSet[(int) $field->id]))->values();
            }

            foreach ($fields as $field) {
                try {
                    $this->exportField($client, $field, $result);
                } catch (AmoCrmException $exception) {
                    $result['errors'][] = "Поле «{$field->name}»: ".$exception->getMessage();
                }
            }
        }

        $account->forceFill([
            'last_exported_at' => now(),
            'last_export_result' => $result,
        ])->save();

        return $result;
    }

    /**
     * @param  array{
     *     pipelines_created: int,
     *     pipelines_skipped: int,
     *     stages_created: int,
     *     stages_skipped: int,
     *     tips_synced: int,
     *     fields_created: int,
     *     fields_skipped: int,
     *     errors: list<string>
     * }  $result
     */
    private function exportFunnel(AmoCrmClient $client, Funnel $funnel, array &$result): void
    {
        if ($funnel->amocrm_id) {
            $pipelineId = (int) $funnel->amocrm_id;

            if ($client->pipelineExists($pipelineId)) {
                $result['pipelines_skipped']++;
                $result['stages_skipped'] += $funnel->stages->whereNotNull('amocrm_id')->count();

                $this->ensureStageAmoIds($client, $funnel, $result);

                $missingStages = $funnel->stages->whereNull('amocrm_id')->values();

                if ($missingStages->isNotEmpty()) {
                    $payload = $missingStages->map(
                        fn (FunnelStage $stage) => $this->statusPayload($stage, withDescriptions: false)
                    )->all();

                    $created = $client->createStatuses($pipelineId, $payload);
                    $this->mapStagesToAmoStatuses($funnel, $created, $result);
                    $funnel->load('stages');
                }

                $this->syncStages($client, $funnel, $result);

                if (! $funnel->fresh()?->amocrm_id) {
                    $funnel->refresh();
                    $funnel->load('stages');
                    $this->createFunnelInAmo($client, $funnel, $result);
                }

                return;
            }

            $this->clearFunnelSyncIds($funnel);
            $funnel->load('stages');
        }

        $this->createFunnelInAmo($client, $funnel, $result);
    }

    /**
     * @param  array{
     *     pipelines_created: int,
     *     pipelines_skipped: int,
     *     stages_created: int,
     *     stages_skipped: int,
     *     tips_synced: int,
     *     fields_created: int,
     *     fields_skipped: int,
     *     errors: list<string>
     * }  $result
     */
    private function createFunnelInAmo(AmoCrmClient $client, Funnel $funnel, array &$result): void
    {
        $statusesPayload = [];
        $hasSuccess = false;
        $hasLost = false;

        foreach ($funnel->stages as $stage) {
            if (AmoCrmMapper::isSuccessStageName($stage->name) && ! $hasSuccess) {
                $statusesPayload[] = $this->systemStatusPayload($stage, 142);
                $hasSuccess = true;

                continue;
            }

            if (AmoCrmMapper::isLostStageName($stage->name) && ! $hasLost) {
                $statusesPayload[] = $this->systemStatusPayload($stage, 143);
                $hasLost = true;

                continue;
            }

            // Без descriptions при create — имя/цвет/подсказки допишем PATCH'ем надёжнее.
            $statusesPayload[] = $this->statusPayload($stage, withDescriptions: false);
        }

        $pipelines = $client->createPipelines([[
            'name' => $funnel->name,
            'sort' => max(1, (int) $funnel->sort_order) * 10,
            'is_main' => false,
            'is_unsorted_on' => false,
            'request_id' => (string) $funnel->id,
            '_embedded' => [
                'statuses' => $statusesPayload,
            ],
        ]]);

        $createdPipeline = $pipelines[0] ?? null;

        if (! is_array($createdPipeline) || empty($createdPipeline['id'])) {
            throw new AmoCrmException('amoCRM не вернул ID созданной воронки.');
        }

        $pipelineId = (int) $createdPipeline['id'];

        $funnel->forceFill(['amocrm_id' => $pipelineId])->save();
        $result['pipelines_created']++;

        $statuses = $createdPipeline['_embedded']['statuses'] ?? [];
        $this->mapStagesToAmoStatuses($funnel, is_array($statuses) ? $statuses : [], $result);

        if ($funnel->stages()->whereNull('amocrm_id')->exists()) {
            $pipeline = $client->getPipeline($pipelineId);
            $fetched = $pipeline['_embedded']['statuses'] ?? [];
            $this->mapStagesToAmoStatuses($funnel, is_array($fetched) ? $fetched : [], $result);
        }

        $funnel->load('stages');
        $this->syncStages($client, $funnel, $result);
    }

    /**
     * @param  array{
     *     pipelines_created: int,
     *     pipelines_skipped: int,
     *     stages_created: int,
     *     stages_skipped: int,
     *     tips_synced: int,
     *     fields_created: int,
     *     fields_skipped: int,
     *     errors: list<string>
     * }  $result
     */
    private function ensureStageAmoIds(AmoCrmClient $client, Funnel $funnel, array &$result): void
    {
        if (! $funnel->amocrm_id || $funnel->stages->every(fn (FunnelStage $stage) => $stage->amocrm_id)) {
            return;
        }

        $pipeline = $client->getPipeline((int) $funnel->amocrm_id);
        $statuses = $pipeline['_embedded']['statuses'] ?? [];
        $this->mapStagesToAmoStatuses($funnel, is_array($statuses) ? $statuses : [], $result);
        $funnel->load('stages');
    }

    /**
     * Сопоставляет локальные этапы со статусами amo по request_id, затем по имени.
     *
     * @param  list<array<string, mixed>>  $statuses
     * @param  array{
     *     pipelines_created: int,
     *     pipelines_skipped: int,
     *     stages_created: int,
     *     stages_skipped: int,
     *     tips_synced: int,
     *     fields_created: int,
     *     fields_skipped: int,
     *     errors: list<string>
     * }  $result
     */
    private function mapStagesToAmoStatuses(Funnel $funnel, array $statuses, array &$result): void
    {
        $funnel->load('stages');
        $stages = $funnel->stages;
        $byLocalId = $stages->keyBy(fn (FunnelStage $stage) => (string) $stage->id);
        $usedStageIds = [];

        foreach ($statuses as $status) {
            if (! is_array($status) || empty($status['id'])) {
                continue;
            }

            // Неразобранное
            if ((int) ($status['type'] ?? 0) === 1) {
                continue;
            }

            $requestId = (string) ($status['request_id'] ?? '');
            $stage = $requestId !== '' ? $byLocalId->get($requestId) : null;

            if (! $stage instanceof FunnelStage) {
                $amoStatusId = (int) $status['id'];

                if ($amoStatusId === 142) {
                    $stage = $stages->first(function (FunnelStage $candidate) use ($usedStageIds) {
                        return ! in_array($candidate->id, $usedStageIds, true)
                            && AmoCrmMapper::isSuccessStageName($candidate->name);
                    });
                } elseif ($amoStatusId === 143) {
                    $stage = $stages->first(function (FunnelStage $candidate) use ($usedStageIds) {
                        return ! in_array($candidate->id, $usedStageIds, true)
                            && AmoCrmMapper::isLostStageName($candidate->name);
                    });
                }
            }

            if (! $stage instanceof FunnelStage) {
                $statusName = mb_strtolower(trim((string) ($status['name'] ?? '')));

                $stage = $stages->first(function (FunnelStage $candidate) use ($statusName, $usedStageIds) {
                    if (in_array($candidate->id, $usedStageIds, true)) {
                        return false;
                    }

                    return mb_strtolower(trim($candidate->name)) === $statusName;
                });
            }

            if (! $stage instanceof FunnelStage) {
                continue;
            }

            $usedStageIds[] = $stage->id;
            $amoId = (int) $status['id'];

            if ((int) $stage->amocrm_id !== $amoId) {
                $stage->forceFill(['amocrm_id' => $amoId])->save();
                $result['stages_created']++;
            }
        }
    }

    private function clearFunnelSyncIds(Funnel $funnel): void
    {
        DB::transaction(function () use ($funnel) {
            $funnel->forceFill(['amocrm_id' => null])->save();
            FunnelStage::query()->where('funnel_id', $funnel->id)->update(['amocrm_id' => null]);
        });

        $funnel->amocrm_id = null;
        $funnel->unsetRelation('stages');
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(FunnelStage $stage, bool $withDescriptions = false): array
    {
        $payload = [
            'name' => $stage->name,
            'sort' => AmoCrmMapper::stageSort((int) $stage->sort_order),
            'color' => AmoCrmMapper::nearestStatusColor(
                FunnelStageColors::resolve($stage->color, (int) $stage->sort_order)
            ),
            'request_id' => (string) $stage->id,
        ];

        if ($withDescriptions) {
            $descriptions = AmoCrmMapper::stageDescriptions(
                $stage->action,
                $stage->comment,
                $stage->stage_type,
                $stage->deadline
            );

            if ($descriptions !== null) {
                $payload['descriptions'] = $descriptions;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function systemStatusPayload(FunnelStage $stage, int $systemId): array
    {
        return [
            'id' => $systemId,
            'name' => $stage->name,
            'request_id' => (string) $stage->id,
        ];
    }

    /**
     * Синхронизирует название, цвет, порядок и подсказки этапа в amoCRM.
     *
     * @param  array{
     *     pipelines_created: int,
     *     pipelines_skipped: int,
     *     stages_created: int,
     *     stages_skipped: int,
     *     tips_synced: int,
     *     fields_created: int,
     *     fields_skipped: int,
     *     errors: list<string>
     * }  $result
     */
    private function syncStages(AmoCrmClient $client, Funnel $funnel, array &$result): void
    {
        if (! $funnel->amocrm_id) {
            return;
        }

        $pipelineId = (int) $funnel->amocrm_id;

        foreach ($funnel->stages as $stage) {
            if (! $stage->amocrm_id) {
                $result['errors'][] = "Этап «{$stage->name}»: не найден ID статуса в amoCRM.";

                continue;
            }

            $amoStatusId = (int) $stage->amocrm_id;

            // Системные успех/неуспех в amoCRM нередактируемы (is_editable=false) — PATCH всегда 400.
            if (in_array($amoStatusId, [142, 143], true)) {
                continue;
            }

            $payload = [
                'name' => $stage->name,
                'sort' => AmoCrmMapper::stageSort((int) $stage->sort_order),
                'color' => AmoCrmMapper::nearestStatusColor(
                    FunnelStageColors::resolve($stage->color, (int) $stage->sort_order)
                ),
            ];

            $descriptions = AmoCrmMapper::stageDescriptions(
                $stage->action,
                $stage->comment,
                $stage->stage_type,
                $stage->deadline
            );

            if ($descriptions !== null) {
                $payload['descriptions'] = $descriptions;
            }

            try {
                $client->updateStatus($pipelineId, $amoStatusId, $payload);
                $result['tips_synced']++;
            } catch (AmoCrmException $exception) {
                if ($exception->isNotFound()) {
                    $this->clearFunnelSyncIds($funnel);

                    return;
                }

                $result['errors'][] = "Этап «{$stage->name}»: ".$exception->getMessage();
            }
        }
    }

    /**
     * @param  array{
     *     pipelines_created: int,
     *     pipelines_skipped: int,
     *     stages_created: int,
     *     stages_skipped: int,
     *     tips_synced: int,
     *     fields_created: int,
     *     fields_skipped: int,
     *     errors: list<string>
     * }  $result
     */
    private function exportField(AmoCrmClient $client, ProjectSkkField $field, array &$result): void
    {
        if ($field->amocrm_id) {
            $result['fields_skipped']++;

            return;
        }

        $entityType = AmoCrmMapper::amoEntityType($field->entity_type);

        if ($entityType === null) {
            throw new AmoCrmException('Неподдерживаемый тип сущности: '.$field->entity_type);
        }

        $payload = [
            'name' => $field->name,
            'type' => AmoCrmMapper::amoFieldType($field->value_type),
            'sort' => max(1, (int) $field->sort_order),
            'is_api_only' => false,
            'request_id' => (string) $field->id,
        ];

        if (in_array($field->value_type, [ProjectSkkField::VALUE_SELECT, ProjectSkkField::VALUE_MULTISELECT], true)) {
            $options = $field->options ?? [];

            if ($options === []) {
                throw new AmoCrmException('У списочного поля нет вариантов.');
            }

            $payload['enums'] = collect($options)->values()->map(fn (string $option, int $index) => [
                'value' => $option,
                'sort' => ($index + 1) * 10,
            ])->all();
        }

        if ($field->is_required && $field->funnelStage?->amocrm_id && $field->funnelStage->funnel?->amocrm_id) {
            $payload['required_statuses'] = [[
                'status_id' => (int) $field->funnelStage->amocrm_id,
                'pipeline_id' => (int) $field->funnelStage->funnel->amocrm_id,
            ]];
        }

        $created = $client->createCustomFields($entityType, [$payload]);
        $createdField = $created[0] ?? null;

        if (! is_array($createdField) || empty($createdField['id'])) {
            throw new AmoCrmException('amoCRM не вернул ID созданного поля.');
        }

        $field->forceFill(['amocrm_id' => (int) $createdField['id']])->save();
        $result['fields_created']++;
    }
}
