<?php

namespace Tests\Unit;

use App\Exceptions\F5AiException;
use App\Services\ProjectAiChangesParser;
use Tests\TestCase;

class ProjectAiChangesParserTest extends TestCase
{
    public function test_parses_json_wrapped_in_markdown_fence(): void
    {
        $parser = app(ProjectAiChangesParser::class);

        $result = $parser->parse(<<<'TEXT'
Вот изменения:
```json
{
  "message": "Готово",
  "changes": {
    "funnels": {
      "create": [{"name": "Продажи", "stages": [{"name": "Лид", "action": "", "comment": ""}]}],
      "update": [],
      "delete": []
    }
  }
}
```
TEXT);

        $this->assertSame('Готово', $result['message']);
        $this->assertSame('Продажи', $result['changes']['funnels']['create'][0]['name']);
    }

    public function test_parses_json_with_trailing_commas(): void
    {
        $parser = app(ProjectAiChangesParser::class);

        $result = $parser->parse(<<<'JSON'
{
  "message": "Готово",
  "changes": {
    "integrations": {
      "create": [{"widget_slug": "chatter", "comment": "Чат",}],
      "update": [],
      "delete": [],
    }
  }
}
JSON);

        $this->assertSame('chatter', $result['changes']['integrations']['create'][0]['widget_slug']);
    }

    public function test_parses_json_with_prefix_text(): void
    {
        $parser = app(ProjectAiChangesParser::class);

        $result = $parser->parse('Ответ:\n{"message":"Готово","changes":{"project":{"fields":{"client_tasks":"Задачи"}}}}');

        $this->assertSame('Задачи', $result['changes']['project']['fields']['client_tasks']);
    }

    public function test_throws_on_invalid_json(): void
    {
        $parser = app(ProjectAiChangesParser::class);

        $this->expectException(F5AiException::class);
        $this->expectExceptionMessage('неверном формате');

        $parser->parse('Это не JSON');
    }

    public function test_parses_stage_auto_task_fields_and_outcomes(): void
    {
        $parser = app(ProjectAiChangesParser::class);

        $result = $parser->parse(<<<'JSON'
{
  "message": "Готово",
  "changes": {
    "stages": {
      "update": [{
        "id": 5,
        "action": "Позвонить за 15 минут",
        "deadline": "15 минут",
        "stage_type": "Первичный контакт",
        "kind": "normal",
        "outcomes": [
          {"result": "Дозвонился", "mode": "move", "target_stage_id": 6, "label": "Далее"},
          {"result": "Не берёт", "mode": "stay", "target_stage_name": "Новый лид"}
        ]
      }],
      "create": [],
      "delete": []
    },
    "funnels": {
      "create": [{
        "name": "Продажи",
        "stages": [{
          "name": "Лид",
          "action": "Квалифицировать",
          "deadline": "1 день",
          "outcomes": [{"result": "Ок", "mode": "move", "target_sort_order": 2}]
        }]
      }],
      "update": [],
      "delete": []
    }
  }
}
JSON);

        $stage = $result['changes']['stages']['update'][0];
        $this->assertSame('Позвонить за 15 минут', $stage['action']);
        $this->assertSame('15 минут', $stage['deadline']);
        $this->assertSame('Первичный контакт', $stage['stage_type']);
        $this->assertSame(6, $stage['outcomes'][0]['target_stage_id']);
        $this->assertSame('Новый лид', $stage['outcomes'][1]['target_stage_name']);

        $nested = $result['changes']['funnels']['create'][0]['stages'][0];
        $this->assertSame('Квалифицировать', $nested['action']);
        $this->assertSame('1 день', $nested['deadline']);
        $this->assertSame(2, $nested['outcomes'][0]['target_sort_order']);
    }
}
