<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Полная выгрузка данных amoCRM для AI-аудита аккаунта.
 * Хранится сутки (expires_at), затем удаляется — сам проект не затрагивается.
 */
class ProjectAmocrmAuditExport extends Model
{
    use Prunable;

    public const TTL_HOURS = 24;

    protected $fillable = [
        'project_id',
        'payload',
        'fetched_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}
