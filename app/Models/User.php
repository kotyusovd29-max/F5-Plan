<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'role', 'rop_id', 'ai_model'])]
#[Hidden([
    'password',
    'remember_token',
    'google_access_token',
    'google_refresh_token',
])]
class User extends Authenticatable
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_ROP = 'rop';

    public const ROLE_EMPLOYEE = 'employee';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_ROP,
        self::ROLE_EMPLOYEE,
    ];

    public const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Администратор',
        self::ROLE_ROP => 'РОП',
        self::ROLE_EMPLOYEE => 'Сотрудник',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
            'online_seconds_total' => 'integer',
            'online_seconds_today' => 'integer',
            'online_day' => 'date',
            'last_seen_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || (bool) $this->is_admin;
    }

    public function isRop(): bool
    {
        return $this->role === self::ROLE_ROP;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE || ($this->role === null && ! $this->isAdmin());
    }

    public function roleLabel(): string
    {
        if ($this->isAdmin()) {
            return self::ROLE_LABELS[self::ROLE_ADMIN];
        }

        return self::ROLE_LABELS[$this->role] ?? self::ROLE_LABELS[self::ROLE_EMPLOYEE];
    }

    public function hasGoogleConnected(): bool
    {
        return filled($this->google_refresh_token) || filled($this->google_access_token);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function editableProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_editors')
            ->withTimestamps();
    }

    public function rop(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rop_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(self::class, 'rop_id');
    }

    public function presenceSessions(): HasMany
    {
        return $this->hasMany(UserPresenceSession::class);
    }

    /**
     * @return list<int>
     */
    public function accessibleProjectOwnerIds(): array
    {
        if ($this->isAdmin()) {
            return [];
        }

        if ($this->isRop()) {
            return array_values(array_unique([
                (int) $this->id,
                ...$this->employees()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ]));
        }

        return [(int) $this->id];
    }

    /**
     * @param  array{role?: string, rop_id?: mixed, is_admin?: mixed}  $data
     * @return array{role: string, rop_id: int|null, is_admin: bool}
     */
    public static function normalizeRolePayload(array $data): array
    {
        $role = (string) ($data['role'] ?? self::ROLE_EMPLOYEE);

        if (! in_array($role, self::ROLES, true)) {
            $role = self::ROLE_EMPLOYEE;
        }

        $ropId = null;

        if ($role === self::ROLE_EMPLOYEE) {
            $ropId = isset($data['rop_id']) && $data['rop_id'] !== ''
                ? (int) $data['rop_id']
                : null;
        }

        return [
            'role' => $role,
            'rop_id' => $ropId,
            'is_admin' => $role === self::ROLE_ADMIN,
        ];
    }
}
