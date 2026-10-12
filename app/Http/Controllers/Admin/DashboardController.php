<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminUserDashboardRecorder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(AdminUserDashboardRecorder $recorder): Response
    {
        $snapshot = $recorder->latest() ?? $recorder->capture();

        return Inertia::render('Admin/Dashboard/Index', [
            'dashboard' => $snapshot->forFrontend(),
        ]);
    }

    public function refresh(AdminUserDashboardRecorder $recorder): RedirectResponse
    {
        $recorder->capture();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Дашборд обновлён и записан в базу.');
    }
}
