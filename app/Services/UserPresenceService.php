<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPresenceSession;
use Illuminate\Support\Facades\DB;

class UserPresenceService
{
    /** Сессия закрывается, если heartbeat не приходил дольше этого. */
    public const SESSION_GAP_SECONDS = 120;

    /** Максимум секунд, которые можно начислить за один heartbeat. */
    public const MAX_TICK_SECONDS = 60;

    public function heartbeat(User $user): array
    {
        $now = now();

        return DB::transaction(function () use ($user, $now) {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $session = UserPresenceSession::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->orderByDesc('last_seen_at')
                ->lockForUpdate()
                ->first();

            $delta = 0;

            if (
                $session
                && $session->last_seen_at
                && $session->last_seen_at->diffInSeconds($now) <= self::SESSION_GAP_SECONDS
            ) {
                $delta = min(
                    max(0, $session->last_seen_at->diffInSeconds($now)),
                    self::MAX_TICK_SECONDS
                );

                $session->update([
                    'last_seen_at' => $now,
                    'duration_seconds' => (int) $session->duration_seconds + $delta,
                ]);
            } else {
                if ($session) {
                    $session->update([
                        'ended_at' => $session->last_seen_at,
                    ]);
                }

                UserPresenceSession::query()->create([
                    'user_id' => $user->id,
                    'started_at' => $now,
                    'last_seen_at' => $now,
                    'duration_seconds' => 0,
                ]);
            }

            $today = $now->toDateString();
            $todaySeconds = (int) $user->online_seconds_today;

            if ($user->online_day?->toDateString() !== $today) {
                $todaySeconds = 0;
            }

            $todaySeconds += $delta;

            $user->forceFill([
                'online_seconds_total' => (int) $user->online_seconds_total + $delta,
                'online_seconds_today' => $todaySeconds,
                'online_day' => $today,
                'last_seen_at' => $now,
            ])->save();

            return [
                'online_seconds_total' => (int) $user->online_seconds_total,
                'online_seconds_today' => (int) $user->online_seconds_today,
                'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            ];
        });
    }

    public function isOnline(?User $user): bool
    {
        if (! $user?->last_seen_at) {
            return false;
        }

        return $user->last_seen_at->diffInSeconds(now()) <= self::SESSION_GAP_SECONDS;
    }

    public function todaySeconds(User $user): int
    {
        if ($user->online_day?->toDateString() !== now()->toDateString()) {
            return 0;
        }

        return (int) $user->online_seconds_today;
    }
}
