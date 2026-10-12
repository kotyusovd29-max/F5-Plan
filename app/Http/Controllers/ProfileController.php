<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAiModelRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Support\F5AiModels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = auth()->user();

        $user->loadMissing('rop:id,name');

        return Inertia::render('Profile/Index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'role' => $user->isAdmin() ? 'admin' : $user->role,
                'role_label' => $user->roleLabel(),
                'rop_name' => $user->rop?->name,
                'created_at' => $user->created_at->format('d.m.Y'),
                'google_connected' => $user->hasGoogleConnected(),
                'google_email' => $user->google_email,
            ],
            'google' => [
                'configured' => filled(config('services.google.client_id'))
                    && filled(config('services.google.client_secret')),
                'connect_url' => route('google.connect'),
                'disconnect_url' => route('google.disconnect'),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'password']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $request->user()->update($data);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Данные аккаунта сохранены.');
    }

    public function updateAiModel(UpdateAiModelRequest $request): JsonResponse|RedirectResponse
    {
        $aiModel = $request->validated('ai_model');

        $request->user()->update([
            'ai_model' => $aiModel,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'ai_model' => $aiModel,
                'ai_model_label' => F5AiModels::labelFor($aiModel)
                    ?? (F5AiModels::labelFor((string) config('f5ai.model', 'gpt-5.5')) ?? 'По умолчанию'),
            ]);
        }

        return back()->with('success', 'Модель ИИ сохранена.');
    }
}
