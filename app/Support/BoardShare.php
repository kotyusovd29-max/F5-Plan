<?php

namespace App\Support;

use App\Models\Funnel;
use App\Models\Project;

class BoardShare
{
    /**
     * @return array{project: Project, token: string, legacy_funnel: ?Funnel}|null
     */
    public static function resolve(string $token): ?array
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $project = Project::query()
            ->where('board_share_token', $token)
            ->first();

        if ($project) {
            return [
                'project' => $project,
                'token' => $token,
                'legacy_funnel' => null,
            ];
        }

        $legacyFunnel = Funnel::query()
            ->where('share_token', $token)
            ->whereHas('project')
            ->with('project')
            ->first();

        if (! $legacyFunnel?->project) {
            return null;
        }

        return [
            'project' => $legacyFunnel->project,
            'token' => $token,
            'legacy_funnel' => $legacyFunnel,
        ];
    }

    public static function resolveOrFail(string $token): array
    {
        $resolved = self::resolve($token);

        abort_if($resolved === null, 404);

        return $resolved;
    }

    public static function activeFunnel(Project $project, ?int $funnelId = null, ?Funnel $legacyFunnel = null): Funnel
    {
        $query = $project->funnels();

        if ($funnelId) {
            $funnel = (clone $query)->whereKey($funnelId)->first();
            if ($funnel) {
                return $funnel;
            }
        }

        if ($legacyFunnel && (int) $legacyFunnel->project_id === (int) $project->id) {
            return $legacyFunnel;
        }

        $funnel = $query->first();

        abort_if($funnel === null, 404);

        return $funnel;
    }
}
