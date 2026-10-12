<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    public const TYPE_AI_READY = 'ai_response_ready';

    public const TYPE_AI_FAILED = 'ai_response_failed';

    public const TYPE_EDITOR_ACCESS = 'editor_access_granted';

    public const TYPE_BOARD_COMMENT = 'board_comment';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'url',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->update(['read_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontend(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            'dismiss_url' => route('notifications.dismiss', $this),
        ];
    }
}
