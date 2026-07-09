<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $view = $request->query('view', 'mine');
        $employees = collect();
        $selectedUser = null;
        $projects = collect();

        if ($view === 'all') {
            $employees = User::query()
                ->whereHas('projects')
                ->withCount('projects')
                ->orderBy('name')
                ->get();

            $selectedUserId = $request->query('user');

            if ($selectedUserId) {
                $selectedUser = $employees->firstWhere('id', (int) $selectedUserId)
                    ?? User::query()->whereHas('projects')->find($selectedUserId);

                if ($selectedUser) {
                    $projects = Project::query()
                        ->where('user_id', $selectedUser->id)
                        ->latest()
                        ->get();
                }
            }
        } else {
            $view = 'mine';
            $projects = $request->user()
                ->projects()
                ->latest()
                ->get();
        }

        return view('home', compact('projects', 'view', 'employees', 'selectedUser'));
    }
}
