<?php

namespace App\Services;

use App\Models\Project;
use App\Support\F5AiModels;
use App\Support\WorkStageCostPricing;

class ProjectAiKpWorkStagesService
{
    public function __construct(
        private readonly F5AiService $f5AiService,
        private readonly ProjectAiContextBuilder $contextBuilder,
        private readonly ProjectAiChangesParser $changesParser
    ) {}

    /**
     * @param  callable(string): void|null  $onProgress
     * @return array{report: string, changes: array<string, mixed>|null}
     */
    public function process(
        Project $project,
        string $kpText,
        ?callable $onProgress = null,
        ?int $preferredUserId = null
    ): array {
        $onProgress ??= static function (): void {};
        $timeout = (int) config('f5ai.timeout_kp_work_stages', 300);

        $onProgress('Читаю коммерческое предложение…');

        $project->loadMissing('workStages');
        $context = $this->contextBuilder->build($project);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);

        $existingIds = $project->workStages->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $existingIdsHint = $existingIds === [] ? 'список пуст' : implode(', ', $existingIds);
        $existingJson = json_encode(
            $project->workStages->map(fn ($stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'composition' => $stage->composition,
                'cost' => $stage->cost,
                'duration_days' => $stage->duration_days,
            ])->values()->all(),
            JSON_UNESCAPED_UNICODE
        );

        $workStageCostRules = WorkStageCostPricing::promptRules();
        $exampleHourCost = WorkStageCostPricing::hourlyRate() * 5;

        $systemPrompt = <<<PROMPT
Ты — коммерческий и проектный аналитик команды F5. По тексту коммерческого предложения (КП) клиента/подрядчика нужно извлечь **этапы работ** для блока «Этапы работ» в плане внедрения amoCRM.

Текущий проект (JSON):
{$contextJson}

Уже существующие этапы работ в проекте:
{$existingJson}

Задача:
1. Найди в КП разделы с этапами работ / составом работ / стоимостью этапов / сроками реализации / календарным планом внедрения.
2. Для каждого этапа извлеки: название, состав работ (что входит), стоимость в рублях (число без пробелов и «₽», или null если нет), срок реализации в рабочих днях (целое число или null если нет).
3. Игнорируй воронки CRM, поля СКК, виджеты — только этапы работ коммерческого плана.
4. Сохрани порядок этапов как в КП.
5. Если в проекте уже есть этапы — в delete перечисли их id ({$existingIdsHint}), а в create — полный актуальный список из КП (замена по согласованию пользователя).
6. Если этапов в КП нет — changes = null и честно напиши об этом в message.

Ответь ТОЛЬКО валидным JSON без markdown:
{
  "message": "Краткий отчёт на русском: сколько этапов найдено, откуда в КП, итоговая сумма и сроки если есть",
  "changes": {
    "work_stages": {
      "create": [
        {"name": "Название этапа", "composition": "1) Первый пункт состава\\n2) Второй пункт состава", "cost": {$exampleHourCost}, "duration_days": 10}
      ],
      "update": [],
      "delete": []
    }
  }
}

{$workStageCostRules}

Правила:
- name обязателен и короткий.
- composition — подробный состав из КП: каждый пункт с новой строки и с нумерацией (1) …\\n2) …). Не пиши все пункты в одну строку через запятую.
- cost — число или null. Если в КП указана сумма — бери её как есть. Если указаны только часы без суммы — умножай на ставку из правил выше.
- duration_days — срок реализации в рабочих днях (целое число ≥ 1) или null. Если в КП указаны календарные дни / недели / месяцы — переведи в рабочие дни (неделя ≈ 5 раб. дней), не выдумывай срок без основания в тексте.
- Не выдумывай этапы, которых нет в КП.
- Не используй ключ "stages" (это этапы воронок CRM). Только "work_stages".
PROMPT;

        $onProgress('Извлекаю этапы работ из КП…');

        $raw = $this->f5AiService->chatCompletion(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Текст коммерческого предложения (КП):\n\n{$kpText}"],
            ],
            [
                'user' => F5AiModels::resolveUser($project, $preferredUserId),
                'temperature' => 0.2,
                'timeout' => $timeout,
            ]
        );

        $parsed = $this->changesParser->parse(
            $raw,
            'ИИ вернул ответ по КП в неверном формате. Попробуйте загрузить документ снова.'
        );

        $changes = $parsed['changes'];

        if (is_array($changes) && ! $this->hasWorkStageOps($changes)) {
            $changes = null;
        }

        $report = trim($parsed['message']);

        if ($report === '') {
            $report = $changes
                ? 'Этапы работ из КП подготовлены.'
                : 'В КП не удалось найти этапы работ.';
        }

        return [
            'report' => $report,
            'changes' => $changes,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function hasWorkStageOps(array $changes): bool
    {
        $section = $changes['work_stages'] ?? null;

        if (! is_array($section)) {
            return false;
        }

        foreach (['create', 'update', 'delete'] as $action) {
            if (! empty($section[$action])) {
                return true;
            }
        }

        return false;
    }
}
