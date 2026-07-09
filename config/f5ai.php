<?php

return [
    'api_key' => env('F5AI_API_KEY'),
    'api_url' => env('F5AI_API_URL', 'https://api.f5ai.ru/v2'),
    'model' => env('F5AI_MODEL', 'gpt-5.4'),
    'model_large' => env('F5AI_MODEL_LARGE', env('F5AI_MODEL', 'gpt-5.4')),
    'max_tokens' => (int) env('F5AI_MAX_TOKENS', 8000),
    'max_tokens_report' => (int) env('F5AI_MAX_TOKENS_REPORT', 16000),
    'max_tokens_changes' => (int) env('F5AI_MAX_TOKENS_CHANGES', 12000),
    'timeout' => (int) env('F5AI_TIMEOUT', 300),
];
