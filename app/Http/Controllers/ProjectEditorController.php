<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProjectEditorsRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\RedirectResponse;

class ProjectEditorController extends Controller
{
    public function update(
        UpdateProjectEditorsRequest $request,
        Project $project,
        UserNotificationService $notifications
    ): RedirectResponse {
        $ids = collect($request->validated('user_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $project->user_id)
            ->values()
            ->all();

        $previousIds = $project->editors()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $newlyGranted = array_values(array_diff($ids, $previousIds));

        $project->editors()->sync($ids);

        if ($newlyGranted !== []) {
            User::query()
                ->whereIn('id', $newlyGranted)
                ->get()
                ->each(fn (User $editor) => $notifications->notifyEditorAccess($project, $editor));
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Список сотрудников с правом редактирования обновлён.');
    }
}
