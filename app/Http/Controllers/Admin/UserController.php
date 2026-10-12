<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\AdminUserDirectory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(AdminUserDirectory $directory): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'groups' => $directory->groups(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'ropOptions' => $this->ropOptions(),
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create($request->userPayload());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Пользователь добавлен.');
    }

    public function edit(User $user): Response
    {
        $user->loadMissing('rop:id,name');

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->isAdmin() ? User::ROLE_ADMIN : $user->role,
                'role_label' => $user->roleLabel(),
                'is_admin' => $user->isAdmin(),
                'rop_id' => $user->rop_id,
                'rop_name' => $user->rop?->name,
            ],
            'isSelf' => $user->id === auth()->id(),
            'ropOptions' => $this->ropOptions($user->id),
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->userPayload();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Данные пользователя обновлены.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Нельзя удалить свой аккаунт.');
        }

        if ($user->isRop() && $user->employees()->exists()) {
            return back()->with('error', 'Нельзя удалить РОПа, пока к нему привязаны сотрудники.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Пользователь удалён.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return collect(User::ROLE_LABELS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function ropOptions(?int $excludeId = null): array
    {
        return User::query()
            ->where('role', User::ROLE_ROP)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }
}
