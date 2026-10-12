<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Project extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_IN_IMPLEMENTATION = 'in_implementation';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_IN_IMPLEMENTATION,
        self::STATUS_ARCHIVED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Черновик',
        self::STATUS_PENDING_APPROVAL => 'На согласовании',
        self::STATUS_APPROVED => 'Согласован',
        self::STATUS_IN_IMPLEMENTATION => 'В внедрении',
        self::STATUS_ARCHIVED => 'Архив',
    ];

    protected $fillable = [
        'user_id',
        'company_name',
        'website',
        'business_sphere',
        'current_services',
        'employees_count',
        'client_tasks',
        'amocrm_deal_url',
        'amocrm_license_tariff',
        'status',
        'is_training',
        'status_changed_at',
        'canvas_offset_x',
        'canvas_offset_y',
        'canvas_zoom',
        'board_share_token',
        'board_share_expires_at',
        'board_share_password',
        'board_share_comments_enabled',
    ];

    protected $hidden = [
        'board_share_password',
    ];

    protected function casts(): array
    {
        return [
            'canvas_zoom' => 'float',
            'is_training' => 'boolean',
            'status_changed_at' => 'datetime',
            'board_share_expires_at' => 'datetime',
            'board_share_comments_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if ($project->status === null || $project->status === '') {
                $project->status = self::STATUS_DRAFT;
            }

            if ($project->status_changed_at === null) {
                $project->status_changed_at = now();
            }
        });
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Days spent in the current status (based on status_changed_at).
     */
    public function daysInCurrentStatus(?\DateTimeInterface $now = null): int
    {
        $since = $this->status_changed_at ?? $this->created_at;

        if ($since === null) {
            return 0;
        }

        $now = $now ? \Illuminate\Support\Carbon::instance($now) : now();

        return max(0, (int) $since->diffInDays($now));
    }

    public function changeStatus(string $status): bool
    {
        if ($this->status === $status) {
            return false;
        }

        return $this->update([
            'status' => $status,
            'status_changed_at' => now(),
        ]);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $builder) use ($like) {
            $builder->where('company_name', 'like', $like)
                ->orWhere('business_sphere', 'like', $like)
                ->orWhere('client_tasks', 'like', $like)
                ->orWhere('website', 'like', $like)
                ->orWhere('current_services', 'like', $like);
        });
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithLifecycleStatus(Builder $query, ?string $status): Builder
    {
        if ($status === 'deleted') {
            return $query->onlyTrashed();
        }

        if ($status === null || $status === '' || $status === 'active') {
            return $query->notArchived();
        }

        if ($status === 'all') {
            return $query;
        }

        if (in_array($status, self::STATUSES, true)) {
            return $query->where('status', $status);
        }

        return $query->notArchived();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_editors')
            ->withTimestamps();
    }

    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->is_training) {
            return $user->isAdmin();
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        $ownerRopId = $this->relationLoaded('user')
            ? $this->user?->rop_id
            : $this->user()->value('rop_id');

        if ($ownerRopId && (int) $ownerRopId === (int) $user->id) {
            return true;
        }

        return $this->editors()->where('users.id', $user->id)->exists();
    }

    public function canManageEditors(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->is_training) {
            return $user->isAdmin();
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        $ownerRopId = $this->relationLoaded('user')
            ? $this->user?->rop_id
            : $this->user()->value('rop_id');

        return $ownerRopId && (int) $ownerRopId === (int) $user->id;
    }

    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->is_training) {
            return $user->isAdmin();
        }

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $this->user_id === (int) $user->id;
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithoutTraining(Builder $query): Builder
    {
        return $query->where('is_training', false);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeTraining(Builder $query): Builder
    {
        return $query->where('is_training', true);
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class)->orderBy('sort_order')->orderBy('id');
    }

    public function ensureBoardShareToken(): string
    {
        if (filled($this->board_share_token)) {
            return (string) $this->board_share_token;
        }

        do {
            $token = Str::lower(Str::random(48));
        } while (
            static::query()->where('board_share_token', $token)->exists()
            || Funnel::query()->where('share_token', $token)->exists()
        );

        $this->forceFill(['board_share_token' => $token])->save();

        return $token;
    }

    public function boardShareUrl(): ?string
    {
        if (! filled($this->board_share_token)) {
            return null;
        }

        return route('funnels.board.public', ['token' => $this->board_share_token]);
    }

    public function isBoardShareExpired(): bool
    {
        return $this->board_share_expires_at !== null && $this->board_share_expires_at->isPast();
    }

    public function hasBoardSharePassword(): bool
    {
        return filled($this->board_share_password);
    }

    public function boardCommentsEnabled(): bool
    {
        return $this->board_share_comments_enabled !== false;
    }

    public function setBoardSharePassword(?string $plain): void
    {
        $plain = trim((string) $plain);

        $this->forceFill([
            'board_share_password' => $plain !== '' ? Hash::make($plain) : null,
        ])->save();
    }

    public function checkBoardSharePassword(?string $plain): bool
    {
        if (! $this->hasBoardSharePassword()) {
            return true;
        }

        return is_string($plain) && Hash::check($plain, (string) $this->board_share_password);
    }

    public function revokeBoardShare(): void
    {
        $this->forceFill([
            'board_share_token' => null,
            'board_share_expires_at' => null,
            'board_share_password' => null,
            'board_share_comments_enabled' => true,
        ])->save();

        $this->funnels()->update([
            'share_token' => null,
            'share_expires_at' => null,
            'share_password' => null,
            'share_comments_enabled' => true,
        ]);
    }

    public function clearLegacyFunnelShareTokens(): void
    {
        $this->funnels()->update([
            'share_token' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function boardShareSettingsForFrontend(): array
    {
        return [
            'url' => $this->boardShareUrl(),
            'has_password' => $this->hasBoardSharePassword(),
            'comments_enabled' => $this->boardCommentsEnabled(),
            'expires_at' => $this->board_share_expires_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'expires_at_label' => $this->board_share_expires_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'is_expired' => $this->isBoardShareExpired(),
        ];
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(ProjectIntegration::class)->orderBy('sort_order')->orderBy('id');
    }

    public function workStages(): HasMany
    {
        return $this->hasMany(ProjectWorkStage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function skkFields(): HasMany
    {
        return $this->hasMany(ProjectSkkField::class)->orderBy('entity_type')->orderBy('sort_order')->orderBy('id');
    }

    public function canvasBlocks(): HasMany
    {
        return $this->hasMany(ProjectCanvasBlock::class)->orderBy('z_index')->orderBy('id');
    }

    public function aiMessages(): HasMany
    {
        return $this->hasMany(ProjectAiMessage::class)->orderBy('id');
    }

    public function briefDocuments(): HasMany
    {
        return $this->hasMany(ProjectBriefDocument::class)->latest();
    }

    public function googleDocuments(): HasMany
    {
        return $this->hasMany(ProjectGoogleDocument::class)->latest();
    }

    public function amocrmAccount(): HasOne
    {
        return $this->hasOne(ProjectAmocrmAccount::class);
    }

    public function amocrmAuditExports(): HasMany
    {
        return $this->hasMany(ProjectAmocrmAuditExport::class)->latest('id');
    }
}
