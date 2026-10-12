<?php

namespace App\Services;

use App\Exceptions\AmoCrmException;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectAmocrmAccount;
use App\Models\ProjectSkkField;
use App\Support\AmoCrmMapper;

class AmoCrmExportPreviewService
{
    /**
     * @return array<string, mixed>
     */
    public function preview(Project $project): array
    {
        $account = $project->amocrmAccount;

        if (! $account instanceof ProjectAmocrmAccount) {
            throw new AmoCrmException('Сначала подключите аккаунт amoCRM к проекту.');
        }

        $client = new AmoCrmClient($account);
        $project->load(['funnels.stages', 'skkFields.funnelStage.funnel']);

        $pipelines = $client->getPipelines();
        $fieldsByEntity = $this->loadFieldsByEntity($client);

        $summary = [
            'pipelines_create' => 0,
            'pipelines_existing' => 0,
            'pipelines_conflicts' => 0,
            'stages_create' => 0,
            'stages_existing' => 0,
            'stages_conflicts' => 0,
            'fields_create' => 0,
            'fields_existing' => 0,
            'fields_conflicts' => 0,
        ];

        $funnelItems = [];

        foreach ($project->funnels as $funnel) {
            $funnelItems[] = $this->previewFunnel($funnel, $pipelines, $summary);
        }

        $fieldItems = [];

        foreach ($project->skkFields as $field) {
            $fieldItems[] = $this->previewField($field, $fieldsByEntity, $summary);
        }

        return [
            'account' => [
                'name' => $account->account_name,
                'subdomain' => $account->subdomain,
            ],
            'summary' => $summary,
            'funnels' => $funnelItems,
            'fields' => $fieldItems,
            'warnings' => $this->warnings($summary),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadFieldsByEntity(AmoCrmClient $client): array
    {
        $fields = [];

        foreach (AmoCrmMapper::ENTITY_MAP as $amoEntityType) {
            $fields[$amoEntityType] = $client->getCustomFields($amoEntityType);
        }

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $pipelines
     * @param  array<string, int>  $summary
     * @return array<string, mixed>
     */
    private function previewFunnel(Funnel $funnel, array $pipelines, array &$summary): array
    {
        $pipelineById = $this->findById($pipelines, $funnel->amocrm_id);
        $pipelineByName = $this->findByName($pipelines, $funnel->name);
        $matchedPipeline = $pipelineById ?? $pipelineByName;

        $status = 'create';
        $message = 'Будет создана новая воронка.';

        if ($pipelineById !== null) {
            $status = 'existing';
            $message = 'Воронка уже связана по ID.';
        } elseif ($funnel->amocrm_id) {
            $status = 'conflict';
            $message = 'Локальный amoCRM ID не найден. При выгрузке связь будет пересоздана.';
        } elseif ($pipelineByName !== null) {
            $status = 'conflict';
            $message = 'В amoCRM уже есть воронка с таким названием, но она не связана с планом.';
        }

        $summary['pipelines_'.$this->summaryBucket($status)]++;

        $embeddedStatuses = is_array($matchedPipeline)
            ? ($matchedPipeline['_embedded']['statuses'] ?? null)
            : null;
        $statuses = is_array($embeddedStatuses) ? $embeddedStatuses : [];

        $stageItems = [];

        foreach ($funnel->stages as $stage) {
            $stageItems[] = $this->previewStage($stage, $statuses, $summary);
        }

        return [
            'id' => $funnel->id,
            'name' => $funnel->name,
            'status' => $status,
            'message' => $message,
            'amo_id' => $funnel->amocrm_id,
            'amo_name' => $matchedPipeline['name'] ?? null,
            'stages' => $stageItems,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $statuses
     * @param  array<string, int>  $summary
     * @return array<string, mixed>
     */
    private function previewStage(FunnelStage $stage, array $statuses, array &$summary): array
    {
        $statusById = $this->findById($statuses, $stage->amocrm_id);
        $statusBySystem = $this->findSystemStage($statuses, $stage);
        $statusByName = $this->findByName($statuses, $stage->name);
        $matchedStatus = $statusById ?? $statusBySystem ?? $statusByName;

        $status = 'create';
        $message = 'Будет создан новый этап.';

        if ($statusById !== null || $statusBySystem !== null) {
            $status = 'existing';
            $message = 'Этап уже сопоставлен со статусом amoCRM.';
        } elseif ($stage->amocrm_id) {
            $status = 'conflict';
            $message = 'Локальный amoCRM ID этапа не найден.';
        } elseif ($statusByName !== null) {
            $status = 'conflict';
            $message = 'В amoCRM уже есть статус с таким названием.';
        }

        $summary['stages_'.$this->summaryBucket($status)]++;

        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'status' => $status,
            'message' => $message,
            'amo_id' => $stage->amocrm_id,
            'amo_name' => $matchedStatus['name'] ?? null,
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $fieldsByEntity
     * @param  array<string, int>  $summary
     * @return array<string, mixed>
     */
    private function previewField(ProjectSkkField $field, array $fieldsByEntity, array &$summary): array
    {
        $entityType = AmoCrmMapper::amoEntityType($field->entity_type);
        $amoFields = $entityType !== null ? ($fieldsByEntity[$entityType] ?? []) : [];
        $fieldById = $this->findById($amoFields, $field->amocrm_id);
        $fieldByName = $this->findByName($amoFields, $field->name);
        $matchedField = $fieldById ?? $fieldByName;

        $status = 'create';
        $message = 'Будет создано новое поле.';

        if ($entityType === null) {
            $status = 'conflict';
            $message = 'Тип сущности не поддерживается amoCRM.';
        } elseif ($fieldById !== null) {
            $status = 'existing';
            $message = 'Поле уже связано по ID.';
        } elseif ($field->amocrm_id) {
            $status = 'conflict';
            $message = 'Локальный amoCRM ID поля не найден.';
        } elseif ($fieldByName !== null) {
            $expectedType = AmoCrmMapper::amoFieldType($field->value_type);
            $amoType = (string) ($fieldByName['type'] ?? '');
            $status = $amoType === $expectedType ? 'existing' : 'conflict';
            $message = $amoType === $expectedType
                ? 'В amoCRM уже есть поле с таким названием и типом.'
                : "В amoCRM уже есть поле с таким названием, но тип отличается ({$amoType} вместо {$expectedType}).";
        }

        $summary['fields_'.$this->summaryBucket($status)]++;

        return [
            'id' => $field->id,
            'name' => $field->name,
            'entity_type' => $field->entity_type,
            'entity_label' => ProjectSkkField::TYPE_LABELS[$field->entity_type] ?? $field->entity_type,
            'value_type' => $field->value_type,
            'status' => $status,
            'message' => $message,
            'amo_id' => $field->amocrm_id,
            'amo_name' => $matchedField['name'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function findById(array $items, mixed $id): ?array
    {
        if (! $id) {
            return null;
        }

        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === (int) $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function findByName(array $items, string $name): ?array
    {
        $normalized = $this->normalizeName($name);

        foreach ($items as $item) {
            if ($this->normalizeName((string) ($item['name'] ?? '')) === $normalized) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $statuses
     */
    private function findSystemStage(array $statuses, FunnelStage $stage): ?array
    {
        $systemId = null;

        if (AmoCrmMapper::isSuccessStageName($stage->name)) {
            $systemId = 142;
        } elseif (AmoCrmMapper::isLostStageName($stage->name)) {
            $systemId = 143;
        }

        return $systemId === null ? null : $this->findById($statuses, $systemId);
    }

    private function summaryBucket(string $status): string
    {
        return $status === 'existing' ? 'existing' : ($status === 'conflict' ? 'conflicts' : 'create');
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
    }

    /**
     * @param  array<string, int>  $summary
     * @return list<string>
     */
    private function warnings(array $summary): array
    {
        $warnings = [];

        if (($summary['pipelines_conflicts'] + $summary['stages_conflicts'] + $summary['fields_conflicts']) > 0) {
            $warnings[] = 'Есть конфликты: проверьте совпадения по названию и устаревшие amoCRM ID перед выгрузкой.';
        }

        if (($summary['pipelines_create'] + $summary['stages_create'] + $summary['fields_create']) === 0) {
            $warnings[] = 'Новых сущностей для создания не найдено.';
        }

        return $warnings;
    }
}
