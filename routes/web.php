<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FunnelCanvasController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\FunnelStageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectCanvasController;
use App\Http\Controllers\ProjectAiChatController;
use App\Http\Controllers\ProjectAiController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectIntegrationController;
use App\Http\Controllers\ProjectSkkFieldController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('/projects/{project}/canvas', [ProjectCanvasController::class, 'show'])->name('projects.canvas.show');
    Route::get('/projects/{project}/canvas/refresh', [ProjectCanvasController::class, 'refresh'])->name('projects.canvas.refresh');
    Route::put('/projects/{project}/canvas', [ProjectCanvasController::class, 'update'])->name('projects.canvas.update');
    Route::delete('/projects/{project}/canvas/blocks/{canvasBlock}', [ProjectCanvasController::class, 'destroyBlock'])->name('projects.canvas.blocks.destroy');
    Route::get('/projects/{project}/export/docx', [ProjectController::class, 'exportDocx'])->name('projects.export.docx');
    Route::post('/projects/{project}/ai/generate', [ProjectAiController::class, 'generate'])->name('projects.ai.generate');
    Route::post('/projects/{project}/ai/apply', [ProjectAiController::class, 'apply'])->name('projects.ai.apply');
    Route::get('/projects/{project}/ai/chat', [ProjectAiChatController::class, 'show'])->name('projects.ai.chat');
    Route::post('/projects/{project}/ai/chat/messages', [ProjectAiChatController::class, 'send'])->name('projects.ai.chat.send');
    Route::post('/projects/{project}/ai/chat/messages/{message}/apply', [ProjectAiChatController::class, 'apply'])->name('projects.ai.chat.apply');

    Route::put('/projects/{project}/skk-fields/reorder', [ProjectSkkFieldController::class, 'reorder'])->name('projects.skk-fields.reorder');
    Route::post('/projects/{project}/skk-fields', [ProjectSkkFieldController::class, 'store'])->name('projects.skk-fields.store');
    Route::put('/projects/{project}/skk-fields/{skkField}', [ProjectSkkFieldController::class, 'update'])->name('projects.skk-fields.update');
    Route::delete('/projects/{project}/skk-fields/{skkField}', [ProjectSkkFieldController::class, 'destroy'])->name('projects.skk-fields.destroy');

    Route::put('/projects/{project}/integrations/reorder', [ProjectIntegrationController::class, 'reorder'])->name('projects.integrations.reorder');
    Route::post('/projects/{project}/integrations', [ProjectIntegrationController::class, 'store'])->name('projects.integrations.store');
    Route::put('/projects/{project}/integrations/{integration}', [ProjectIntegrationController::class, 'update'])->name('projects.integrations.update');
    Route::delete('/projects/{project}/integrations/{integration}', [ProjectIntegrationController::class, 'destroy'])->name('projects.integrations.destroy');

    Route::post('/projects/{project}/funnels', [FunnelController::class, 'store'])->name('projects.funnels.store');
    Route::get('/projects/{project}/funnels/{funnel}/canvas', [FunnelCanvasController::class, 'show'])->name('projects.funnels.canvas.show');
    Route::get('/projects/{project}/funnels/{funnel}/canvas/refresh', [FunnelCanvasController::class, 'refresh'])->name('projects.funnels.canvas.refresh');
    Route::put('/projects/{project}/funnels/{funnel}/canvas', [FunnelCanvasController::class, 'update'])->name('projects.funnels.canvas.update');
    Route::get('/projects/{project}/funnels/{funnel}', [FunnelController::class, 'show'])->name('projects.funnels.show');
    Route::put('/projects/{project}/funnels/{funnel}', [FunnelController::class, 'update'])->name('projects.funnels.update');
    Route::delete('/projects/{project}/funnels/{funnel}', [FunnelController::class, 'destroy'])->name('projects.funnels.destroy');

    Route::put('/projects/{project}/funnels/{funnel}/stages/reorder', [FunnelStageController::class, 'reorder'])->name('projects.funnels.stages.reorder');
    Route::post('/projects/{project}/funnels/{funnel}/stages', [FunnelStageController::class, 'store'])->name('projects.funnels.stages.store');
    Route::put('/projects/{project}/funnels/{funnel}/stages/{stage}', [FunnelStageController::class, 'update'])->name('projects.funnels.stages.update');
    Route::delete('/projects/{project}/funnels/{funnel}/stages/{stage}', [FunnelStageController::class, 'destroy'])->name('projects.funnels.stages.destroy');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });
});
