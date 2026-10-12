<?php

$text = file_get_contents('C:/Users/kotyu/.cursor/projects/c-OSPanel-home-F5plan/agent-tools/4fc2457c-a928-4cc2-9e10-9e53a76e1237.txt');

preg_match_all(
    '/([^;\n]+?) link=(https:\/\/cmdf5\.ru\/[^;\s]+);([^;]+);([^;]+);/u',
    $text,
    $matches,
    PREG_SET_ORDER
);

$categoryMap = [
    'voice-ai' => 'ai',
    'meet-ai' => 'ai',
    'palantir' => 'ai',
    'ai-manager' => 'ai',
    'chatter' => 'integrations',
    '1c' => 'integrations',
    'vidzhet-moysklad-dlya-amocrm' => 'integrations',
    'form-vk-amocrm' => 'integrations',
    'kassa' => 'integrations',
    'pochta-rossii' => 'integrations',
    'vidzhet-yandeks-disk-dlya-amocrm' => 'integrations',
    'vidzhet-integratsii-google-disk-s-amocrm' => 'integrations',
    'amocrm-excel-online' => 'integrations',
    'metrika-amo' => 'integrations',
    'vk-market' => 'integrations',
    'bpmn' => 'automation',
    'triggers' => 'automation',
    'widget-docs-amocrm' => 'automation',
    'vidzhet-pochtovik-dlya-raboty-s-elektronnoy-pochtoy' => 'automation',
    'raspredeline-zayavok' => 'automation',
    'smart' => 'automation',
    'tasker' => 'automation',
    'calc-fields' => 'automation',
    'check-list' => 'automation',
    'lead-stage-time' => 'automation',
    'sms' => 'automation',
    'telegram-group-amocrm' => 'integrations',
    'telegram-amocrm' => 'integrations',
    'max-amocrm' => 'integrations',
    'package-base' => 'packages',
    'package-pro' => 'packages',
    'package-ultima' => 'packages',
];

$widgets = [];

foreach ($matches as $match) {
    $name = trim($match[1]);
    if (preg_match('/link=\s*https?:\/\/\S+\s+(.+)$/iu', $name, $nameMatch)) {
        $name = trim($nameMatch[1]);
    } else {
        $name = preg_replace('/^link=\s*https?:\/\/\S+\s+/iu', '', $name);
        $name = preg_replace('/^https?:\/\/\S+\s+/iu', '', $name);
        $name = preg_replace('/^(?:Установить|Подробнее)\s+/u', '', $name);
        $name = trim($name);
    }
    $name = str_replace('(MAX]', '(MAX)', $name);

    if ($name === '' || $name === 'Виджет' || str_contains($name, 'Описание')) {
        continue;
    }

    $url = trim($match[2]);
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    $slug = basename(rtrim($path, '/'));

    if (str_starts_with($name, 'Подписка')) {
        $slug = 'package-'.strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    }

    if ($slug === '' || $slug === 'widjety-amocrm') {
        continue;
    }

    $widgets[$slug] = [
        'slug' => $slug,
        'name' => $name,
        'description' => trim($match[3]),
        'price' => trim($match[4]),
        'category' => $categoryMap[$slug] ?? 'utilities',
        'url' => $url,
    ];
}

$extra = [
    ['slug' => 'google-sheets-lite', 'name' => 'Google Sheets Lite', 'description' => 'Выгрузка сделок, контактов и компаний в Google Таблицы по шаблонам.', 'price' => '399 ₽', 'category' => 'integrations', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'vk-market', 'name' => 'Интеграция с магазином ВКонтакте', 'description' => 'Заказы из магазина VK в amoCRM, выгрузка товаров, переписка с клиентами.', 'price' => '—', 'category' => 'integrations', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'no-deals-without-tasks', 'name' => 'Запрет сделок без задач', 'description' => 'Не позволяет работать, если есть активные сделки без задачи.', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'no-task-close-without-result', 'name' => 'Запрет закрытия задачи без результата', 'description' => 'Нельзя закрыть задачу без текста результата.', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'field-grouping', 'name' => 'Группировка полей', 'description' => 'Сворачивает несколько полей и разворачивает при клике.', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'timezone-switch', 'name' => 'Смена часового пояса', 'description' => 'Меняет часовой пояс для пользователей в другой временной зоне.', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'task-delete-ban', 'name' => 'Запрет удаления задач', 'description' => 'Ограничивает редактирование и удаление задач для менеджеров.', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'second-responsible', 'name' => 'Второй ответственный', 'description' => 'Привязка второго ответственного к сделке.', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'feed-filter', 'name' => 'Фильтр ленты', 'description' => 'Скрывает ненужные события в ленте сделки.', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'list-field-search', 'name' => 'Поиск по списковым полям', 'description' => 'Быстрый поиск значений в полях типа «список».', 'price' => 'Бонусный пакет', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
    ['slug' => 'custom', 'name' => 'Другое (свой вариант)', 'description' => 'Интеграция или виджет не из каталога F5.', 'price' => '—', 'category' => 'utilities', 'url' => 'https://cmdf5.ru/widjety-amocrm'],
];

foreach ($extra as $item) {
    $widgets[$item['slug']] = $item;
}

usort($widgets, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

$config = [
    'source_url' => 'https://cmdf5.ru/widjety-amocrm',
    'categories' => [
        'automation' => 'Автоматизация',
        'integrations' => 'Интеграции',
        'ai' => 'ИИ',
        'utilities' => 'Удобства',
        'packages' => 'Подписки',
    ],
    'widgets' => array_values($widgets),
];

$content = "<?php\n\nreturn ".var_export($config, true).";\n";
$content = str_replace(['array (', ')'], ['[', ']'], $content);

file_put_contents(__DIR__.'/../config/f5_widgets.php', $content);

echo 'Widgets: '.count($widgets).PHP_EOL;
