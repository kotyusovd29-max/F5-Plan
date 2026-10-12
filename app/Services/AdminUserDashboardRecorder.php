<?php

namespace App\Services;

use App\Models\AdminDashboardSnapshot;
use App\Models\AdminDashboardUserStat;
use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\User;
use App\Support\OnlineTimeFormatter;
use App\Support\RopTeamDashboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminUserDashboardRecorder
{
    public function __construct(
        private readonly UserPresenceService $presence
    ) {}

    public function latest(): ?AdminDashboardSnapshot
    {
        return AdminDashboardSnapshot::query()->latest('id')->first();
    }

    /**
     * Считает показатели и записывает их отдельным снимком.
     * Предыдущие снимки удаляются: в базе остаётся актуальный блок дашборда.
     */
    public function capture(): AdminDashboardSnapshot
    {
        $users = User::query()->with('rop:id,name')->orderBy('name')->get();
        $projects = Project::query()
            ->withoutTraining()
            ->get(['id', 'user_id', 'company_name', 'status', 'status_changed_at', 'created_at', 'is_training']);

        $projectsByOwner = $projects->groupBy('user_id');
        $editorCounts = $this->editorCounts();
        $aiByUser = $this->aiByUser();

        $rows = $users->map(function (User $user) use ($projectsByOwner, $editorCounts, $aiByUser) {
            return $this->rowFor($user, $projectsByOwner->get($user->id, collect()), $editorCounts, $aiByUser);
        })->values();

        $summary = $this->summary($users, $rows, $projects);
        $departments = $this->departments($users, $rows);

        return DB::transaction(function () use ($summary, $departments, $rows) {
            AdminDashboardUserStat::query()->delete();
            AdminDashboardSnapshot::query()->delete();

            $snapshot = AdminDashboardSnapshot::query()->create([
                'captured_at' => now(),
                'summary' => $summary,
                'departments' => $departments,
            ]);

            $snapshot->userStats()->createMany($rows->all());

            return $snapshot->fresh(['userStats']);
        });
    }

    /**
     * @return array<int, int>
     */
    private function editorCounts(): array
    {
        return DB::table('project_editors')
            ->join('projects', 'projects.id', '=', 'project_editors.project_id')
            ->where('projects.is_training', false)
            ->whereNull('projects.deleted_at')
            ->select('project_editors.user_id', DB::raw('count(*) as aggregate'))
            ->groupBy('project_editors.user_id')
            ->pluck('aggregate', 'user_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @return array<int, array{requests: int, responses: int, last: ?array{project: ?string, thread_label: string, at: ?string}}>
     */
    private function aiByUser(): array
    {
        $messages = ProjectAiMessage::query()
            ->whereNotNull('user_id')
            ->with('project:id,company_name')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'project_id', 'role', 'thread', 'created_at']);

        $grouped = [];

        foreach ($messages as $message) {
            $userId = (int) $message->user_id;

            if (! isset($grouped[$userId])) {
                $grouped[$userId] = [
                    'requests' => 0,
                    'responses' => 0,
                    'last' => [
                        'project' => $message->project?->company_name,
                        'thread_label' => $message->threadLabelShort(),
                        'at' => $message->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
                    ],
                ];
            }

            if ($message->role === ProjectAiMessage::ROLE_USER) {
                $grouped[$userId]['requests']++;
            }

            if ($message->role === ProjectAiMessage::ROLE_ASSISTANT) {
                $grouped[$userId]['responses']++;
            }
        }

        return $grouped;
    }

    /**
     * @param  Collection<int, Project>  $owned
     * @param  array<int, int>  $editorCounts
     * @param  array<int, array<string, mixed>>  $aiByUser
     * @return array<string, mixed>
     */
    private function rowFor(User $user, Collection $owned, array $editorCounts, array $aiByUser): array
    {
        $counts = [
            Project::STATUS_DRAFT => 0,
            Project::STATUS_PENDING_APPROVAL => 0,
            Project::STATUS_APPROVED => 0,
            Project::STATUS_IN_IMPLEMENTATION => 0,
            Project::STATUS_ARCHIVED => 0,
        ];
        $overdue = 0;

        foreach ($owned as $project) {
            $status = $project->status ?? Project::STATUS_DRAFT;

            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }

            $threshold = RopTeamDashboard::SLA_DAYS[$status] ?? null;

            if ($threshold !== null && $project->daysInCurrentStatus() > $threshold) {
                $overdue++;
            }
        }

        $today = $this->presence->todaySeconds($user);
        $total = (int) $user->online_seconds_total;
        $isOnline = $this->presence->isOnline($user);
        $ai = $aiByUser[$user->id] ?? null;
        $role = $user->isAdmin() ? User::ROLE_ADMIN : ($user->role ?: User::ROLE_EMPLOYEE);

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'role_label' => $user->roleLabel(),
            'rop_name' => $user->isEmployee() ? $user->rop?->name : null,
            'is_online' => $isOnline,
            'last_seen_label' => $isOnline
                ? 'Сейчас на сайте'
                : ($user->last_seen_at
                    ? 'Был: '.$user->last_seen_at->timezone(config('app.timezone'))->format('d.m.Y H:i')
                    : 'Ещё не заходил'),
            'online_seconds_today' => $today,
            'online_today_label' => OnlineTimeFormatter::format($today),
            'online_seconds_total' => $total,
            'online_total_label' => OnlineTimeFormatter::format($total),
            'projects_draft' => $counts[Project::STATUS_DRAFT],
            'projects_pending' => $counts[Project::STATUS_PENDING_APPROVAL],
            'projects_approved' => $counts[Project::STATUS_APPROVED],
            'projects_implementation' => $counts[Project::STATUS_IN_IMPLEMENTATION],
            'projects_archived' => $counts[Project::STATUS_ARCHIVED],
            'projects_total' => array_sum($counts),
            'projects_overdue' => $overdue,
            'editor_projects' => (int) ($editorCounts[$user->id] ?? 0),
            'ai_requests' => (int) ($ai['requests'] ?? 0),
            'ai_responses' => (int) ($ai['responses'] ?? 0),
            'last_ai_project' => $ai['last']['project'] ?? null,
            'last_ai_thread_label' => $ai['last']['thread_label'] ?? null,
            'last_ai_at' => $ai['last']['at'] ?? null,
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, Project>  $projects
     * @return array<string, mixed>
     */
    private function summary(Collection $users, Collection $rows, Collection $projects): array
    {
        $byStatus = $projects->groupBy(fn (Project $project) => $project->status ?? Project::STATUS_DRAFT);
        $overdue = (int) $rows->sum('projects_overdue');
        $today = (int) $rows->sum('online_seconds_today');
        $totalOnline = (int) $rows->sum('online_seconds_total');

        return [
            'users_total' => $users->count(),
            'admins_count' => $users->filter(fn (User $user) => $user->isAdmin())->count(),
            'rops_count' => $users->filter(fn (User $user) => $user->isRop())->count(),
            'employees_count' => $users->filter(fn (User $user) => $user->isEmployee())->count(),
            'employees_without_rop' => $users->filter(
                fn (User $user) => $user->isEmployee() && blank($user->rop_id)
            )->count(),
            'online_now' => (int) $rows->where('is_online', true)->count(),
            'online_seconds_today' => $today,
            'online_today_label' => OnlineTimeFormatter::format($today),
            'online_seconds_total' => $totalOnline,
            'online_total_label' => OnlineTimeFormatter::format($totalOnline),
            'projects_total' => $projects->count(),
            'projects_draft' => $byStatus->get(Project::STATUS_DRAFT, collect())->count(),
            'projects_pending' => $byStatus->get(Project::STATUS_PENDING_APPROVAL, collect())->count(),
            'projects_approved' => $byStatus->get(Project::STATUS_APPROVED, collect())->count(),
            'projects_implementation' => $byStatus->get(Project::STATUS_IN_IMPLEMENTATION, collect())->count(),
            'projects_archived' => $byStatus->get(Project::STATUS_ARCHIVED, collect())->count(),
            'projects_overdue' => $overdue,
            'ai_requests' => (int) $rows->sum('ai_requests'),
            'ai_responses' => (int) $rows->sum('ai_responses'),
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function departments(Collection $users, Collection $rows): array
    {
        $byId = $rows->keyBy('user_id');
        $departments = [];

        foreach ($users->filter(fn (User $user) => $user->isRop()) as $rop) {
            $memberIds = $users
                ->filter(fn (User $user) => $user->id === $rop->id || (int) $user->rop_id === (int) $rop->id)
                ->pluck('id');

            $departments[] = $this->departmentRow('Отдел: '.$rop->name, $memberIds, $byId);
        }

        $orphanIds = $users
            ->filter(fn (User $user) => $user->isEmployee() && blank($user->rop_id))
            ->pluck('id');

        if ($orphanIds->isNotEmpty()) {
            $departments[] = $this->departmentRow('Сотрудники без РОПа', $orphanIds, $byId);
        }

        return $departments;
    }

    /**
     * @param  Collection<int, int>  $memberIds
     * @param  Collection<int, array<string, mixed>>  $byId
     * @return array<string, mixed>
     */
    private function departmentRow(string $title, Collection $memberIds, Collection $byId): array
    {
        $members = $memberIds
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->values();

        $today = (int) $members->sum('online_seconds_today');

        return [
            'title' => $title,
            'users_count' => $members->count(),
            'online_now' => $members->where('is_online', true)->count(),
            'online_today_label' => OnlineTimeFormatter::format($today),
            'projects_total' => (int) $members->sum('projects_total'),
            'projects_overdue' => (int) $members->sum('projects_overdue'),
            'ai_requests' => (int) $members->sum('ai_requests'),
        ];
    }
}
