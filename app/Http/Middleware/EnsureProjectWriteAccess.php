<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectWriteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $project = $request->route('project');

        if (! $project instanceof Project) {
            return $next($request);
        }

        if ($request->routeIs('projects.destroy', 'projects.restore')) {
            abort_unless(
                $project->canBeDeletedBy($request->user()),
                403,
                $request->routeIs('projects.restore')
                    ? 'Восстановить проект может только ответственный менеджер.'
                    : 'Удалить проект может только ответственный менеджер.'
            );

            return $next($request);
        }

        abort_unless(
            $project->canBeEditedBy($request->user()),
            403,
            'Недостаточно прав для изменения проекта.'
        );

        return $next($request);
    }
}
