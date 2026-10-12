<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WidgetController;
use App\Http\Controllers\Admin\LibraryFunnelController;
use App\Http\Controllers\Admin\LibrarySkkFieldController;
use App\Http\Controllers\Admin\StarterPackageController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\FunnelCanvasController;
use App\Http\Controllers\FunnelController;
use App\Http\Controllers\FunnelStageController;
use App\Http\Controllers\PublicFunnelBoardController;
use App\Http\Controllers\GoogleAccountController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectAmocrmController;
use App\Http\Controllers\ProjectBriefDocumentController;
use App\Http\Controllers\ProjectCanvasController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectAiChatController;
use App\Http\Controllers\ProjectAiController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectEditorController;
use App\Http\Controllers\ProjectGoogleDocumentController;
use App\Http\Controllers\ProjectIntegrationController;
use App\Http\Controllers\ProjectLibraryController;
use App\Http\Controllers\ProjectSectionBulkDeleteController;
use App\Http\Controllers\ProjectSkkFieldController;
use App\Http\Controllers\ProjectWorkStageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::get('/b/{token}', [PublicFunnelBoardController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->name('funnels.board.public');
Route::post('/b/{token}/unlock', [PublicFunnelBoardController::class, 'unlock'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:20,1')
    ->name('funnels.board.public.unlock');
Route::post('/b/{token}/comments', [PublicFunnelBoardController::class, 'storeComment'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:20,1')
    ->name('funnels.board.public.comments.store');
Route::delete('/b/{token}/comments/{comment}', [PublicFunnelBoardController::class, 'destroyComment'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->whereNumber('comment')
    ->middleware('throttle:30,1')
    ->name('funnels.board.public.comments.destroy');

Route::middleware('auth')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/knowledge', [KnowledgeBaseController::class, 'index'])->name('knowledge.index');
    Route::post('/presence/heartbeat', [PresenceController::class, 'heartbeat'])
        ->middleware('throttle:120,1')
        ->name('presence.heartbeat');

    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::middleware('project.write')->group(function () {
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::put('/projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.status.update');
        Route::put('/projects/{project}/amocrm-license', [ProjectController::class, 'updateAmocrmLicense'])->name('projects.amocrm-license.update');
        Route::post('/projects/{project}/clone', [ProjectController::class, 'clone'])->name('projects.clone');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->withTrashed()->name('projects.restore');
        Route::put('/projects/{project}/editors', [ProjectEditorController::class, 'update'])->name('projects.editors.update');
        Route::get('/projects/{project}/canvas', [ProjectCanvasController::class, 'show'])->name('projects.canvas.show');
        Route::get('/projects/{project}/canvas/refresh', [ProjectCanvasController::class, 'refresh'])->name('projects.canvas.refresh');
        Route::put('/projects/{project}/canvas', [ProjectCanvasController::class, 'update'])->name('projects.canvas.update');
        Route::delete('/projects/{project}/canvas/blocks/{canvasBlock}', [ProjectCanvasController::class, 'destroyBlock'])->name('projects.canvas.blocks.destroy');
        Route::get('/projects/{project}/export/docx', [ProjectController::class, 'exportDocx'])->name('projects.export.docx');
        Route::get('/projects/{project}/export/xlsx', [ProjectController::class, 'exportXlsx'])->name('projects.export.xlsx');
        Route::post('/projects/{project}/export/google-docs', [ProjectController::class, 'exportGoogleDocs'])->name('projects.export.google-docs');
        Route::post('/projects/{project}/export/google-sheets', [ProjectController::class, 'exportGoogleSheets'])->name('projects.export.google-sheets');
        Route::post('/projects/{project}/amocrm/connect', [ProjectAmocrmController::class, 'connect'])->name('projects.amocrm.connect');
        Route::delete('/projects/{project}/amocrm', [ProjectAmocrmController::class, 'disconnect'])->name('projects.amocrm.disconnect');
        Route::post('/projects/{project}/amocrm/snapshot', [ProjectAmocrmController::class, 'refreshSnapshot'])->name('projects.amocrm.snapshot');
        Route::get('/projects/{project}/amocrm/pipelines', [ProjectAmocrmController::class, 'pipelines'])->name('projects.amocrm.pipelines');
        Route::post('/projects/{project}/amocrm/import-pipelines', [ProjectAmocrmController::class, 'importPipelines'])->name('projects.amocrm.import-pipelines');
        Route::get('/projects/{project}/amocrm/preview', [ProjectAmocrmController::class, 'preview'])->name('projects.amocrm.preview');
        Route::post('/projects/{project}/amocrm/audit', [ProjectAmocrmController::class, 'audit'])->name('projects.amocrm.audit');
        Route::post('/projects/{project}/amocrm/audit/stream', [ProjectAmocrmController::class, 'auditStream'])->name('projects.amocrm.audit.stream');
        Route::get('/projects/{project}/amocrm/audit/docx', [ProjectAmocrmController::class, 'exportAuditDocx'])->name('projects.amocrm.audit-docx');
        Route::post('/projects/{project}/amocrm/audit/google-docs', [ProjectAmocrmController::class, 'exportAuditGoogleDocs'])->name('projects.amocrm.audit-google-docs');
        Route::post('/projects/{project}/amocrm/export', [ProjectAmocrmController::class, 'export'])->name('projects.amocrm.export');
        Route::post('/projects/{project}/amocrm/ai-seed/preview', [ProjectAmocrmController::class, 'previewAiSeed'])->name('projects.amocrm.ai-seed.preview');
        Route::post('/projects/{project}/amocrm/ai-seed', [ProjectAmocrmController::class, 'applyAiSeed'])->name('projects.amocrm.ai-seed');
        Route::get('/projects/{project}/documents', [ProjectGoogleDocumentController::class, 'index'])->name('projects.documents.index');
        Route::delete('/projects/{project}/documents/{document}', [ProjectGoogleDocumentController::class, 'destroy'])->name('projects.documents.destroy');
        Route::post('/projects/{project}/ai/generate', [ProjectAiController::class, 'generate'])->name('projects.ai.generate');
        Route::post('/projects/{project}/ai/apply', [ProjectAiController::class, 'apply'])->name('projects.ai.apply');
        Route::post('/projects/{project}/ai/analyze', [ProjectAiChatController::class, 'analyze'])->name('projects.ai.analyze');
        Route::get('/projects/{project}/brief-documents', [ProjectBriefDocumentController::class, 'index'])->name('projects.brief-documents.index');
        Route::post('/projects/{project}/brief-documents', [ProjectBriefDocumentController::class, 'store'])->name('projects.brief-documents.store');
        Route::delete('/projects/{project}/brief-documents/{briefDocument}', [ProjectBriefDocumentController::class, 'destroy'])->name('projects.brief-documents.destroy');
        Route::post('/projects/{project}/brief-documents/{briefDocument}/plan', [ProjectBriefDocumentController::class, 'generatePlan'])->name('projects.brief-documents.generate-plan');
        Route::post('/projects/{project}/brief-documents/{briefDocument}/work-stages', [ProjectBriefDocumentController::class, 'generateWorkStages'])->name('projects.brief-documents.generate-work-stages');
        Route::get('/projects/{project}/ai/chat', [ProjectAiChatController::class, 'show'])->name('projects.ai.chat');
        Route::get('/projects/{project}/ai/chat/messages', [ProjectAiChatController::class, 'messages'])->name('projects.ai.chat.messages');
        Route::post('/projects/{project}/ai/chat/messages', [ProjectAiChatController::class, 'send'])->name('projects.ai.chat.send');
        Route::post('/projects/{project}/ai/chat/messages/{message}/cancel', [ProjectAiChatController::class, 'cancel'])->name('projects.ai.chat.cancel');
        Route::post('/projects/{project}/ai/chat/messages/{message}/apply', [ProjectAiChatController::class, 'apply'])->name('projects.ai.chat.apply');
        Route::post('/projects/{project}/ai/chat/messages/{message}/rollback', [ProjectAiChatController::class, 'rollback'])->name('projects.ai.chat.rollback');
        Route::get('/projects/{project}/ai/chat/messages/{message}/export/docx', [ProjectAiChatController::class, 'exportMeetingDocx'])->name('projects.ai.chat.export-docx');
        Route::post('/projects/{project}/ai/chat/messages/{message}/export/google-docs', [ProjectAiChatController::class, 'exportMeetingGoogleDocs'])->name('projects.ai.chat.export-google-docs');
        Route::get('/projects/{project}/ai/chat/messages/{message}/kp-pptx', [ProjectAiChatController::class, 'downloadKpPptx'])->name('projects.ai.chat.kp-pptx');

        Route::delete('/projects/{project}/skk-fields/sections/{entityType}', [ProjectSectionBulkDeleteController::class, 'destroySkkSection'])->name('projects.skk-fields.sections.destroy');
        Route::put('/projects/{project}/skk-fields/reorder', [ProjectSkkFieldController::class, 'reorder'])->name('projects.skk-fields.reorder');
        Route::post('/projects/{project}/skk-fields', [ProjectSkkFieldController::class, 'store'])->name('projects.skk-fields.store');
        Route::put('/projects/{project}/skk-fields/{skkField}', [ProjectSkkFieldController::class, 'update'])->name('projects.skk-fields.update');
        Route::delete('/projects/{project}/skk-fields/{skkField}', [ProjectSkkFieldController::class, 'destroy'])->name('projects.skk-fields.destroy');

        Route::delete('/projects/{project}/integrations/bulk', [ProjectSectionBulkDeleteController::class, 'destroyAllIntegrations'])->name('projects.integrations.bulk-destroy');
        Route::put('/projects/{project}/integrations/reorder', [ProjectIntegrationController::class, 'reorder'])->name('projects.integrations.reorder');
        Route::post('/projects/{project}/integrations', [ProjectIntegrationController::class, 'store'])->name('projects.integrations.store');
        Route::put('/projects/{project}/integrations/{integration}', [ProjectIntegrationController::class, 'update'])->name('projects.integrations.update');
        Route::delete('/projects/{project}/integrations/{integration}', [ProjectIntegrationController::class, 'destroy'])->name('projects.integrations.destroy');

        Route::delete('/projects/{project}/work-stages/bulk', [ProjectSectionBulkDeleteController::class, 'destroyAllWorkStages'])->name('projects.work-stages.bulk-destroy');
        Route::put('/projects/{project}/work-stages/reorder', [ProjectWorkStageController::class, 'reorder'])->name('projects.work-stages.reorder');
        Route::post('/projects/{project}/work-stages', [ProjectWorkStageController::class, 'store'])->name('projects.work-stages.store');
        Route::put('/projects/{project}/work-stages/{workStage}', [ProjectWorkStageController::class, 'update'])->name('projects.work-stages.update');
        Route::delete('/projects/{project}/work-stages/{workStage}', [ProjectWorkStageController::class, 'destroy'])->name('projects.work-stages.destroy');

        Route::delete('/projects/{project}/funnels/bulk', [ProjectSectionBulkDeleteController::class, 'destroyAllFunnels'])->name('projects.funnels.bulk-destroy');
        Route::post('/projects/{project}/funnels', [FunnelController::class, 'store'])->name('projects.funnels.store');
        Route::post('/projects/{project}/library/funnels/{libraryFunnel}/apply', [ProjectLibraryController::class, 'applyFunnel'])->name('projects.library.funnels.apply');
        Route::post('/projects/{project}/library/skk-fields/{librarySkkField}/apply', [ProjectLibraryController::class, 'applySkkField'])->name('projects.library.skk-fields.apply');
        Route::get('/projects/{project}/funnels/{funnel}/canvas', [FunnelCanvasController::class, 'show'])->name('projects.funnels.canvas.show');
        Route::get('/projects/{project}/funnels/{funnel}/canvas/refresh', [FunnelCanvasController::class, 'refresh'])->name('projects.funnels.canvas.refresh');
        Route::put('/projects/{project}/funnels/{funnel}/canvas', [FunnelCanvasController::class, 'update'])->name('projects.funnels.canvas.update');
        Route::post('/projects/{project}/funnels/{funnel}/canvas/share', [FunnelCanvasController::class, 'share'])->name('projects.funnels.canvas.share');
        Route::get('/projects/{project}/funnels/{funnel}', [FunnelController::class, 'show'])->name('projects.funnels.show');
        Route::put('/projects/{project}/funnels/{funnel}', [FunnelController::class, 'update'])->name('projects.funnels.update');
        Route::delete('/projects/{project}/funnels/{funnel}', [FunnelController::class, 'destroy'])->name('projects.funnels.destroy');

        Route::put('/projects/{project}/funnels/{funnel}/stages/reorder', [FunnelStageController::class, 'reorder'])->name('projects.funnels.stages.reorder');
        Route::post('/projects/{project}/funnels/{funnel}/stages', [FunnelStageController::class, 'store'])->name('projects.funnels.stages.store');
        Route::put('/projects/{project}/funnels/{funnel}/stages/{stage}', [FunnelStageController::class, 'update'])->name('projects.funnels.stages.update');
        Route::delete('/projects/{project}/funnels/{funnel}/stages/{stage}', [FunnelStageController::class, 'destroy'])->name('projects.funnels.stages.destroy');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/ai-model', [ProfileController::class, 'updateAiModel'])->name('profile.ai-model');

    Route::get('/google/connect', [GoogleAccountController::class, 'redirect'])->name('google.connect');
    Route::get('/google/callback', [GoogleAccountController::class, 'callback'])->name('google.callback');
    Route::post('/google/disconnect', [GoogleAccountController::class, 'disconnect'])->name('google.disconnect');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/dashboard/refresh', [DashboardController::class, 'refresh'])->name('dashboard.refresh');
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('widgets', WidgetController::class)->except(['show']);
        Route::resource('starter-packages', StarterPackageController::class)
            ->parameters(['starter-packages' => 'starterPackage'])
            ->except(['show']);
        Route::resource('library/funnels', LibraryFunnelController::class)
            ->parameters(['funnels' => 'libraryFunnel'])
            ->names('library.funnels')
            ->except(['show']);
        Route::resource('library/skk-fields', LibrarySkkFieldController::class)
            ->parameters(['skk-fields' => 'librarySkkField'])
            ->names('library.skk-fields')
            ->except(['show']);
    });
});
