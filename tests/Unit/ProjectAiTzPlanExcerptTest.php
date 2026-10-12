<?php

namespace Tests\Unit;

use App\Services\ProjectAiTzPlanService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProjectAiTzPlanExcerptTest extends TestCase
{
    public function test_excerpt_keeps_the_beginning_of_the_document(): void
    {
        $service = (new \ReflectionClass(ProjectAiTzPlanService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ProjectAiTzPlanService::class, 'excerpt');
        $method->setAccessible(true);

        $prefix = 'ЭТАПЫ ВОРОНКИ: Новый запрос → Диагностика → КП → Договор';
        $text = $prefix.str_repeat(' X', 5000);

        $excerpt = $method->invoke($service, $text, 80);

        $this->assertStringStartsWith('ЭТАПЫ ВОРОНКИ', $excerpt);
        $this->assertStringContainsString('Новый запрос', $excerpt);
        $this->assertStringContainsString('обрезан', $excerpt);
    }

    public function test_extract_plan_focus_prefers_funnels_and_fields_sections(): void
    {
        $service = (new \ReflectionClass(ProjectAiTzPlanService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ProjectAiTzPlanService::class, 'extractPlanFocus');
        $method->setAccessible(true);

        $report = <<<'MD'
# План
## Резюме ТЗ
Клиенту нужна CRM.

## Поля CRM
- Бюджет (сделки)

## Воронки и этапы
### Воронка: Продажи
1. Новый запрос
2. КП

## Риски
Сроки сжаты.
MD;

        $focus = $method->invoke($service, $report);

        $this->assertStringContainsString('Воронки и этапы', $focus);
        $this->assertStringContainsString('Поля CRM', $focus);
        $this->assertStringContainsString('Новый запрос', $focus);
        $this->assertStringNotContainsString('Сроки сжаты', $focus);
    }

    public function test_parse_funnels_from_markdown_reads_named_funnels_and_stages(): void
    {
        $service = (new \ReflectionClass(ProjectAiTzPlanService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ProjectAiTzPlanService::class, 'parseFunnelsFromMarkdown');
        $method->setAccessible(true);

        $report = <<<'MD'
## Воронки и этапы
### Воронка: B2B продажи
1. Новый запрос — квалификация
2. Диагностика — созвон
3. КП — отправить коммерческое

### Воронка: Сервис
1. Обращение
2. В работе
3. Закрыто

## Интеграции
- Chatter
MD;

        $funnels = $method->invoke($service, $report);

        $this->assertCount(2, $funnels);
        $this->assertSame('B2B продажи', $funnels[0]['name']);
        $this->assertSame(['Новый запрос', 'Диагностика', 'КП'], array_column($funnels[0]['stages'], 'name'));
        $this->assertSame('квалификация', $funnels[0]['stages'][0]['action']);
        $this->assertSame('Сервис', $funnels[1]['name']);
        $this->assertCount(3, $funnels[1]['stages']);
    }

    public function test_parse_funnels_prefers_stages_section_over_fields_for_funnel(): void
    {
        $service = (new \ReflectionClass(ProjectAiTzPlanService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ProjectAiTzPlanService::class, 'parseFunnelsFromMarkdown');
        $method->setAccessible(true);

        $report = <<<'MD'
# 3. **Поля CRM**
### Воронка 0: «Неразобранное»
Обязательные поля:
- **Дата обращения** — дата, автозаполнение
- **Номер телефона** — телефон
- **Канал обращения** — список:
  - Звонок
  - WhatsApp
- **Направление** — список
- **Комментарий менеджера** — текст

# 4. **Воронки и этапы**
## Воронки и этапы
### Воронка: Неразобранное
1. Новое обращение — менеджер смотрит, кто звонил
2. Передано в отдел продаж техники — менеджер переводит в воронку 1
3. Передано в отдел запчастей — обращение закрывается здесь
4. Передано в холодный обзвон — менеджер переводит в воронку 3
5. Отклонён — менеджер отклоняет спам

### Воронка: Тяжелая техника
1. Новая заявка — менеджер фиксирует лид
2. Переговоры — менеджер обсуждает потребности
3. Направлено КП — менеджер отправляет КП
4. Согласование условий — менеджер обсуждает условия
5. Договор / Счет — менеджер выставляет счет
6. Закрыто. Успех — менеджер фиксирует оплату
7. Закрыто. Отказ — менеджер фиксирует отказ

# 5. **Интеграции**
- Chatter
MD;

        $funnels = $method->invoke($service, $report);

        $this->assertGreaterThanOrEqual(2, count($funnels));
        $this->assertSame('Неразобранное', $funnels[0]['name']);
        $this->assertCount(5, $funnels[0]['stages']);
        $this->assertSame('Новое обращение', $funnels[0]['stages'][0]['name']);
        $this->assertSame('Тяжелая техника', $funnels[1]['name']);
        $this->assertCount(7, $funnels[1]['stages']);
    }

    public function test_parse_funnels_ignores_reports_without_funnel_section(): void
    {
        $service = (new \ReflectionClass(ProjectAiTzPlanService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ProjectAiTzPlanService::class, 'parseFunnelsFromMarkdown');
        $method->setAccessible(true);

        $report = "## План внедрения\n\n1. Воронка B2B\n2. Chatter";

        $this->assertSame([], $method->invoke($service, $report));
    }

    public function test_parse_skk_fields_from_fields_crm_section(): void
    {
        $service = (new \ReflectionClass(ProjectAiTzPlanService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ProjectAiTzPlanService::class, 'parseSkkFieldsFromMarkdown');
        $method->setAccessible(true);

        $report = <<<'MD'
# 3. **Поля CRM**
## Сделки
### Воронка 0: «Неразобранное»
- **Дата обращения** — дата, автозаполнение
- **Канал обращения** — список:
  - Звонок
  - WhatsApp
  - Telegram
- **Бюджет** — деньги, обязательно после этапа «Переговоры»

## Компании
- **ИНН** — строка
- **Активный клиент** — чекбокс

# 4. **Воронки и этапы**
### Воронка: Неразобранное
1. Новое обращение — менеджер смотрит
MD;

        $fields = $method->invoke($service, $report);

        $this->assertGreaterThanOrEqual(5, count($fields));
        $this->assertSame('Дата обращения', $fields[0]['name']);
        $this->assertSame('deals', $fields[0]['entity_type']);
        $this->assertSame('date', $fields[0]['value_type']);

        $channel = null;
        $inn = null;

        foreach ($fields as $field) {
            if ($field['name'] === 'Канал обращения') {
                $channel = $field;
            }

            if ($field['name'] === 'ИНН') {
                $inn = $field;
            }
        }

        $this->assertNotNull($channel);
        $this->assertSame('select', $channel['value_type']);
        $this->assertSame(['Звонок', 'WhatsApp', 'Telegram'], $channel['options']);

        $this->assertNotNull($inn);
        $this->assertSame('companies', $inn['entity_type']);
    }

    public function test_empty_changes_are_rejected(): void
    {
        $service = (new \ReflectionClass(ProjectAiTzPlanService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ProjectAiTzPlanService::class, 'changesHaveOperations');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, null));
        $this->assertFalse($method->invoke($service, [
            'funnels' => ['create' => [], 'update' => [], 'delete' => []],
        ]));
        $this->assertTrue($method->invoke($service, [
            'funnels' => [
                'create' => [
                    [
                        'name' => 'Продажи',
                        'stages' => [['name' => 'Лид']],
                    ],
                ],
                'update' => [],
                'delete' => [],
            ],
        ]));
    }
}
