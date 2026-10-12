<?php

return [
    'api_key' => env('F5AI_API_KEY'),
    'api_url' => env('F5AI_API_URL', 'https://api.f5ai.ru/v2'),
    'model' => env('F5AI_MODEL', 'gpt-5.5'),
    'model_large' => env('F5AI_MODEL_LARGE', env('F5AI_MODEL', 'gpt-5.5')),
    /*
     * Доступные модели для выбора в личном кабинете (коды F5AI).
     * large — модель для длинных отчётов; если не указана, используется тот же id.
     */
    'models' => [
        'gpt-6-astra' => [
            'label' => 'GPT-6 Astra',
            'vendor' => 'OpenAI',
            'description' => 'Флагманское поколение GPT-6 · вариант Astra',
            'large' => 'gpt-6-astra',
        ],
        'gpt-6-sol' => [
            'label' => 'GPT-6 Sol',
            'vendor' => 'OpenAI',
            'description' => 'Флагманское поколение GPT-6 · вариант Sol',
            'large' => 'gpt-6-sol',
        ],
        'gpt-6-luna' => [
            'label' => 'GPT-6 Luna',
            'vendor' => 'OpenAI',
            'description' => 'Флагманское поколение GPT-6 · вариант Luna',
            'large' => 'gpt-6-luna',
        ],
        'gpt-5.6-sol' => [
            'label' => 'GPT-5.6 Sol',
            'vendor' => 'OpenAI',
            'description' => 'Поколение 5.6 · вариант Sol',
            'large' => 'gpt-5.6-sol',
        ],
        'gpt-5.6-terra' => [
            'label' => 'GPT-5.6 Terra',
            'vendor' => 'OpenAI',
            'description' => 'Поколение 5.6 · вариант Terra',
            'large' => 'gpt-5.6-terra',
        ],
        'gpt-5.6-luna' => [
            'label' => 'GPT-5.6 Luna',
            'vendor' => 'OpenAI',
            'description' => 'Поколение 5.6 · вариант Luna',
            'large' => 'gpt-5.6-luna',
        ],
        'gpt-5.5-pro' => [
            'label' => 'GPT-5.5 Pro',
            'vendor' => 'OpenAI',
            'description' => 'PRO-версия GPT-5.5 · максимум качества',
            'large' => 'gpt-5.5-pro',
        ],
        'gpt-5.5' => [
            'label' => 'GPT-5.5',
            'vendor' => 'OpenAI',
            'description' => 'По умолчанию · новейшая флагманская модель',
            'large' => 'gpt-5.5',
        ],
        'gpt-5.4' => [
            'label' => 'GPT-5.4',
            'vendor' => 'OpenAI',
            'description' => 'Предыдущий флагман · сильная универсальная модель',
            'large' => 'gpt-5.4',
        ],
        'gpt-5.4-mini' => [
            'label' => 'GPT-5.4 mini',
            'vendor' => 'OpenAI',
            'description' => 'Быстрая и экономичная · для простых правок',
            'large' => 'gpt-5.4',
        ],
        'gpt-4.1' => [
            'label' => 'GPT-4.1',
            'vendor' => 'OpenAI',
            'description' => 'Стабильная модель для повседневных задач',
            'large' => 'gpt-4.1',
        ],
        'gpt-4o' => [
            'label' => 'GPT-4o',
            'vendor' => 'OpenAI',
            'description' => 'Быстрая мультимодальная модель',
            'large' => 'gpt-4o',
        ],
        'claude-sonnet-4-0' => [
            'label' => 'Claude Sonnet 4',
            'vendor' => 'Anthropic',
            'description' => 'Хороший баланс качества и скорости',
            'large' => 'claude-sonnet-4-0',
        ],
        'claude-opus-4-0' => [
            'label' => 'Claude Opus 4',
            'vendor' => 'Anthropic',
            'description' => 'Максимальное качество для сложных ТЗ',
            'large' => 'claude-opus-4-0',
        ],
        'deepseek-chat' => [
            'label' => 'DeepSeek Chat',
            'vendor' => 'DeepSeek',
            'description' => 'Экономичный вариант для чата и правок',
            'large' => 'deepseek-chat',
        ],
        'GigaChat-Pro' => [
            'label' => 'GigaChat Pro',
            'vendor' => 'Sber',
            'description' => 'Российская модель · хорошо с русским языком',
            'large' => 'GigaChat-Pro',
        ],
        'yandexgpt' => [
            'label' => 'YandexGPT',
            'vendor' => 'Yandex',
            'description' => 'Российская модель Yandex Cloud',
            'large' => 'yandexgpt',
        ],
    ],
    // 0 = не передавать max_tokens в API (без искусственного усечения ответа).
    'max_tokens' => (int) env('F5AI_MAX_TOKENS', 0),
    'max_tokens_report' => (int) env('F5AI_MAX_TOKENS_REPORT', 0),
    'max_tokens_amocrm_audit' => (int) env('F5AI_MAX_TOKENS_AMOCRM_AUDIT', 0),
    'max_tokens_changes' => (int) env('F5AI_MAX_TOKENS_CHANGES', 0),
    'max_tokens_meeting' => (int) env('F5AI_MAX_TOKENS_MEETING', 0),
    'timeout' => (int) env('F5AI_TIMEOUT', 300),
    'timeout_tz_plan' => (int) env('F5AI_TIMEOUT_TZ_PLAN', 600),
    'timeout_kp_work_stages' => (int) env('F5AI_TIMEOUT_KP_WORK_STAGES', 300),
    'stale_minutes' => (int) env('F5AI_STALE_MINUTES', 8),
    'stale_minutes_tz_plan' => (int) env('F5AI_STALE_MINUTES_TZ_PLAN', 30),
    'stale_minutes_kp_work_stages' => (int) env('F5AI_STALE_MINUTES_KP_WORK_STAGES', 20),
    'sync_processing' => (bool) env('F5AI_SYNC_PROCESSING', false),
    'php_binary' => env('F5AI_PHP_BINARY'),
    /*
     * OCR для скриншотов в чате. F5AI chat/completions сейчас отбрасывает image_url,
     * поэтому текст со скрина передаём в промпт через tesseract.
     */
    'ocr_binary' => env('F5AI_OCR_BINARY', 'tesseract'),
    'ocr_languages' => env('F5AI_OCR_LANGS', 'rus+eng'),
    'ocr_timeout' => (int) env('F5AI_OCR_TIMEOUT', 90),
    /** Ставка часа для сметы этапов работ (₽). Суммы кратны этой ставке. */
    'work_stage_hourly_rate' => (int) env('F5AI_WORK_STAGE_HOURLY_RATE', 5100),
];

