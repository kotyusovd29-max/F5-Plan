<?php

namespace App\Support;

class ProjectAiPrompts
{
    /**
     * @return array<string, string>
     */
    public static function analysis(): array
    {
        return [
            'title' => 'Анализ проекта',
            'description' => 'ИИ изучит поля, воронки и интеграции и предложит улучшения',
            'mode' => 'project_analysis',
            'message' => 'Проанализируй проект и дай советы по улучшению плана внедрения amoCRM.',
        ];
    }

    /**
     * @return list<array{id: string, label: string, message: string}>
     * @deprecated Use analysis() instead.
     */
    public static function suggestions(): array
    {
        return [
            [
                'id' => 'b2b_funnel',
                'label' => 'Сгенерировать воронку для B2B',
                'message' => 'Предложи и добавь в проект воронку продаж для B2B-компании: типовые этапы, действия менеджера на каждом этапе, SLA и комментарии к переходам.',
            ],
            [
                'id' => 'analytics_fields',
                'label' => 'Поля для сквозной аналитики',
                'message' => 'Дополни проект полями сделок, контактов и компаний для сквозной аналитики: UTM-метки, источник лида, канал, кампания, referrer. Укажи типы полей и обязательность.',
            ],
            [
                'id' => 'integrations_pack',
                'label' => 'Пакет интеграций',
                'message' => 'Предложи и добавь типовой набор интеграций и виджетов amoCRM для этого клиента: телефония, мессенджеры, сайт, почта, 1С/учёт — с кратким сценарием для каждой.',
            ],
            [
                'id' => 'contact_fields',
                'label' => 'Дополнить поля контактов',
                'message' => 'Проанализируй проект и добавь недостающие поля в контакты: должность, сегмент, предпочитаемый канал связи, ЛПР/не ЛПР и другие типовые B2B-поля.',
            ],
            [
                'id' => 'stage_sla',
                'label' => 'SLA на этапах воронки',
                'message' => 'Проверь этапы воронок в проекте и дополни: action (автозадача менеджера), deadline/SLA, outcomes (результаты и переходы без виджетов), триггеры в comment. Виджеты на этапах (widgets) и widget_slug в outcomes не добавляй — только если пользователь отдельно попросит.',
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, mode: string, message: string}>
     */
    public static function actions(): array
    {
        return [
            [
                'id' => 'audit',
                'label' => 'Аудит плана',
                'description' => 'Чего не хватает в плане',
                'mode' => 'audit',
                'message' => 'Проведи аудит текущего плана внедрения amoCRM для этого проекта.',
            ],
            [
                'id' => 'benchmark_b2b',
                'label' => 'Сравнение с B2B',
                'description' => 'Насколько план похож на эталон',
                'mode' => 'benchmark_b2b',
                'message' => 'Сравни текущий план внедрения с типовым эталоном B2B-внедрения amoCRM.',
            ],
            [
                'id' => 'auto_tasks',
                'label' => 'Автозадачи',
                'description' => 'Варианты автоматизации с записью в этапы',
                'mode' => 'auto_tasks',
                'message' => 'Предложи варианты автоматизации автозадач для этапов воронок этого проекта и подготовь изменения для записи в проект. Для каждого этапа: 2–3 варианта текста автозадачи (что должен сделать менеджер), рекомендуемый вариант, deadline/SLA, outcomes (результаты и переходы), при необходимости stage_type. Базово НЕ включай виджеты: не заполняй widgets этапа и не ставь widget_slug в outcomes — только action, deadline, comment, kind, outcomes с переходами. Виджеты добавляй лишь если пользователь явно попросит. Запиши рекомендуемые варианты в stages.update. В message кратко сравни варианты и объясни выбор.',
            ],
        ];
    }

    public static function modeMessage(string $mode): ?string
    {
        foreach (self::actions() as $action) {
            if ($action['mode'] === $mode) {
                return $action['message'];
            }
        }

        return null;
    }

    /**
     * @return list<array{id: string, label: string, description: string, message: string}>
     */
    public static function meetingActions(): array
    {
        return [
            [
                'id' => 'meeting_structure',
                'label' => 'Структура встречи',
                'description' => 'Повестка на 60–90 минут',
                'message' => 'Подготовь структуру первичной встречи по внедрению amoCRM: цель, повестка с таймингом на 60–90 минут, ключевые вопросы и ожидаемые результаты. Пиши компактно.',
            ],
            [
                'id' => 'meeting_questions',
                'label' => 'Вопросы клиенту',
                'description' => 'Что выяснить на встрече',
                'message' => 'Составь короткий список вопросов для первичной встречи: бизнес-процессы, воронки, роли, интеграции, боли и критерии успеха.',
            ],
            [
                'id' => 'meeting_followup',
                'label' => 'После встречи',
                'description' => 'Follow-up и следующие шаги',
                'message' => 'Подготовь короткий шаблон follow-up после первичной встречи: что зафиксировать, какой документ отправить и следующие шаги.',
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, message: string}>
     */
    public static function auditRecommendationActions(): array
    {
        return [
            [
                'id' => 'audit_from_transcript',
                'label' => 'Из записи встречи',
                'description' => 'Рекомендации клиенту по транскрипту',
                'message' => 'Ниже текст записи встречи / аудита. Сформируй документ «Рекомендации по аудиту amoCRM» для клиента: что улучшить в настройке и процессах, с приоритетами и следующими шагами. Опирайся только на факты из записи; если данных мало — явно отметь пробелы.',
            ],
            [
                'id' => 'audit_client_doc',
                'label' => 'Документ клиенту',
                'description' => 'Структура рекомендаций',
                'message' => 'Подготовь клиентский документ рекомендаций по улучшению amoCRM: резюме, что уже хорошо, рекомендации с приоритетом (высокий/средний/низкий), воронки и этапы, поля, автоматизация, интеграции, следующие шаги. Пиши деловым языком для клиента, без внутреннего жаргона команды внедрения.',
            ],
            [
                'id' => 'audit_priorities',
                'label' => 'Топ приоритетов',
                'description' => '5–7 главных улучшений',
                'message' => 'Выдели 5–7 приоритетных рекомендаций по улучшению amoCRM для клиента: для каждой — зачем, эффект, сложность внедрения и что сделать в первую очередь.',
            ],
        ];
    }
}
