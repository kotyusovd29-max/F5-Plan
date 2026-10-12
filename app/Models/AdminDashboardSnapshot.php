<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminDashboardSnapshot extends Model
{
    protected $fillable = [
        'captured_at',
        'summary',
        'departments',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'summary' => 'array',
            'departments' => 'array',
        ];
    }

    public function userStats(): HasMany
    {
        return $this->hasMany(AdminDashboardUserStat::class, 'snapshot_id')->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    public function forFrontend(): array
    {
        $this->loadMissing('userStats');

        return [
            'captured_at' => $this->captured_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'summary' => $this->summary ?? [],
            'departments' => $this->departments ?? [],
            'users' => $this->userStats->map(fn (AdminDashboardUserStat $row) => [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'role' => $row->role,
                'role_label' => $row->role_label,
                'rop_name' => $row->rop_name,
                'is_online' => $row->is_online,
                'last_seen_label' => $row->last_seen_label,
                'online_today_label' => $row->online_today_label,
                'online_total_label' => $row->online_total_label,
                'projects_draft' => $row->projects_draft,
                'projects_pending' => $row->projects_pending,
                'projects_approved' => $row->projects_approved,
                'projects_implementation' => $row->projects_implementation,
                'projects_archived' => $row->projects_archived,
                'projects_total' => $row->projects_total,
                'projects_overdue' => $row->projects_overdue,
                'editor_projects' => $row->editor_projects,
                'ai_requests' => $row->ai_requests,
                'ai_responses' => $row->ai_responses,
                'last_ai_project' => $row->last_ai_project,
                'last_ai_thread_label' => $row->last_ai_thread_label,
                'last_ai_at' => $row->last_ai_at,
            ])->values()->all(),
        ];
    }
}
