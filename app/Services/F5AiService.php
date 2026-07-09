<?php

namespace App\Services;

use App\Exceptions\F5AiException;
use Illuminate\Support\Facades\Http;

class F5AiService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chatCompletion(array $messages, array $options = []): string
    {
        $apiKey = config('f5ai.api_key');

        if (empty($apiKey)) {
            throw new F5AiException('F5AI API ключ не настроен. Добавьте F5AI_API_KEY в файл .env.');
        }

        $timeout = (int) ($options['timeout'] ?? config('f5ai.timeout', 300));

        $payload = [
            'model' => $options['model'] ?? config('f5ai.model'),
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.4,
            'max_tokens' => (int) ($options['max_tokens'] ?? config('f5ai.max_tokens', 8000)),
        ];

        $response = Http::timeout($timeout)
            ->withHeaders([
                'X-Auth-Token' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post(rtrim((string) config('f5ai.api_url'), '/').'/chat/completions', $payload);

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->json('error.message')
                ?? $response->json('error')
                ?? $response->body();

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE);
            }

            throw new F5AiException('Ошибка F5AI API: '.$message);
        }

        return $this->extractContent($response->json());
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function extractContent(?array $data): string
    {
        if ($data === null) {
            throw new F5AiException('F5AI API вернул пустой ответ.');
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return (string) $data['choices'][0]['message']['content'];
        }

        if (isset($data['message']['content'])) {
            return (string) $data['message']['content'];
        }

        throw new F5AiException('Не удалось извлечь текст ответа из ответа F5AI API.');
    }
}
