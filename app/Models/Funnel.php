<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Funnel extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'sort_order',
        'amocrm_id',
        'canvas_offset_x',
        'canvas_offset_y',
        'canvas_zoom',
        'board_widgets',
        'share_token',
        'share_expires_at',
        'share_password',
        'share_comments_enabled',
    ];

    protected $hidden = [
        'share_password',
    ];

    protected function casts(): array
    {
        return [
            'canvas_zoom' => 'float',
            'board_widgets' => 'array',
            'share_expires_at' => 'datetime',
            'share_comments_enabled' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FunnelStage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function boardComments(): HasMany
    {
        return $this->hasMany(FunnelBoardComment::class)->orderByDesc('id');
    }

    public function ensureShareToken(): string
    {
        if (filled($this->share_token)) {
            return (string) $this->share_token;
        }

        do {
            $token = Str::lower(Str::random(48));
        } while (static::query()->where('share_token', $token)->exists());

        $this->forceFill(['share_token' => $token])->save();

        return $token;
    }

    public function shareUrl(): ?string
    {
        if (! filled($this->share_token)) {
            return null;
        }

        return route('funnels.board.public', ['token' => $this->share_token]);
    }

    public function isShareExpired(): bool
    {
        return $this->share_expires_at !== null && $this->share_expires_at->isPast();
    }

    public function hasSharePassword(): bool
    {
        return filled($this->share_password);
    }

    public function commentsEnabled(): bool
    {
        return $this->share_comments_enabled !== false;
    }

    public function setSharePassword(?string $plain): void
    {
        $plain = trim((string) $plain);

        $this->forceFill([
            'share_password' => $plain !== '' ? Hash::make($plain) : null,
        ])->save();
    }

    public function checkSharePassword(?string $plain): bool
    {
        if (! $this->hasSharePassword()) {
            return true;
        }

        return is_string($plain) && Hash::check($plain, (string) $this->share_password);
    }

    public function revokeShare(): void
    {
        $this->forceFill([
            'share_token' => null,
            'share_expires_at' => null,
            'share_password' => null,
            'share_comments_enabled' => true,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function shareSettingsForFrontend(): array
    {
        return [
            'url' => $this->shareUrl(),
            'has_password' => $this->hasSharePassword(),
            'comments_enabled' => $this->commentsEnabled(),
            'expires_at' => $this->share_expires_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'expires_at_label' => $this->share_expires_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'is_expired' => $this->isShareExpired(),
        ];
    }
}
