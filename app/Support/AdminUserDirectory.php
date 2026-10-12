<?php

namespace App\Support;

use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Support\Collection;

class AdminUserDirectory
{
    public function __construct(
        private readonly UserPresenceService $presence
    ) {}

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     subtitle: string|null,
     *     kind: string,
     *     team_online_seconds_total: int,
     *     team_online_total_label: string,
     *     team_online_seconds_today: int,
     *     team_online_today_label: string,
     *     users: list<array<string, mixed>>
     * }>
     */
    public function groups(): array
    {
        $users = User::query()
            ->with('rop:id,name')
            ->orderBy('name')
            ->get();

        $admins = $users->filter(fn (User $user) => $user->isAdmin())->values();
        $rops = $users->filter(fn (User $user) => $user->isRop())->values();
        $employees = $users->filter(fn (User $user) => $user->isEmployee())->values();

        $groups = [];

        if ($admins->isNotEmpty()) {
            $groups[] = $this->makeGroup(
                key: 'admins',
                title: 'Администраторы',
                subtitle: null,
                kind: 'admins',
                users: $admins
            );
        }

        $ropIds = $rops->pluck('id');

        foreach ($rops as $rop) {
            $team = collect([$rop])
                ->concat($employees->where('rop_id', $rop->id)->values())
                ->values();

            $groups[] = $this->makeGroup(
                key: 'rop-'.$rop->id,
                title: 'Отдел: '.$rop->name,
                subtitle: 'РОП и сотрудники',
                kind: 'rop',
                users: $team
            );
        }

        $orphans = $employees
            ->filter(fn (User $user) => blank($user->rop_id) || ! $ropIds->contains($user->rop_id))
            ->values();

        if ($orphans->isNotEmpty()) {
            $groups[] = $this->makeGroup(
                key: 'orphans',
                title: 'Сотрудники без РОП',
                subtitle: 'Нужно назначить руководителя',
                kind: 'orphans',
                users: $orphans
            );
        }

        return $groups;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{
     *     key: string,
     *     title: string,
     *     subtitle: string|null,
     *     kind: string,
     *     team_online_seconds_total: int,
     *     team_online_total_label: string,
     *     team_online_seconds_today: int,
     *     team_online_today_label: string,
     *     users: list<array<string, mixed>>
     * }
     */
    private function makeGroup(
        string $key,
        string $title,
        ?string $subtitle,
        string $kind,
        Collection $users
    ): array {
        $payload = $users
            ->map(fn (User $user) => $this->presentUser($user))
            ->values()
            ->all();

        $total = (int) collect($payload)->sum('online_seconds_total');
        $today = (int) collect($payload)->sum('online_seconds_today');

        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'kind' => $kind,
            'team_online_seconds_total' => $total,
            'team_online_total_label' => OnlineTimeFormatter::format($total),
            'team_online_seconds_today' => $today,
            'team_online_today_label' => OnlineTimeFormatter::format($today),
            'users' => $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentUser(User $user): array
    {
        $todaySeconds = $this->presence->todaySeconds($user);
        $totalSeconds = (int) $user->online_seconds_total;
        $isOnline = $this->presence->isOnline($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->isAdmin() ? User::ROLE_ADMIN : $user->role,
            'role_label' => $user->roleLabel(),
            'is_admin' => $user->isAdmin(),
            'rop_id' => $user->rop_id,
            'rop_name' => $user->rop?->name,
            'created_at' => $user->created_at?->format('d.m.Y'),
            'is_online' => $isOnline,
            'last_seen_at' => $user->last_seen_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'last_seen_label' => $isOnline
                ? 'Сейчас на сайте'
                : ($user->last_seen_at
                    ? 'Был: '.$user->last_seen_at->timezone(config('app.timezone'))->format('d.m.Y H:i')
                    : 'Ещё не заходил'),
            'online_seconds_total' => $totalSeconds,
            'online_total_label' => OnlineTimeFormatter::format($totalSeconds),
            'online_seconds_today' => $todaySeconds,
            'online_today_label' => OnlineTimeFormatter::format($todaySeconds),
        ];
    }
}
