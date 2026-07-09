<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAiMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'project_id',
        'role',
        'content',
        'changes',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function proposalChanges(): ?array
    {
        $changes = $this->getAttribute('changes');

        return is_array($changes) ? $changes : null;
    }

    public function hasPendingChanges(): bool
    {
        return $this->role === self::ROLE_ASSISTANT
            && $this->applied_at === null
            && $this->changesHasOperations();
    }

    public function changesHasOperations(): bool
    {
        $changes = $this->proposalChanges();

        if ($changes === null) {
            return false;
        }

        foreach ($changes as $section => $operations) {
            if ($section === 'project') {
                if (! empty($operations['fields'])) {
                    return true;
                }

                continue;
            }

            if (! is_array($operations)) {
                continue;
            }

            foreach (['create', 'update', 'delete'] as $action) {
                if (! empty($operations[$action])) {
                    return true;
                }
            }
        }

        return false;
    }
}
