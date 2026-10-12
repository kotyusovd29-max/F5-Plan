<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminDashboardUserStat extends Model
{
    protected $fillable = [
        'snapshot_id',
        'user_id',
        'name',
        'email',
        'role',
        'role_label',
        'rop_name',
        'is_online',
        'last_seen_label',
        'online_seconds_today',
        'online_today_label',
        'online_seconds_total',
        'online_total_label',
        'projects_draft',
        'projects_pending',
        'projects_approved',
        'projects_implementation',
        'projects_archived',
        'projects_total',
        'projects_overdue',
        'editor_projects',
        'ai_requests',
        'ai_responses',
        'last_ai_project',
        'last_ai_thread_label',
        'last_ai_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'online_seconds_today' => 'integer',
            'online_seconds_total' => 'integer',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(AdminDashboardSnapshot::class, 'snapshot_id');
    }
}
