<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RopTeamDashboard
{
    /** @var array<string, int> days after which a project in this status is overdue */
    public const SLA_DAYS = [
        Project::STATUS_DRAFT => 7,
        Project::STATUS_PENDING_APPROVAL => 3,
        Project::STATUS_APPROVED => 5,
        Project::STATUS_IN_IMPLEMENTATION => 21,
    ];

    /** @var list<string> */
    public const FUNNEL_STATUSES = [
        Project::STATUS_DRAFT,
        Project::STATUS_PENDING_APPROVAL,
        Project::STATUS_APPROVED,
        Project::STATUS_IN_IMPLEMENTATION,
    ];

    /**
     * @return array{
     *     funnel: list<array<string, mixed>>,
     *     conversion_to_implementation_pct: float|null,
     *     sla: array{overdue_count: int, thresholds: list<array<string, mixed>>, avg_days_by_status: list<array<string, mixed>>},
     *     employees: list<array<string, mixed>>,
     *     total_active: int
     * }
     */
    public static function forRop(User $rop): array
    {
        $ownerIds = $rop->accessibleProjectOwnerIds();

        $projects = Project::query()
            ->whereIn('user_id', $ownerIds)
            ->notArchived()
            ->get(['id', 'user_id', 'status', 'status_changed_at', 'created_at', 'company_name']);

        $employees = $rop->employees()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->keyBy('id');

        // Include ROP themselves in load table
        $members = collect([
            [
                'id' => $rop->id,
                'name' => $rop->name.' (вы)',
                'is_self' => true,
            ],
        ])->concat($employees->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'is_self' => false,
        ]));

        $countsByStatus = $projects->groupBy('status')->map->count();
        $funnel = self::buildFunnel($countsByStatus);
        $totalActive = $projects->count();

        $activeFunnelTotal = collect(self::FUNNEL_STATUSES)
            ->sum(fn (string $status) => (int) ($countsByStatus[$status] ?? 0));

        $implementationCount = (int) ($countsByStatus[Project::STATUS_IN_IMPLEMENTATION] ?? 0);
        $conversionPct = $activeFunnelTotal > 0
            ? round(($implementationCount / $activeFunnelTotal) * 100, 1)
            : null;

        return [
            'funnel' => $funnel,
            'conversion_to_implementation_pct' => $conversionPct,
            'sla' => self::buildSla($projects),
            'employees' => self::buildEmployeeLoad($members, $projects),
            'total_active' => $totalActive,
        ];
    }

    /**
     * @param  Collection<string, int>  $countsByStatus
     * @return list<array<string, mixed>>
     */
    private static function buildFunnel(Collection $countsByStatus): array
    {
        $funnel = [];
        $previousCount = null;

        foreach (self::FUNNEL_STATUSES as $status) {
            $count = (int) ($countsByStatus[$status] ?? 0);
            $fromPreviousPct = null;

            if ($previousCount !== null && $previousCount > 0) {
                $fromPreviousPct = round(($count / $previousCount) * 100, 1);
            }

            $funnel[] = [
                'status' => $status,
                'label' => Project::STATUS_LABELS[$status],
                'count' => $count,
                'from_previous_pct' => $fromPreviousPct,
                'filter_url' => route('home', [
                    'view' => 'team',
                    'status' => $status,
                ]),
            ];

            $previousCount = $count;
        }

        return $funnel;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array{overdue_count: int, thresholds: list<array<string, mixed>>, avg_days_by_status: list<array<string, mixed>>, overdue_projects: list<array<string, mixed>>}
     */
    private static function buildSla(Collection $projects): array
    {
        $now = Carbon::now();
        $overdue = [];
        $daysByStatus = [];

        foreach (self::FUNNEL_STATUSES as $status) {
            $daysByStatus[$status] = [];
        }

        foreach ($projects as $project) {
            $status = $project->status ?? Project::STATUS_DRAFT;

            if (! isset(self::SLA_DAYS[$status])) {
                continue;
            }

            $days = $project->daysInCurrentStatus($now);
            $daysByStatus[$status][] = $days;
            $threshold = self::SLA_DAYS[$status];

            if ($days > $threshold) {
                $overdue[] = [
                    'id' => $project->id,
                    'company_name' => $project->company_name,
                    'status' => $status,
                    'status_label' => Project::STATUS_LABELS[$status] ?? $status,
                    'days' => $days,
                    'threshold' => $threshold,
                    'url' => route('projects.show', $project),
                ];
            }
        }

        usort($overdue, fn (array $a, array $b) => $b['days'] <=> $a['days']);

        $thresholds = [];
        $avgDays = [];

        foreach (self::FUNNEL_STATUSES as $status) {
            $values = $daysByStatus[$status];
            $avg = $values === [] ? null : round(array_sum($values) / count($values), 1);

            $thresholds[] = [
                'status' => $status,
                'label' => Project::STATUS_LABELS[$status],
                'days' => self::SLA_DAYS[$status],
            ];

            $avgDays[] = [
                'status' => $status,
                'label' => Project::STATUS_LABELS[$status],
                'avg_days' => $avg,
                'count' => count($values),
            ];
        }

        return [
            'overdue_count' => count($overdue),
            'thresholds' => $thresholds,
            'avg_days_by_status' => $avgDays,
            'overdue_projects' => array_slice($overdue, 0, 8),
        ];
    }

    /**
     * @param  Collection<int, array{id: int, name: string, is_self: bool}>  $members
     * @param  Collection<int, Project>  $projects
     * @return list<array<string, mixed>>
     */
    private static function buildEmployeeLoad(Collection $members, Collection $projects): array
    {
        $now = Carbon::now();
        $byUser = $projects->groupBy('user_id');

        return $members->map(function (array $member) use ($byUser, $now) {
            $userProjects = $byUser->get($member['id'], collect());
            $byStatus = $userProjects->groupBy(fn (Project $project) => $project->status ?? Project::STATUS_DRAFT);

            $overdueCount = $userProjects->filter(function (Project $project) use ($now) {
                $status = $project->status ?? Project::STATUS_DRAFT;
                $threshold = self::SLA_DAYS[$status] ?? null;

                return $threshold !== null && $project->daysInCurrentStatus($now) > $threshold;
            })->count();

            return [
                'id' => $member['id'],
                'name' => $member['name'],
                'is_self' => $member['is_self'],
                'total' => $userProjects->count(),
                'draft' => $byStatus->get(Project::STATUS_DRAFT, collect())->count(),
                'pending_approval' => $byStatus->get(Project::STATUS_PENDING_APPROVAL, collect())->count(),
                'approved' => $byStatus->get(Project::STATUS_APPROVED, collect())->count(),
                'in_implementation' => $byStatus->get(Project::STATUS_IN_IMPLEMENTATION, collect())->count(),
                'overdue' => $overdueCount,
                'filter_url' => route('home', [
                    'view' => 'team',
                    'user' => $member['id'],
                    'status' => 'active',
                ]),
            ];
        })->values()->all();
    }
}
