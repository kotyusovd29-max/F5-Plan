<?php

namespace App\Services;

use App\Exceptions\AmoCrmException;
use App\Exceptions\F5AiException;
use App\Models\Project;
use App\Models\ProjectAmocrmAccount;
use App\Support\F5AiModels;

class AmoCrmAiSeedService
{
    public const MAX_ENTITIES = 50;

    public const DEMO_TAG = 'F5 demo';

    public function __construct(
        private readonly F5AiService $f5AiService
    ) {}

    /**
     * @return array{
     *     message: string,
     *     contacts: list<array<string, mixed>>,
     *     leads: list<array<string, mixed>>,
     *     links: list<array<string, mixed>>,
     *     summary: array{contacts: int, leads: int, links: int, fields_used: list<string>},
     *     payload: array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>, links: list<array<string, mixed>>}
     * }
     */
    public function preview(Project $project, string $prompt, ?int $userId = null): array
    {
        $account = $this->requireAccount($project);
        $client = new AmoCrmClient($account);

        $fieldCatalog = $this->buildFieldCatalog($client);
        $pipelines = $this->mapPipelinesBrief($client->getPipelines());
        $contactPool = $this->mapContactsBrief($client->getContacts(self::MAX_ENTITIES));

        $raw = $this->askAi($project, $prompt, $fieldCatalog, $pipelines, $contactPool, $userId);
        $normalized = $this->buildNormalizedPlan($client, $raw, $fieldCatalog, $pipelines, $contactPool);

        return $this->presentPreview($normalized, $raw['message'] ?? null);
    }

    /**
     * @param  array{
     *     contacts?: list<array<string, mixed>>,
     *     leads?: list<array<string, mixed>>,
     *     links?: list<array<string, mixed>>
     * }  $payload
     * @return array{
     *     contacts_created: int,
     *     leads_created: int,
     *     links_created: int,
     *     contact_ids: list<int>,
     *     lead_ids: list<int>,
     *     errors: list<string>
     * }
     */
    public function apply(Project $project, array $payload): array
    {
        $account = $this->requireAccount($project);
        $client = new AmoCrmClient($account);
        $fieldCatalog = $this->buildFieldCatalog($client);

        $contactsIn = is_array($payload['contacts'] ?? null) ? $payload['contacts'] : [];
        $leadsIn = is_array($payload['leads'] ?? null) ? $payload['leads'] : [];
        $linksIn = is_array($payload['links'] ?? null) ? $payload['links'] : [];

        $normalizedContacts = [];
        $contactFields = $this->indexFields($fieldCatalog['contacts'] ?? []);
        foreach (array_slice($contactsIn, 0, self::MAX_ENTITIES) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $built = $this->buildContactPayload($row, $contactFields);
            if ($built !== null) {
                $normalizedContacts[] = $built;
            }
        }

        $normalizedLeads = [];
        $leadFields = $this->indexFields($fieldCatalog['leads'] ?? []);
        foreach (array_slice($leadsIn, 0, self::MAX_ENTITIES) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $built = $this->buildLeadPayload($row, $leadFields);
            if ($built !== null) {
                $normalizedLeads[] = $built;
            }
        }

        $normalizedLinks = $this->normalizeLinksPayload($linksIn);

        if ($normalizedContacts === [] && $normalizedLeads === [] && $normalizedLinks === []) {
            throw new AmoCrmException('Нет данных для создания в amoCRM. Сначала получите предпросмотр.');
        }

        $result = [
            'contacts_created' => 0,
            'leads_created' => 0,
            'links_created' => 0,
            'contact_ids' => [],
            'lead_ids' => [],
            'errors' => [],
        ];

        try {
            if ($normalizedContacts !== []) {
                $created = $client->createContacts(
                    array_map(fn (array $row) => $this->stripPreviewMeta($row), $normalizedContacts)
                );
                $result['contacts_created'] = count($created);
                $result['contact_ids'] = collect($created)
                    ->map(fn ($row) => (int) ($row['id'] ?? 0))
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();
            }
        } catch (AmoCrmException $exception) {
            $result['errors'][] = 'Контакты: '.$exception->getMessage();
        }

        try {
            if ($normalizedLeads !== []) {
                $created = $client->createLeads(
                    array_map(fn (array $row) => $this->stripPreviewMeta($row), $normalizedLeads)
                );
                $result['leads_created'] = count($created);
                $result['lead_ids'] = collect($created)
                    ->map(fn ($row) => (int) ($row['id'] ?? 0))
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();
            }
        } catch (AmoCrmException $exception) {
            $result['errors'][] = 'Сделки: '.$exception->getMessage();
        }

        foreach ($normalizedLinks as $link) {
            try {
                $client->linkLeadToContacts((int) $link['lead_id'], [(int) $link['contact_id']]);
                $result['links_created']++;
            } catch (AmoCrmException $exception) {
                $result['errors'][] = 'Связь сделки #'.$link['lead_id'].': '.$exception->getMessage();
            }
        }

        if (
            $result['contacts_created'] === 0
            && $result['leads_created'] === 0
            && $result['links_created'] === 0
        ) {
            throw new AmoCrmException(
                $result['errors'][0] ?? 'Не удалось создать данные в amoCRM.'
            );
        }

        return $result;
    }

    /**
     * @return list<array{id: string, label: string, message: string}>
     */
    public static function templates(): array
    {
        return [
            [
                'id' => 'contacts_20_birthday',
                'label' => '20 контактов + дата рождения',
                'message' => 'Создай в amo 20 разных контактов. В каждом заполни имя, телефон, email и поле «Дата рождения» (если оно есть). Данные тестовые, на русском.',
            ],
            [
                'id' => 'leads_with_contacts',
                'label' => 'Сделки + контакты из amo',
                'message' => 'Создай 50 сделок в воронке Продажа украшений на этапе Новая заявка. В каждую сделку прикрепи существующие контакты из amoCRM.',
            ],
            [
                'id' => 'link_missing_contacts',
                'label' => 'Прикрепить контакты к сделкам без них',
                'message' => 'Есть сделки на этапе Новая заявка. Во все сделки, в которых нет контактов, нужно их прикрепить. Контакты бери из базы amoCRM.',
            ],
            [
                'id' => 'contacts_10_full',
                'label' => '10 контактов со всеми полями',
                'message' => 'Создай 10 разных тестовых контактов и заполни максимум доступных полей контакта из каталога (телефон, email, дата рождения и другие).',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function stripPreviewMeta(array $row): array
    {
        unset($row['_preview']);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAiJson(string $content): array
    {
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
                $decoded = json_decode($matches[0], true);
            }
        }

        if (! is_array($decoded)) {
            throw new F5AiException('ИИ вернул ответ в неожиданном формате. Попробуйте переформулировать запрос.');
        }

        return $decoded;
    }

    private function requireAccount(Project $project): ProjectAmocrmAccount
    {
        $account = $project->amocrmAccount;

        if (! $account instanceof ProjectAmocrmAccount) {
            throw new AmoCrmException('Сначала подключите аккаунт amoCRM к проекту.');
        }

        return $account;
    }

    /**
     * @return array{
     *     contacts: list<array{id: int, name: string, type: string, code: ?string, enums: list<string>}>,
     *     leads: list<array{id: int, name: string, type: string, code: ?string, enums: list<string>}>
     * }
     */
    private function buildFieldCatalog(AmoCrmClient $client): array
    {
        return [
            'contacts' => $this->mapFields($client->getCustomFields('contacts')),
            'leads' => $this->mapFields($client->getCustomFields('leads')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array{id: int, name: string, type: string, code: ?string, enums: list<string>}>
     */
    private function mapFields(array $fields): array
    {
        return collect($fields)
            ->filter(fn ($field) => is_array($field) && (int) ($field['id'] ?? 0) > 0)
            ->map(function (array $field) {
                $enums = collect($field['enums'] ?? [])
                    ->map(fn ($enum) => is_array($enum) ? trim((string) ($enum['value'] ?? '')) : '')
                    ->filter()
                    ->take(30)
                    ->values()
                    ->all();

                return [
                    'id' => (int) $field['id'],
                    'name' => trim((string) ($field['name'] ?? '')),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'code' => isset($field['code']) && is_string($field['code']) && $field['code'] !== ''
                        ? $field['code']
                        : null,
                    'enums' => $enums,
                ];
            })
            ->filter(fn (array $field) => $field['name'] !== '')
            ->take(120)
            ->values()
            ->all();
    }

    /**
     * @param  array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>}  $fieldCatalog
     * @param  list<array{id: int, name: string, statuses: list<array{id: int, name: string}>}>  $pipelines
     * @param  list<array{id: int, name: string}>  $contactPool
     * @return array<string, mixed>
     */
    private function askAi(
        Project $project,
        string $prompt,
        array $fieldCatalog,
        array $pipelines,
        array $contactPool,
        ?int $userId
    ): array {
        $catalogJson = json_encode($fieldCatalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $pipelinesJson = json_encode($pipelines, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $contactsJson = json_encode($contactPool, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $max = self::MAX_ENTITIES;
        $tag = self::DEMO_TAG;
        $contactsCount = count($contactPool);

        $system = <<<PROMPT
Ты помощник F5 для работы с amoCRM. Разбери запрос пользователя и верни ТОЛЬКО JSON без markdown.

Формат ответа:
{
  "message": "Кратко что будет сделано",
  "intent": "create_contacts|create_leads|link_contacts|mixed",
  "contacts": [
    {"name":"Имя","phone":"+79001234567","email":"a@example.com","fields":{"Дата рождения":"1990-05-12"}}
  ],
  "create_leads": {
    "count": 10,
    "pipeline": "Название воронки из списка",
    "status": "Название этапа из списка",
    "attach_existing_contacts": true,
    "leads": [{"name":"Сделка 1","price":15000}]
  },
  "link_contacts": {
    "pipeline": "Название воронки",
    "status": "Название этапа",
    "only_without_contacts": true,
    "max": 50
  },
  "leads": []
}

Правила выбора intent:
- Создать новые контакты → intent=create_contacts, заполни contacts.
- Создать сделки (в воронке/этапе) и прикрепить существующие контакты из amo → intent=create_leads, заполни create_leads (attach_existing_contacts=true). Не выдумывай contact_id.
- Прикрепить контакты из amo к уже существующим сделкам без контактов → intent=link_contacts, заполни link_contacts.
- Можно комбинировать (mixed).

Ограничения:
- Максимум {$max} сущностей/связей за раз.
- pipeline/status бери ТОЛЬКО из списка воронок ниже (имена как в списке, можно близкие по смыслу).
- Для создания контактов: вымышленные данные, русский язык; метку {$tag} система добавит сама.
- fields — только имена из каталога полей.
- Если просят контакты из базы amo — НЕ создавай новые contacts, используй create_leads/link_contacts.
- В amo сейчас доступно контактов для привязки: {$contactsCount}.

Воронки и этапы amoCRM:
{$pipelinesJson}

Контакты amoCRM (id/name, можно использовать только через attach/link на сервере):
{$contactsJson}

Каталог полей:
{$catalogJson}
PROMPT;

        $content = $this->f5AiService->chatCompletion([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => trim($prompt)],
        ], [
            'user' => F5AiModels::resolveUser($project, $userId),
            'temperature' => 0.35,
            'timeout' => (int) config('f5ai.timeout', 300),
        ]);

        return $this->decodeAiJson($content);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>}  $fieldCatalog
     * @param  list<array{id: int, name: string, statuses: list<array{id: int, name: string}>}>  $pipelines
     * @param  list<array{id: int, name: string}>  $contactPool
     * @return array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>, links: list<array<string, mixed>>}
     */
    private function buildNormalizedPlan(
        AmoCrmClient $client,
        array $raw,
        array $fieldCatalog,
        array $pipelines,
        array $contactPool
    ): array {
        $base = $this->normalizeSeedPayload($raw, $fieldCatalog);
        $links = [];

        $createLeads = is_array($raw['create_leads'] ?? null) ? $raw['create_leads'] : null;
        $linkContacts = is_array($raw['link_contacts'] ?? null) ? $raw['link_contacts'] : null;

        if ($createLeads !== null) {
            $built = $this->buildCreateLeadsFromPlan($createLeads, $pipelines, $contactPool, $fieldCatalog);
            $base['leads'] = array_slice(
                array_merge($base['leads'], $built),
                0,
                self::MAX_ENTITIES
            );
        }

        if ($linkContacts !== null) {
            $links = $this->buildLinksFromPlan($client, $linkContacts, $pipelines, $contactPool);
        }

        return [
            'contacts' => $base['contacts'],
            'leads' => $base['leads'],
            'links' => $links,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array{id: int, name: string, statuses: list<array{id: int, name: string}>}>  $pipelines
     * @param  list<array{id: int, name: string}>  $contactPool
     * @param  array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>}  $fieldCatalog
     * @return list<array<string, mixed>>
     */
    private function buildCreateLeadsFromPlan(
        array $plan,
        array $pipelines,
        array $contactPool,
        array $fieldCatalog
    ): array {
        $resolved = $this->resolvePipelineStatus(
            $pipelines,
            (string) ($plan['pipeline'] ?? ''),
            (string) ($plan['status'] ?? '')
        );

        if ($resolved === null) {
            throw new AmoCrmException(
                'Не удалось найти воронку/этап из запроса. Укажите точные названия из amoCRM.'
            );
        }

        $count = (int) ($plan['count'] ?? 0);
        $explicit = is_array($plan['leads'] ?? null) ? $plan['leads'] : [];
        if ($count <= 0) {
            $count = max(count($explicit), 1);
        }
        $count = min(self::MAX_ENTITIES, max(1, $count));

        $attach = (bool) ($plan['attach_existing_contacts'] ?? true);
        if ($attach && $contactPool === []) {
            throw new AmoCrmException(
                'В amoCRM нет контактов для привязки. Сначала создайте контакты или уберите требование прикреплять существующие.'
            );
        }

        $leadFields = $this->indexFields($fieldCatalog['leads'] ?? []);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $row = is_array($explicit[$i] ?? null) ? $explicit[$i] : [];
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Сделка '.($i + 1);
            }

            $built = $this->buildLeadPayload([
                ...$row,
                'name' => $name,
                'pipeline_id' => $resolved['pipeline_id'],
                'status_id' => $resolved['status_id'],
            ], $leadFields);

            if ($built === null) {
                continue;
            }

            $built['pipeline_id'] = $resolved['pipeline_id'];
            $built['status_id'] = $resolved['status_id'];
            $built['_preview']['pipeline'] = $resolved['pipeline_name'];
            $built['_preview']['status'] = $resolved['status_name'];

            if ($attach) {
                $contact = $contactPool[$i % count($contactPool)];
                $contactId = (int) $contact['id'];
                $built['_embedded'] = [
                    'contacts' => [['id' => $contactId]],
                ];
                $built['_preview']['contact_id'] = $contactId;
                $built['_preview']['contact_name'] = (string) ($contact['name'] ?? '');
            }

            $result[] = $built;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  list<array{id: int, name: string, statuses: list<array{id: int, name: string}>}>  $pipelines
     * @param  list<array{id: int, name: string}>  $contactPool
     * @return list<array{lead_id: int, lead_name: string, contact_id: int, contact_name: string}>
     */
    private function buildLinksFromPlan(
        AmoCrmClient $client,
        array $plan,
        array $pipelines,
        array $contactPool
    ): array {
        $resolved = $this->resolvePipelineStatus(
            $pipelines,
            (string) ($plan['pipeline'] ?? ''),
            (string) ($plan['status'] ?? '')
        );

        if ($resolved === null) {
            // Если этап указан без воронки — ищем этап по имени во всех воронках.
            $resolved = $this->resolveStatusAnywhere($pipelines, (string) ($plan['status'] ?? ''));
        }

        if ($resolved === null) {
            throw new AmoCrmException(
                'Не удалось найти этап из запроса. Укажите название этапа (и воронки) как в amoCRM.'
            );
        }

        if ($contactPool === []) {
            throw new AmoCrmException('В amoCRM нет контактов для привязки.');
        }

        $max = min(self::MAX_ENTITIES, max(1, (int) ($plan['max'] ?? self::MAX_ENTITIES)));
        $onlyWithout = (bool) ($plan['only_without_contacts'] ?? true);

        $leads = $client->getLeadsByStatus(
            $resolved['pipeline_id'],
            $resolved['status_id'],
            $max,
            true
        );

        $targets = [];
        foreach ($leads as $lead) {
            $leadId = (int) ($lead['id'] ?? 0);
            if ($leadId <= 0) {
                continue;
            }

            $embeddedContacts = $lead['_embedded']['contacts'] ?? [];
            $hasContacts = is_array($embeddedContacts) && $embeddedContacts !== [];

            if ($onlyWithout && $hasContacts) {
                continue;
            }

            $targets[] = [
                'id' => $leadId,
                'name' => (string) ($lead['name'] ?? ('Сделка #'.$leadId)),
            ];

            if (count($targets) >= $max) {
                break;
            }
        }

        if ($targets === []) {
            throw new AmoCrmException(
                'На этапе «'.$resolved['status_name'].'» нет сделок без контактов (или сделок вообще нет).'
            );
        }

        $links = [];
        foreach ($targets as $index => $lead) {
            $contact = $contactPool[$index % count($contactPool)];
            $links[] = [
                'lead_id' => $lead['id'],
                'lead_name' => $lead['name'],
                'contact_id' => (int) $contact['id'],
                'contact_name' => (string) ($contact['name'] ?? ''),
            ];
        }

        return $links;
    }

    /**
     * @param  list<array{id: int, name: string, statuses: list<array{id: int, name: string}>}>  $pipelines
     * @return array{pipeline_id: int, pipeline_name: string, status_id: int, status_name: string}|null
     */
    private function resolvePipelineStatus(array $pipelines, string $pipelineName, string $statusName): ?array
    {
        $pipelineName = trim($pipelineName);
        $statusName = trim($statusName);

        if ($pipelineName === '' || $statusName === '') {
            return null;
        }

        $pipeline = $this->findByName($pipelines, $pipelineName);
        if ($pipeline === null) {
            return null;
        }

        $status = $this->findByName($pipeline['statuses'] ?? [], $statusName);
        if ($status === null) {
            return null;
        }

        return [
            'pipeline_id' => (int) $pipeline['id'],
            'pipeline_name' => (string) $pipeline['name'],
            'status_id' => (int) $status['id'],
            'status_name' => (string) $status['name'],
        ];
    }

    /**
     * @param  list<array{id: int, name: string, statuses: list<array{id: int, name: string}>}>  $pipelines
     * @return array{pipeline_id: int, pipeline_name: string, status_id: int, status_name: string}|null
     */
    private function resolveStatusAnywhere(array $pipelines, string $statusName): ?array
    {
        $statusName = trim($statusName);
        if ($statusName === '') {
            return null;
        }

        foreach ($pipelines as $pipeline) {
            $status = $this->findByName($pipeline['statuses'] ?? [], $statusName);
            if ($status !== null) {
                return [
                    'pipeline_id' => (int) $pipeline['id'],
                    'pipeline_name' => (string) $pipeline['name'],
                    'status_id' => (int) $status['id'],
                    'status_name' => (string) $status['name'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, name: string}>  $items
     * @return array{id: int, name: string}|null
     */
    private function findByName(array $items, string $name): ?array
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (mb_strtolower(trim((string) ($item['name'] ?? ''))) === $needle) {
                return $item;
            }
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $hay = mb_strtolower(trim((string) ($item['name'] ?? '')));
            if ($hay !== '' && (str_contains($hay, $needle) || str_contains($needle, $hay))) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $pipelines
     * @return list<array{id: int, name: string, statuses: list<array{id: int, name: string}>}>
     */
    private function mapPipelinesBrief(array $pipelines): array
    {
        $result = [];

        foreach ($pipelines as $pipeline) {
            if (! is_array($pipeline)) {
                continue;
            }

            $id = (int) ($pipeline['id'] ?? 0);
            $name = trim((string) ($pipeline['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }

            $statuses = [];
            foreach ($pipeline['_embedded']['statuses'] ?? [] as $status) {
                if (! is_array($status)) {
                    continue;
                }
                $sid = (int) ($status['id'] ?? 0);
                $sname = trim((string) ($status['name'] ?? ''));
                if ($sid > 0 && $sname !== '') {
                    $statuses[] = ['id' => $sid, 'name' => $sname];
                }
            }

            $result[] = [
                'id' => $id,
                'name' => $name,
                'statuses' => $statuses,
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $contacts
     * @return list<array{id: int, name: string}>
     */
    private function mapContactsBrief(array $contacts): array
    {
        return collect($contacts)
            ->filter(fn ($row) => is_array($row) && (int) ($row['id'] ?? 0) > 0)
            ->map(fn (array $row) => [
                'id' => (int) $row['id'],
                'name' => trim((string) ($row['name'] ?? ('Контакт #'.$row['id']))),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array{lead_id: int, contact_id: int, lead_name?: string, contact_name?: string}>
     */
    private function normalizeLinksPayload(array $links): array
    {
        $result = [];

        foreach (array_slice($links, 0, self::MAX_ENTITIES) as $link) {
            if (! is_array($link)) {
                continue;
            }

            $leadId = (int) ($link['lead_id'] ?? 0);
            $contactId = (int) ($link['contact_id'] ?? 0);

            if ($leadId <= 0 || $contactId <= 0) {
                continue;
            }

            $result[] = [
                'lead_id' => $leadId,
                'contact_id' => $contactId,
                'lead_name' => (string) ($link['lead_name'] ?? ''),
                'contact_name' => (string) ($link['contact_name'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>}  $fieldCatalog
     * @return array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>}
     */
    private function normalizeSeedPayload(array $raw, array $fieldCatalog): array
    {
        $contactFields = $this->indexFields($fieldCatalog['contacts'] ?? []);
        $leadFields = $this->indexFields($fieldCatalog['leads'] ?? []);

        $contacts = [];
        foreach (array_slice(is_array($raw['contacts'] ?? null) ? $raw['contacts'] : [], 0, self::MAX_ENTITIES) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $built = $this->buildContactPayload($row, $contactFields);

            if ($built !== null) {
                $contacts[] = $built;
            }
        }

        $remaining = self::MAX_ENTITIES - count($contacts);
        $leads = [];

        // Старый формат leads: [] — только если нет create_leads (его обрабатывает buildNormalizedPlan).
        if ($remaining > 0 && ! is_array($raw['create_leads'] ?? null)) {
            foreach (array_slice(is_array($raw['leads'] ?? null) ? $raw['leads'] : [], 0, $remaining) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $built = $this->buildLeadPayload($row, $leadFields);

                if ($built !== null) {
                    $leads[] = $built;
                }
            }
        }

        return [
            'contacts' => $contacts,
            'leads' => $leads,
        ];
    }

    /**
     * @param  list<array{id: int, name: string, type: string, code: ?string, enums: list<string>}>  $fields
     * @return array{by_id: array<int, array<string, mixed>>, by_name: array<string, array<string, mixed>>, by_code: array<string, array<string, mixed>>}
     */
    private function indexFields(array $fields): array
    {
        $byId = [];
        $byName = [];
        $byCode = [];

        foreach ($fields as $field) {
            $byId[(int) $field['id']] = $field;
            $byName[mb_strtolower(trim((string) $field['name']))] = $field;

            if (! empty($field['code'])) {
                $byCode[mb_strtoupper((string) $field['code'])] = $field;
            }
        }

        return [
            'by_id' => $byId,
            'by_name' => $byName,
            'by_code' => $byCode,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{by_id: array<int, array<string, mixed>>, by_name: array<string, array<string, mixed>>, by_code: array<string, array<string, mixed>>}  $index
     * @return array<string, mixed>|null
     */
    private function buildContactPayload(array $row, array $index): ?array
    {
        $name = $this->demoName(trim((string) ($row['name'] ?? '')));

        if ($name === '[F5 demo]') {
            return null;
        }

        $customFields = [];
        $phone = trim((string) ($row['phone'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));

        if ($phone !== '') {
            $customFields[] = $this->phoneEmailField('PHONE', $phone, $index);
        }

        if ($email !== '') {
            $customFields[] = $this->phoneEmailField('EMAIL', $email, $index);
        }

        $fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];

        // Поддержка payload из предпросмотра: custom_fields_values уже собраны.
        if (isset($row['custom_fields_values']) && is_array($row['custom_fields_values'])) {
            foreach ($row['custom_fields_values'] as $cf) {
                if (is_array($cf)) {
                    $customFields[] = $cf;
                }
            }
        } else {
            foreach ($fields as $fieldName => $value) {
                $mapped = $this->mapNamedField((string) $fieldName, $value, $index);

                if ($mapped !== null) {
                    $customFields[] = $mapped;
                }
            }
        }

        $customFields = $this->uniqueCustomFields($customFields);

        $payload = [
            'name' => $name,
            'tags_to_add' => [['name' => self::DEMO_TAG]],
            '_preview' => [
                'phone' => $phone,
                'email' => $email,
                'fields' => $this->previewFieldsFromCustom($customFields, $index),
            ],
        ];

        if ($customFields !== []) {
            $payload['custom_fields_values'] = $customFields;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{by_id: array<int, array<string, mixed>>, by_name: array<string, array<string, mixed>>, by_code: array<string, array<string, mixed>>}  $index
     * @return array<string, mixed>|null
     */
    private function buildLeadPayload(array $row, array $index): ?array
    {
        $name = $this->demoName(trim((string) ($row['name'] ?? '')));

        if ($name === '[F5 demo]') {
            return null;
        }

        $customFields = [];
        $fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];

        if (isset($row['custom_fields_values']) && is_array($row['custom_fields_values'])) {
            foreach ($row['custom_fields_values'] as $cf) {
                if (is_array($cf)) {
                    $customFields[] = $cf;
                }
            }
        } else {
            foreach ($fields as $fieldName => $value) {
                $mapped = $this->mapNamedField((string) $fieldName, $value, $index);

                if ($mapped !== null) {
                    $customFields[] = $mapped;
                }
            }
        }

        $customFields = $this->uniqueCustomFields($customFields);
        $price = $row['price'] ?? null;
        $priceValue = is_numeric($price) ? (int) round((float) $price) : null;

        $payload = [
            'name' => $name,
            'tags_to_add' => [['name' => self::DEMO_TAG]],
            '_preview' => [
                'price' => $priceValue,
                'fields' => $this->previewFieldsFromCustom($customFields, $index),
            ],
        ];

        if ($priceValue !== null && $priceValue >= 0) {
            $payload['price'] = $priceValue;
        }

        if ($customFields !== []) {
            $payload['custom_fields_values'] = $customFields;
        }

        $pipelineId = (int) ($row['pipeline_id'] ?? 0);
        $statusId = (int) ($row['status_id'] ?? 0);
        if ($pipelineId > 0) {
            $payload['pipeline_id'] = $pipelineId;
        }
        if ($statusId > 0) {
            $payload['status_id'] = $statusId;
        }

        if (isset($row['_embedded']) && is_array($row['_embedded'])) {
            $payload['_embedded'] = $row['_embedded'];
        }

        if (isset($row['_preview']) && is_array($row['_preview'])) {
            $payload['_preview'] = array_merge($payload['_preview'], $row['_preview']);
        }

        return $payload;
    }

    private function demoName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '') {
            $name = 'Контакт';
        }

        if (! str_contains(mb_strtolower($name), mb_strtolower(self::DEMO_TAG))) {
            $name = '[F5 demo] '.$name;
        }

        return mb_substr($name, 0, 250);
    }

    /**
     * @param  array{by_id: array<int, array<string, mixed>>, by_name: array<string, array<string, mixed>>, by_code: array<string, array<string, mixed>>}  $index
     * @return array<string, mixed>
     */
    private function phoneEmailField(string $code, string $value, array $index): array
    {
        $field = $index['by_code'][$code] ?? null;
        $item = ['value' => $value];

        if ($code === 'PHONE') {
            $item['enum_code'] = 'WORK';
        } elseif ($code === 'EMAIL') {
            $item['enum_code'] = 'WORK';
        }

        if (is_array($field)) {
            return [
                'field_id' => (int) $field['id'],
                'values' => [$item],
            ];
        }

        return [
            'field_code' => $code,
            'values' => [$item],
        ];
    }

    /**
     * @param  array{by_id: array<int, array<string, mixed>>, by_name: array<string, array<string, mixed>>, by_code: array<string, array<string, mixed>>}  $index
     * @return array<string, mixed>|null
     */
    private function mapNamedField(string $fieldName, mixed $value, array $index): ?array
    {
        $key = mb_strtolower(trim($fieldName));

        if ($key === '') {
            return null;
        }

        $field = $index['by_name'][$key] ?? null;

        if (! is_array($field)) {
            return null;
        }

        $normalized = $this->normalizeFieldValue($field, $value);

        if ($normalized === null) {
            return null;
        }

        return [
            'field_id' => (int) $field['id'],
            'values' => [['value' => $normalized]],
        ];
    }

    /**
     * @param  array{id: int, name: string, type: string, code: ?string, enums: list<string>}  $field
     */
    private function normalizeFieldValue(array $field, mixed $value): mixed
    {
        if (is_array($value)) {
            $value = $value['value'] ?? reset($value);
        }

        if ($value === null) {
            return null;
        }

        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'checkbox' || $type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? true : false;
        }

        if ($type === 'numeric' || $type === 'price') {
            if (! is_numeric($value)) {
                return null;
            }

            return (float) $value + 0;
        }

        if ($type === 'date' || $type === 'birthday') {
            $raw = trim((string) $value);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                return strtotime($raw.' UTC') ?: $raw;
            }

            $ts = strtotime($raw);

            return $ts !== false ? $ts : null;
        }

        if ($type === 'select' || $type === 'radiobutton') {
            $raw = trim((string) $value);
            $enums = $field['enums'] ?? [];

            if ($enums !== [] && ! in_array($raw, $enums, true)) {
                $match = collect($enums)->first(
                    fn ($enum) => mb_strtolower((string) $enum) === mb_strtolower($raw)
                );

                if ($match === null) {
                    return null;
                }

                $raw = (string) $match;
            }

            return $raw !== '' ? $raw : null;
        }

        $text = trim((string) $value);

        return $text !== '' ? mb_substr($text, 0, 500) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $customFields
     * @return list<array<string, mixed>>
     */
    private function uniqueCustomFields(array $customFields): array
    {
        $seen = [];
        $result = [];

        foreach ($customFields as $field) {
            $key = isset($field['field_id'])
                ? 'id:'.(int) $field['field_id']
                : 'code:'.mb_strtoupper((string) ($field['field_code'] ?? ''));

            if ($key === 'id:0' || $key === 'code:' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = [
                'field_id' => $field['field_id'] ?? null,
                'field_code' => $field['field_code'] ?? null,
                'values' => $field['values'] ?? [],
            ];

            if ($result[array_key_last($result)]['field_id'] === null) {
                unset($result[array_key_last($result)]['field_id']);
            }

            if ($result[array_key_last($result)]['field_code'] === null) {
                unset($result[array_key_last($result)]['field_code']);
            }
        }

        return array_values($result);
    }

    /**
     * @param  list<array<string, mixed>>  $customFields
     * @param  array{by_id: array<int, array<string, mixed>>, by_name: array<string, array<string, mixed>>, by_code: array<string, array<string, mixed>>}  $index
     * @return array<string, string>
     */
    private function previewFieldsFromCustom(array $customFields, array $index): array
    {
        $out = [];

        foreach ($customFields as $field) {
            $label = null;

            if (isset($field['field_id'])) {
                $meta = $index['by_id'][(int) $field['field_id']] ?? null;
                $label = is_array($meta) ? (string) $meta['name'] : 'field_'.(int) $field['field_id'];
            } elseif (isset($field['field_code'])) {
                $code = mb_strtoupper((string) $field['field_code']);
                $meta = $index['by_code'][$code] ?? null;
                $label = is_array($meta) ? (string) $meta['name'] : $code;
            }

            $value = $field['values'][0]['value'] ?? null;

            if ($label && $value !== null && $value !== '') {
                if (is_bool($value)) {
                    $value = $value ? 'да' : 'нет';
                } elseif (is_int($value) && $value > 1000000000) {
                    $value = date('Y-m-d', $value);
                }

                $out[$label] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * @param  array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>, links: list<array<string, mixed>>}  $normalized
     * @return array{
     *     message: string,
     *     contacts: list<array<string, mixed>>,
     *     leads: list<array<string, mixed>>,
     *     links: list<array<string, mixed>>,
     *     summary: array{contacts: int, leads: int, links: int, fields_used: list<string>},
     *     payload: array{contacts: list<array<string, mixed>>, leads: list<array<string, mixed>>, links: list<array<string, mixed>>}
     * }
     */
    private function presentPreview(array $normalized, mixed $message): array
    {
        $fieldsUsed = [];
        $contactPreview = [];

        foreach ($normalized['contacts'] as $contact) {
            $preview = $contact['_preview'] ?? [];
            foreach (array_keys($preview['fields'] ?? []) as $name) {
                $fieldsUsed[$name] = true;
            }

            $contactPreview[] = [
                'name' => $contact['name'] ?? '',
                'phone' => $preview['phone'] ?? '',
                'email' => $preview['email'] ?? '',
                'fields' => $preview['fields'] ?? [],
            ];
        }

        $leadPreview = [];

        foreach ($normalized['leads'] as $lead) {
            $preview = $lead['_preview'] ?? [];
            foreach (array_keys($preview['fields'] ?? []) as $name) {
                $fieldsUsed[$name] = true;
            }

            $leadPreview[] = [
                'name' => $lead['name'] ?? '',
                'price' => $preview['price'] ?? null,
                'pipeline' => $preview['pipeline'] ?? null,
                'status' => $preview['status'] ?? null,
                'contact_name' => $preview['contact_name'] ?? null,
                'fields' => $preview['fields'] ?? [],
            ];
        }

        $linkPreview = [];
        foreach ($normalized['links'] ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }
            $linkPreview[] = [
                'lead_id' => (int) ($link['lead_id'] ?? 0),
                'lead_name' => (string) ($link['lead_name'] ?? ''),
                'contact_id' => (int) ($link['contact_id'] ?? 0),
                'contact_name' => (string) ($link['contact_name'] ?? ''),
            ];
        }

        $payloadContacts = array_map(function (array $row) {
            unset($row['_preview']);

            return $row;
        }, $normalized['contacts']);

        $payloadLeads = array_map(function (array $row) {
            unset($row['_preview']);

            return $row;
        }, $normalized['leads']);

        $payloadLinks = $this->normalizeLinksPayload($normalized['links'] ?? []);

        $msg = trim((string) $message);

        if ($msg === '') {
            $msg = sprintf(
                'Подготовлено: контактов %d, сделок %d, связей %d.',
                count($payloadContacts),
                count($payloadLeads),
                count($payloadLinks)
            );
        }

        return [
            'message' => $msg,
            'contacts' => $contactPreview,
            'leads' => $leadPreview,
            'links' => $linkPreview,
            'summary' => [
                'contacts' => count($payloadContacts),
                'leads' => count($payloadLeads),
                'links' => count($payloadLinks),
                'fields_used' => array_keys($fieldsUsed),
            ],
            'payload' => [
                'contacts' => $payloadContacts,
                'leads' => $payloadLeads,
                'links' => $payloadLinks,
            ],
        ];
    }
}
