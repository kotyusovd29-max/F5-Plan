<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAiApplySnapshot extends Model
{
    protected $fillable = [
        'project_id',
        'project_ai_message_id',
        'payload',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'restored_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ProjectAiMessage::class, 'project_ai_message_id');
    }

    public function canRestore(): bool
    {
        return $this->restored_at === null && is_array($this->payload) && $this->payload !== [];
    }
}
