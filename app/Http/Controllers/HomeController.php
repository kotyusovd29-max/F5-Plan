<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAiMessage;
use App\Models\User;
use App\Support\ProjectStatus;
use App\Support\RopTeamDashboard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $view = $request->query('view', 'mine');
        $filters = $this->filtersFromRequest($request);
        $employees = collect();
        $departments = [];
        $selectedUser = null;
        $projects = collect();

        if ($view === 'all' && $user->isAdmin()) {
            $departments = $this->departmentsForAllProjectsView();
            $employees = collect($departments)
                ->flatMap(fn (array $department) => $department['employees'])
                ->values();

            $selectedUserId = $request->query('user');

            if ($selectedUserId) {
                $selectedEmployee = $employees->firstWhere('id', (int) $selectedUserId);
                $selectedUser = $selectedEmployee
                    ? User::query()->find($selectedEmployee['id'])
                    : User::query()->whereHas('projects')->find($selectedUserId);

                if ($selectedUser) {
                    $projects = $this->filteredProjects(
                        Project::query()->where('user_id', $selectedUser->id),
                        $filters
                    )->latest()->get();
                }
            }
        } elseif ($view === 'shared') {
            $view = 'shared';
            $sharedFilters = $filters;
            if ($sharedFilters['status'] === 'deleted') {
                $sharedFilters['status'] = 'active';
            }
            $projects = $this->filteredProjects($this->sharedProjectsQuery($user), $sharedFilters)
                ->latest()
                ->get();
        } elseif ($view === 'team' && $user->isRop()) {
            $view = 'team';
            $employees = $user->employees()
                ->withCount(['projects' => fn (Builder $query) => $query->notArchived()])
                ->orderBy('name')
                ->get();

            $selectedUserId = $request->query('user');

            if ($selectedUserId) {
                $selectedUser = $employees->firstWhere('id', (int) $selectedUserId);

                if ($selectedUser) {
                    $projects = $this->filteredProjects(
                        Project::query()->where('user_id', $selectedUser->id),
                        $filters
                    )->latest()->get();
                }
            } else {
                $ownerIds = $user->accessibleProjectOwnerIds();
                $projects = $this->filteredProjects(
                    Project::query()->whereIn('user_id', $ownerIds),
                    $filters
                )->latest()->get();
            }
        } else {
            $view = 'mine';
            $projects = $this->filteredProjects($user->projects(), $filters)->latest()->get();
        }

        $sharedListFilters = $filters;
        if ($sharedListFilters['status'] === 'deleted') {
            $sharedListFilters['status'] = 'active';
        }
        $sharedProjects = $view === 'shared'
            ? $projects
            : $this->filteredProjects($this->sharedProjectsQuery($user), $sharedListFilters)
                ->latest()
                ->get();

        $canViewDeleted = $view === 'mine' || ($view === 'all' && $user->isAdmin());

        return Inertia::render('Home/Index', [
            'view' => $view,
            'filters' => $filters,
            'statusFilters' => ProjectStatus::filterOptions($canViewDeleted),
            'sphereOptions' => $this->sphereOptionsFor($user),
            'dashboard' => $this->dashboardFor($user),
            'teamDashboard' => $view === 'team' && $user->isRop()
                ? RopTeamDashboard::forRop($user)
                : null,
            'canViewAll' => $user->isAdmin(),
            'canViewTeam' => $user->isRop(),
            'sharedProjectsCount' => $sharedProjects->count(),
            'projects' => $projects->map(fn (Project $project) => $this->serializeProject($project))->values(),
            'sharedProjects' => $sharedProjects->map(fn (Project $project) => $this->serializeProject($project, true))->values(),
            'employees' => $view === 'all'
                ? []
                : $employees->map(fn (User $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'projects_count' => $employee->projects_count,
                ])->values(),
            'departments' => $departments,
            'selectedUser' => $selectedUser ? [
                'id' => $selectedUser->id,
                'name' => $selectedUser->name,
            ] : null,
        ]);
    }

    /**
     * Группы сотрудников для вкладки «Все проекты»: «Отдел {имя РОПа}».
     *
     * @return list<array{
     *     key: string,
     *     title: string,
     *     rop: ?array{id: int, name: string},
     *     employees: list<array{id: int, name: string, projects_count: int}>
     * }>
     */
    private function departmentsForAllProjectsView(): array
    {
        $users = User::query()
            ->whereHas('projects')
            ->with(['rop:id,name'])
            ->withCount(['projects' => fn (Builder $query) => $query->notArchived()])
            ->orderBy('name')
            ->get();

        $groups = [];

        foreach ($users as $member) {
            if ($member->isRop()) {
                $key = 'rop-'.$member->id;
                $title = 'Отдел '.$member->name;
                $rop = ['id' => $member->id, 'name' => $member->name];
            } elseif ($member->rop_id && $member->rop) {
                $key = 'rop-'.$member->rop_id;
                $title = 'Отдел '.$member->rop->name;
                $rop = ['id' => (int) $member->rop->id, 'name' => $member->rop->name];
            } else {
                $key = 'other';
                $title = 'Без отдела';
                $rop = null;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'title' => $title,
                    'rop' => $rop,
                    'employees' => [],
                ];
            }

            $groups[$key]['employees'][] = [
                'id' => $member->id,
                'name' => $member->name,
                'projects_count' => (int) $member->projects_count,
            ];
        }

        uasort($groups, function (array $left, array $right): int {
            if ($left['key'] === 'other') {
                return 1;
            }

            if ($right['key'] === 'other') {
                return -1;
            }

            return strcasecmp($left['title'], $right['title']);
        });

        return array_values($groups);
    }

    /**
     * @return array{
     *     q: string,
     *     status: string,
     *     sphere: string,
     *     date_from: string,
     *     date_to: string
     * }
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => $this->normalizeStatusFilter($request->query('status')),
            'sphere' => trim((string) $request->query('sphere', '')),
            'date_from' => $this->normalizeDate($request->query('date_from')),
            'date_to' => $this->normalizeDate($request->query('date_to')),
        ];
    }

    /**
     * @param  Builder<Project>|Relation<Project, *, *>  $query
     * @param  array{q: string, status: string, sphere: string, date_from: string, date_to: string}  $filters
     * @return Builder<Project>|Relation<Project, *, *>
     */
    private function filteredProjects(mixed $query, array $filters): mixed
    {
        $query->withoutTraining()
            ->withLifecycleStatus($filters['status'])
            ->withCount(['funnels', 'integrations', 'workStages', 'skkFields']);

        if ($filters['q'] !== '') {
            $query->search($filters['q']);
        }

        if ($filters['sphere'] !== '') {
            $query->where('business_sphere', $filters['sphere']);
        }

        if ($filters['date_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function sphereOptionsFor(User $user): array
    {
        $ownerIds = $user->isAdmin()
            ? null
            : ($user->isRop()
                ? $user->accessibleProjectOwnerIds()
                : [$user->id]);

        return Project::query()
            ->when($ownerIds !== null, fn (Builder $query) => $query->whereIn('user_id', $ownerIds))
            ->whereNotNull('business_sphere')
            ->where('business_sphere', '!=', '')
            ->orderBy('business_sphere')
            ->distinct()
            ->pluck('business_sphere')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     drafts_count: int,
     *     pending_approval_count: int,
     *     drafts_url: string,
     *     pending_approval_url: string,
     *     recent_ai: list<array<string, mixed>>
     * }
     */
    private function dashboardFor(User $user): array
    {
        $draftsCount = $user->projects()
            ->withoutTraining()
            ->where('status', Project::STATUS_DRAFT)
            ->count();

        $pendingCount = $user->projects()
            ->withoutTraining()
            ->where('status', Project::STATUS_PENDING_APPROVAL)
            ->count();

        $recentAi = ProjectAiMessage::query()
            ->where('role', ProjectAiMessage::ROLE_ASSISTANT)
            ->whereIn('status', [
                ProjectAiMessage::STATUS_COMPLETED,
                ProjectAiMessage::STATUS_PROCESSING,
            ])
            ->whereHas('project', fn (Builder $query) => $query->where('user_id', $user->id))
            ->with(['project:id,company_name'])
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(function (ProjectAiMessage $message) {
                $preview = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $message->content)) ?? '');
                if (mb_strlen($preview) > 120) {
                    $preview = mb_substr($preview, 0, 117).'…';
                }

                return [
                    'id' => $message->id,
                    'project_id' => $message->project_id,
                    'project_name' => $message->project?->company_name ?? 'Проект',
                    'thread' => $message->thread,
                    'thread_label' => $message->threadLabelShort(),
                    'preview' => $preview !== '' ? $preview : 'Ответ ИИ',
                    'created_at' => $message->created_at?->format('d.m.Y H:i'),
                    'url' => route('projects.ai.chat', $message->project_id),
                ];
            })
            ->values()
            ->all();

        return [
            'drafts_count' => $draftsCount,
            'pending_approval_count' => $pendingCount,
            'drafts_url' => route('home', ['view' => 'mine', 'status' => Project::STATUS_DRAFT]),
            'pending_approval_url' => route('home', ['view' => 'mine', 'status' => Project::STATUS_PENDING_APPROVAL]),
            'recent_ai' => $recentAi,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Project, User>
     */
    private function sharedProjectsQuery(User $user)
    {
        return $user->editableProjects()
            ->with('user:id,name')
            ->where('projects.user_id', '!=', $user->id);
    }

    private function normalizeStatusFilter(mixed $status): string
    {
        $status = is_string($status) ? $status : 'active';

        if (in_array($status, ['active', 'all', 'deleted'], true) || ProjectStatus::isValid($status)) {
            return $status;
        }

        return 'active';
    }

    private function normalizeDate(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project, bool $withManager = false): array
    {
        $data = [
            'id' => $project->id,
            'company_name' => $project->company_name,
            'business_sphere' => $project->business_sphere,
            'client_tasks' => $project->client_tasks,
            'employees_count' => $project->employees_count,
            'funnels_count' => (int) ($project->funnels_count ?? 0),
            'integrations_count' => (int) ($project->integrations_count ?? 0),
            'work_stages_count' => (int) ($project->work_stages_count ?? 0),
            'skk_fields_count' => (int) ($project->skk_fields_count ?? 0),
            'status' => $project->status ?? Project::STATUS_DRAFT,
            'status_label' => $project->statusLabel(),
            'is_archived' => $project->isArchived(),
            'is_deleted' => $project->trashed(),
            'created_at' => $project->created_at->format('d.m.Y'),
            'url' => $project->trashed()
                ? null
                : route('projects.show', $project),
            'restore_url' => $project->trashed()
                ? route('projects.restore', $project)
                : null,
        ];

        if ($withManager) {
            $project->loadMissing('user:id,name');
            $data['manager_name'] = $project->user?->name;
            $data['access_label'] = 'Доступ на редактирование';
        }

        return $data;
    }
}
