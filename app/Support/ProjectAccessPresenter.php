<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;

class ProjectAccessPresenter
{
    /**
     * @return array{
     *     can_edit: bool,
     *     can_manage_editors: bool,
     *     can_delete: bool,
     *     manager: array{id: int, name: string}|null,
     *     editors: list<array{id: int, name: string}>,
     *     editor_ids: list<int>
     * }
     */
    public static function forUser(Project $project, ?User $user): array
    {
        $project->loadMissing(['user:id,name,rop_id', 'editors:id,name']);

        return [
            'can_edit' => $project->canBeEditedBy($user),
            'can_manage_editors' => $project->canManageEditors($user),
            'can_delete' => $project->canBeDeletedBy($user),
            'manager' => $project->user ? [
                'id' => $project->user->id,
                'name' => $project->user->name,
            ] : null,
            'editors' => $project->editors
                ->map(fn (User $editor) => [
                    'id' => $editor->id,
                    'name' => $editor->name,
                ])
                ->values()
                ->all(),
            'editor_ids' => $project->editors->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, role_label: string}>
     */
    public static function editorOptions(Project $project): array
    {
        return User::query()
            ->where('id', '!=', $project->user_id)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'is_admin'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role_label' => $user->roleLabel(),
            ])
            ->values()
            ->all();
    }
}
