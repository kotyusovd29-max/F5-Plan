<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;

class F5AiModels
{
    public static function resolveUser(?Project $project = null, ?int $preferredUserId = null): ?User
    {
        if ($preferredUserId !== null) {
            $user = User::query()->find($preferredUserId);

            if ($user instanceof User) {
                return $user;
            }
        }

        $auth = auth()->user();

        if ($auth instanceof User) {
            return $auth;
        }

        if ($project !== null) {
            $project->loadMissing('user');

            if ($project->user instanceof User) {
                return $project->user;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{label: string, vendor: string, large?: string, description?: string}>
     */
    public static function catalog(): array
    {
        $catalog = config('f5ai.models', []);

        return is_array($catalog) ? $catalog : [];
    }

    /**
     * @return list<array{id: string, label: string, vendor: string, description: string|null}>
     */
    public static function optionsForFrontend(): array
    {
        $options = [];

        foreach (self::catalog() as $id => $meta) {
            if (! is_string($id) || $id === '' || ! is_array($meta)) {
                continue;
            }

            $options[] = [
                'id' => $id,
                'label' => (string) ($meta['label'] ?? $id),
                'vendor' => (string) ($meta['vendor'] ?? ''),
                'description' => isset($meta['description']) ? (string) $meta['description'] : null,
            ];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function allowedIds(): array
    {
        return array_keys(self::catalog());
    }

    public static function isAllowed(?string $modelId): bool
    {
        return is_string($modelId)
            && $modelId !== ''
            && array_key_exists($modelId, self::catalog());
    }

    public static function resolve(?User $user = null, bool $large = false): string
    {
        $preferred = $user?->ai_model;

        if (self::isAllowed($preferred)) {
            $meta = self::catalog()[$preferred];

            if ($large) {
                $largeId = $meta['large'] ?? $preferred;

                return is_string($largeId) && $largeId !== ''
                    ? $largeId
                    : (string) $preferred;
            }

            return (string) $preferred;
        }

        return $large
            ? (string) config('f5ai.model_large', config('f5ai.model', 'gpt-5.5'))
            : (string) config('f5ai.model', 'gpt-5.5');
    }

    public static function labelFor(?string $modelId): ?string
    {
        if (! self::isAllowed($modelId)) {
            return null;
        }

        return (string) (self::catalog()[$modelId]['label'] ?? $modelId);
    }
}
