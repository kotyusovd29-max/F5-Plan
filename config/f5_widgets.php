<?php

return [
  'source_url' => 'https://cmdf5.ru/widjety-amocrm',
  'categories' => 
  [
    'automation' => 'Автоматизация',
    'integrations' => 'Интеграции',
    'ai' => 'ИИ',
    'utilities' => 'Удобства',
    'packages' => 'Подписки',
  ],
  'widgets' => 
  [
    0 => 
    [
      'slug' => 'ai-manager',
      'name' => 'Chat AI',
      'description' => 'ИИ-агент для продаж и автоматизации в amoCRM',
      'price' => '4 990 ₽',
      'category' => 'ai',
      'url' => 'https://cmdf5.ru/ai-manager',
    ],
    1 => 
    [
      'slug' => 'chatter',
      'name' => 'Chatter',
      'description' => 'Интеграция amoCRM с Вотсап, Авито, Телеграм, Вконтакте, IG',
      'price' => '3 500 ₽ / канал · мес',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/chatter',
      'pricing' => [
        'model' => 'per_channel_monthly',
        'unit_monthly' => 3500,
        'quantity_label' => 'каналов',
        'default_quantity' => 1,
        'min_quantity' => 1,
      ],
    ],
    2 => 
    [
      'slug' => 'finder',
      'name' => 'Finder',
      'description' => 'Отслеживание скорости ответов в мессенджерах',
      'price' => '249 ₽',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/finder',
    ],
    3 => 
    [
      'slug' => 'google-sheets-lite',
      'name' => 'Google Sheets Lite',
      'description' => 'Выгрузка сделок, контактов и компаний в Google Таблицы по шаблонам.',
      'price' => '399 ₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/google-sheets_lite',
    ],
    4 => 
    [
      'slug' => 'meet-ai',
      'name' => 'Meet AI',
      'description' => 'Видеозвонки в один клик с расшифровкой и ИИ-анализом',
      'price' => '1.5 ₽ / час',
      'category' => 'ai',
      'url' => 'https://cmdf5.ru/widjety-amocrm/meet-ai',
    ],
    5 => 
    [
      'slug' => 'palantir',
      'name' => 'Palantir',
      'description' => 'ИИ-помощник для ведения сделок и общения с клиентами',
      'price' => 'Расчет по токенам',
      'category' => 'ai',
      'url' => 'https://cmdf5.ru/widjety-amocrm/palantir',
    ],
    6 => 
    [
      'slug' => 'smart-buttons',
      'name' => 'SMART кнопки',
      'description' => 'Умные кнопки автоматизации',
      'price' => '149 ₽',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/smart-buttons',
    ],
    7 => 
    [
      'slug' => 'tasker',
      'name' => 'Tasker',
      'description' => 'Таск-менеджер для amoCRM',
      'price' => '599 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/tasker',
    ],
    8 => 
    [
      'slug' => 'lead-stage-time',
      'name' => 'Tracker',
      'description' => 'Отслеживание времени работы со сделкой',
      'price' => 'Бонусный пакет',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/lead-stage-time',
    ],
    9 => 
    [
      'slug' => 'voice-ai',
      'name' => 'Voice AI',
      'description' => 'ИИ-анализ звонков',
      'price' => '2 ₽ / мин',
      'category' => 'ai',
      'url' => 'https://cmdf5.ru/widjety-amocrm/voice-ai',
    ],
    10 => 
    [
      'slug' => 'poisk-dubley',
      'name' => 'Антидубль',
      'description' => 'Контроль и предотвращение дублей в системе',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/poisk-dubley',
    ],
    11 => 
    [
      'slug' => 'second-responsible',
      'name' => 'Второй ответственный',
      'description' => 'Привязка второго ответственного к сделке.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    12 => 
    [
      'slug' => 'amocrm-excel-online',
      'name' => 'Выгрузка в Google Таблицы',
      'description' => 'Выгрузка и данных из amoCRM в Гугл-таблицы',
      'price' => '499 ₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/amocrm-excel-online',
    ],
    13 => 
    [
      'slug' => 'field-grouping',
      'name' => 'Группировка полей',
      'description' => 'Сворачивает несколько полей и разворачивает при клике.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    14 => 
    [
      'slug' => 'group-edit',
      'name' => 'Групповые действия',
      'description' => 'Массовая смена любых полей в amoCRM',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/group-edit',
    ],
    15 => 
    [
      'slug' => 'telegram-group-amocrm',
      'name' => 'Групповые чаты в Telegram',
      'description' => 'Общение с групповыми чатами в телеграм из amoCRM',
      'price' => '249 ₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/telegram-group-amocrm',
    ],
    16 => 
    [
      'slug' => 'widget-docs-amocrm',
      'name' => 'Документы',
      'description' => 'Генерация любых документов в два клика',
      'price' => '399 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/widget-docs-amocrm',
    ],
    17 => 
    [
      'slug' => 'custom',
      'name' => 'Другое (свой вариант)',
      'description' => 'Интеграция или виджет не из каталога F5.',
      'price' => '—',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm',
    ],
    18 => 
    [
      'slug' => 'no-task-close-without-result',
      'name' => 'Запрет закрытия задачи без результата',
      'description' => 'Нельзя закрыть задачу без текста результата.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    19 => 
    [
      'slug' => 'no-deals-without-tasks',
      'name' => 'Запрет сделок без задач',
      'description' => 'Не позволяет работать, если есть активные сделки без задачи.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    20 => 
    [
      'slug' => 'task-delete-ban',
      'name' => 'Запрет удаления задач',
      'description' => 'Ограничивает редактирование и удаление задач для менеджеров.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    21 => 
    [
      'slug' => '1c',
      'name' => 'Интеграция с 1C',
      'description' => 'Полноценный функционал 1C в amoCRM, синхрониазция счетов, контрагентов, платежей',
      'price' => '60 000 ₽ / 6 мес · 90 000 ₽ / 12 мес',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/1c',
      'pricing' => [
        'model' => 'fixed_period',
        'packages' => [
          6 => 60000,
          12 => 90000,
        ],
      ],
    ],
    22 => 
    [
      'slug' => 'vidzhet-integratsii-google-disk-s-amocrm',
      'name' => 'Интеграция с Гугл Диск',
      'description' => 'Хранение файлов из amoCRM на облаке Google',
      'price' => 'Бонусный пакет',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/vidzhet-integratsii-google-disk-s-amocrm',
    ],
    23 => 
    [
      'slug' => 'vidzhet-moysklad-dlya-amocrm',
      'name' => 'Интеграция с МойСклад',
      'description' => 'Интеграция amoCRM со складским учетом МойСклад',
      'price' => '499 ₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/vidzhet-moysklad-dlya-amocrm',
    ],
    24 => 
    [
      'slug' => 'vidzhet-yandeks-disk-dlya-amocrm',
      'name' => 'Интеграция с Яндекс Диск',
      'description' => 'Хранение файлов из amoCRM в облаке Яндекс',
      'price' => 'Бонусный пакет',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/vidzhet-yandeks-disk-dlya-amocrm',
    ],
    25 => 
    [
      'slug' => 'vk-market',
      'name' => 'Интеграция с магазином ВКонтакте',
      'description' => 'Заказы из магазина VK в amoCRM, выгрузка товаров, переписка с клиентами.',
      'price' => '—',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm',
    ],
    26 => 
    [
      'slug' => 'form-vk-amocrm',
      'name' => 'Интеграция с формами ВК',
      'description' => 'Интеграция amoCRM с заявками из форм Вконтакте',
      'price' => '149 ₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/form-vk-amocrm',
    ],
    27 => 
    [
      'slug' => 'company-info',
      'name' => 'Информер',
      'description' => 'Всплывающее окно при наведении на любую сущность',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/company-info',
    ],
    28 => 
    [
      'slug' => 'kassa',
      'name' => 'КАССА',
      'description' => 'Интеграция с Т-Банк, Т-Бизнес, ЮKassa, PayKeeper, СберБизнес, Альфа-Банк, Модульбанк, Банк Точка и CloudPayments',
      'price' => '24 990 ₽ в год',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/kassa',
    ],
    29 => 
    [
      'slug' => 'calc-fields',
      'name' => 'Калькулятор полей',
      'description' => 'Сложные математические операции с полями карточек',
      'price' => '249 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/calc-fields',
    ],
    30 => 
    [
      'slug' => 'copy-lead',
      'name' => 'Копирование сделки',
      'description' => 'Позволит скопировать сделку со всей историей',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/copy-lead',
    ],
    31 => 
    [
      'slug' => 'multi-company',
      'name' => 'Мультикомпании',
      'description' => 'Cвязи между компаниями',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/multi-company',
    ],
    32 => 
    [
      'slug' => 'whatsapp-web',
      'name' => 'Написать в WhatsApp',
      'description' => 'Позволит написать в WhatsApp через веб-версию мессенджера',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/whatsapp-web',
    ],
    33 => 
    [
      'slug' => 'normalizer',
      'name' => 'Нормализатор',
      'description' => 'Стандартизация телефонных номеров',
      'price' => '149 ₽',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/normalizer',
    ],
    34 => 
    [
      'slug' => 'time-zone-amocrm',
      'name' => 'Определение региона по номеру телефона',
      'description' => 'Покажет время клиента в зависимости от его региона',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/time-zone-amocrm',
    ],
    35 => 
    [
      'slug' => 'sp',
      'name' => 'Платформа',
      'description' => 'Набор виджетов для улучшения amoCRM',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    36 => 
    [
      'slug' => 'help-field',
      'name' => 'Подсказки полей',
      'description' => 'Выводит подсказки, которые помогают правильно заполнить поля',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/help-field',
    ],
    37 => 
    [
      'slug' => 'list-field-search',
      'name' => 'Поиск по списковым полям',
      'description' => 'Быстрый поиск значений в полях типа «список».',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    38 => 
    [
      'slug' => 'pochta-rossii',
      'name' => 'Почта России',
      'description' => 'Интеграция с доставкой через Почту России',
      'price' => '150 ₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/pochta-rossii',
    ],
    39 => 
    [
      'slug' => 'vidzhet-pochtovik-dlya-raboty-s-elektronnoy-pochtoy',
      'name' => 'Почтовик',
      'description' => 'Отслеживание открытий писем и постановка задач',
      'price' => '399 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/vidzhet-pochtovik-dlya-raboty-s-elektronnoy-pochtoy',
    ],
    40 => 
    [
      'slug' => 'bpmn',
      'name' => 'Процессы',
      'description' => 'BPMN платформа для автоматизации процессов',
      'price' => '1299 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/bpmn',
    ],
    41 => 
    [
      'slug' => 'pusher',
      'name' => 'Пушер',
      'description' => 'Уведомления о важных событиях с помощью всплывающих окон',
      'price' => '249 ₽',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/pusher',
    ],
    42 => 
    [
      'slug' => 'smart',
      'name' => 'Распределение SMART',
      'description' => 'Распределение заявок с учетом кол-ва сделок и задач',
      'price' => '299 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/smart',
    ],
    43 => 
    [
      'slug' => 'raspredeline-zayavok',
      'name' => 'Распределение заявок',
      'description' => 'Автоматическое распределение новых заявок',
      'price' => '399 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/raspredeline-zayavok',
    ],
    44 => 
    [
      'slug' => 'amocrm-dadata',
      'name' => 'Реквизиты DaData',
      'description' => 'Автоматическое заполнение реквизитов, адресов, паспортов и т.д.',
      'price' => '149 ₽',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/amocrm-dadata',
    ],
    45 => 
    [
      'slug' => 'sms',
      'name' => 'СМС-шлюз',
      'description' => 'Отправка СМС через один из 11 агрегаторов',
      'price' => 'Бонусный пакет',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sms',
    ],
    46 => 
    [
      'slug' => 'timezone-switch',
      'name' => 'Смена часового пояса',
      'description' => 'Меняет часовой пояс для пользователей в другой временной зоне.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    47 => 
    [
      'slug' => 'tablo',
      'name' => 'Табель',
      'description' => 'Список сотрудников и их активность в amoCRM',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/tablo',
    ],
    48 => 
    [
      'slug' => 'triggers',
      'name' => 'Триггеры',
      'description' => 'NO CODE инструмент для триггерных автоматизаций',
      'price' => '699 ₽',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/triggers',
    ],
    49 => 
    [
      'slug' => 'telegram-amocrm',
      'name' => 'Уведомления в Telegram',
      'description' => 'Отправка важных уведомлений в чат, в канал или в личку в телеграм',
      'price' => '149₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/telegram-amocrm',
    ],
    50 => 
    [
      'slug' => 'max-amocrm',
      'name' => 'Уведомления в МАКС (MAX]',
      'description' => 'Отпраляйте уведомления о важных событиях в мессенджер МАКС',
      'price' => '149₽',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/max-amocrm',
    ],
    51 => 
    [
      'slug' => 'file-cleaner',
      'name' => 'Удаление файлов',
      'description' => 'Ежедневно очищает файловое хранилище',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/file-cleaner',
    ],
    52 => 
    [
      'slug' => 'hidden-field',
      'name' => 'Управление полями',
      'description' => 'Скрытие полей, страниц, разделов и т.д.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/hidden-field',
    ],
    53 => 
    [
      'slug' => 'feed-filter',
      'name' => 'Фильтр ленты',
      'description' => 'Скрывает ненужные события в ленте сделки.',
      'price' => 'Бонусный пакет',
      'category' => 'utilities',
      'url' => 'https://cmdf5.ru/widjety-amocrm/sp',
    ],
    54 => 
    [
      'slug' => 'check-list',
      'name' => 'Чек-листы',
      'description' => 'Добавление чек-листов в сделку',
      'price' => 'Бонусный пакет',
      'category' => 'automation',
      'url' => 'https://cmdf5.ru/widjety-amocrm/check-list',
    ],
    55 => 
    [
      'slug' => 'metrika-amo',
      'name' => 'Яндекс Метрика',
      'description' => 'Интеграция с Яндекс Метрикой для отправки оффлайн целей',
      'price' => 'Бонусный пакет',
      'category' => 'integrations',
      'url' => 'https://cmdf5.ru/widjety-amocrm/metrika-amo',
    ],
  ],
];
