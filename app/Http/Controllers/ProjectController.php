<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsForCanvas;
use App\Http\Controllers\Concerns\ExportsToGoogleDocs;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectAmocrmLicenseRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\UpdateProjectStatusRequest;
use App\Models\Project;
use App\Models\ProjectBriefDocument;
use App\Models\ProjectSkkField;
use App\Models\StarterPackage;
use App\Services\GoogleDocsExportService;
use App\Services\GoogleOAuthService;
use App\Services\ProjectCloner;
use App\Services\ProjectDocxExporter;
use App\Services\ProjectXlsxExporter;
use App\Services\StarterPackageApplier;
use App\Support\AmocrmAccountPresenter;
use App\Support\BriefDocumentPresenter;
use App\Support\F5WidgetCatalog;
use App\Support\IntegrationPresenter;
use App\Support\LibraryPresenter;
use App\Support\ProjectAccessPresenter;
use App\Support\ProjectAiPrompts;
use App\Support\ProjectAiUrls;
use App\Support\ProjectStatus;
use App\Support\SkkFieldPresenter;
use App\Support\StarterPackagePresenter;
use App\Support\WorkStagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    use ExportsToGoogleDocs;
    use RespondsForCanvas;

    public function create(): Response
    {
        return Inertia::render('Projects/Create', [
            'starterPackages' => StarterPackagePresenter::forCreateWizard(),
        ]);
    }

    public function store(StoreProjectRequest $request, StarterPackageApplier $applier): RedirectResponse
    {
        $data = $request->validated();
        $packageId = $data['starter_package_id'] ?? null;
        unset($data['starter_package_id']);

        $project = $request->user()->projects()->create([
            ...$data,
            'status' => Project::STATUS_DRAFT,
        ]);

        if ($packageId) {
            $package = StarterPackage::query()->active()->find($packageId);

            if ($package) {
                $applier->apply($project, $package);
            }
        }

        $message = $packageId
            ? 'Проект создан по стартовому пакету.'
            : 'Проект создан.';

        return redirect()
            ->route('projects.show', $project)
            ->with('success', $message);
    }

    public function show(Project $project): Response
    {
        $project->load([
            'user',
            'editors',
            'briefDocuments',
            'amocrmAccount',
            'funnels' => fn ($query) => $query->withCount('stages'),
            'funnels.stages',
            'integrations',
            'workStages',
            'skkFields.funnelStage.funnel',
        ]);

        return Inertia::render('Projects/Show', [
            'project' => $this->serializeProject($project),
            'editorOptions' => ProjectAccessPresenter::editorOptions($project),
            'amocrm' => AmocrmAccountPresenter::forFrontend($project),
            'skkSectionLabel' => ProjectSkkField::SECTION_LABEL,
            'funnelStages' => SkkFieldPresenter::funnelStageOptions($project),
            'skkSections' => $this->serializeSkkSections($project),
            'integrations' => $this->serializeIntegrations($project),
            'workStages' => $this->serializeWorkStages($project),
            'funnels' => $this->serializeFunnels($project),
            'f5WidgetCatalog' => F5WidgetCatalog::forFrontend(),
            'amocrmLicenses' => config('f5_estimate.amocrm_licenses'),
            'library' => [
                'funnels' => LibraryPresenter::funnelsForApply($project),
                'skk_fields' => LibraryPresenter::skkFieldsForApply($project),
            ],
            'briefDocuments' => $project->briefDocuments
                ->filter(fn ($document) => ($document->kind ?: ProjectBriefDocument::KIND_TZ) !== ProjectBriefDocument::KIND_CHAT)
                ->map(fn ($document) => BriefDocumentPresenter::forFrontend($document, $project))
                ->values()
                ->all(),
            'aiPrompts' => [
                'analysis' => ProjectAiPrompts::analysis(),
                'actions' => ProjectAiPrompts::actions(),
            ],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $project->update($request->validated());

        return $this->respondForCanvas(
            $request,
            redirect()
                ->route('projects.show', $project)
                ->with('success', 'Информация о проекте сохранена.'),
            'Информация о проекте сохранена.'
        );
    }

    public function updateStatus(UpdateProjectStatusRequest $request, Project $project): RedirectResponse
    {
        $status = $request->validated('status');

        if ($project->status !== $status) {
            $project->changeStatus($status);
        }

        return back()->with(
            'success',
            'Статус проекта: '.ProjectStatus::label($project->fresh()->status).'.'
        );
    }

    public function updateAmocrmLicense(UpdateProjectAmocrmLicenseRequest $request, Project $project): RedirectResponse
    {
        $project->update([
            'amocrm_license_tariff' => $request->validated('amocrm_license_tariff'),
        ]);

        return back()->with('success', 'Тариф лицензии amoCRM сохранён.');
    }

    public function clone(Project $project, ProjectCloner $cloner): RedirectResponse
    {
        $clone = $cloner->clone($project, request()->user());

        return redirect()
            ->route('projects.show', $clone)
            ->with('success', "Создана копия «{$clone->company_name}». Укажите данные нового клиента.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        abort_unless(
            $project->canBeDeletedBy(request()->user()),
            403,
            'Удалить проект может только ответственный менеджер.'
        );

        $companyName = $project->company_name;
        $project->delete();

        return redirect()
            ->route('home')
            ->with('success', "Проект «{$companyName}» удалён (можно восстановить из «Удалённые»).");
    }

    public function restore(Project $project): RedirectResponse
    {
        abort_unless(
            $project->canBeDeletedBy(request()->user()),
            403,
            'Восстановить проект может только ответственный менеджер.'
        );

        $project->restore();

        if ($project->status === Project::STATUS_ARCHIVED) {
            $project->changeStatus(Project::STATUS_DRAFT);
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Проект «{$project->company_name}» восстановлен.");
    }

    public function exportDocx(Project $project, ProjectDocxExporter $exporter): BinaryFileResponse
    {
        $tempFile = $exporter->build($project);

        return response()
            ->download($tempFile, $exporter->filename($project))
            ->deleteFileAfterSend();
    }

    public function exportXlsx(Project $project, ProjectXlsxExporter $exporter): BinaryFileResponse
    {
        $tempFile = $exporter->build($project);

        return response()
            ->download($tempFile, $exporter->filename($project))
            ->deleteFileAfterSend();
    }

    public function exportGoogleDocs(
        Project $project,
        ProjectDocxExporter $exporter,
        GoogleOAuthService $oauth,
        GoogleDocsExportService $googleDocs
    ): JsonResponse {
        $tempFile = $exporter->build($project);

        return $this->exportDocxPathToGoogleDocs(
            request()->user(),
            $project,
            $tempFile,
            $exporter->filename($project),
            $oauth,
            $googleDocs,
            ['source' => \App\Models\ProjectGoogleDocument::SOURCE_PROJECT_PLAN]
        );
    }

    public function exportGoogleSheets(
        Project $project,
        ProjectXlsxExporter $exporter,
        GoogleOAuthService $oauth,
        GoogleDocsExportService $googleDocs
    ): JsonResponse {
        $tempFile = $exporter->build($project);

        return $this->exportXlsxPathToGoogleSheets(
            request()->user(),
            $project,
            $tempFile,
            $exporter->filename($project),
            $oauth,
            $googleDocs,
            ['source' => \App\Models\ProjectGoogleDocument::SOURCE_ESTIMATE]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'company_name' => $project->company_name,
            'website' => $project->website,
            'business_sphere' => $project->business_sphere,
            'current_services' => $project->current_services,
            'employees_count' => $project->employees_count,
            'client_tasks' => $project->client_tasks,
            'amocrm_deal_url' => $project->amocrm_deal_url,
            'amocrm_license_tariff' => $project->amocrm_license_tariff,
            'author' => $project->user->name,
            'status' => $project->status ?? Project::STATUS_DRAFT,
            'status_label' => $project->statusLabel(),
            'status_options' => ProjectStatus::options(),
            'is_archived' => $project->isArchived(),
            'is_training' => (bool) $project->is_training,
            ...ProjectAccessPresenter::forUser($project, request()->user()),
            'urls' => [
                'home' => route('home'),
                'update' => route('projects.update', $project),
                'status_update' => route('projects.status.update', $project),
                'amocrm_license_update' => route('projects.amocrm-license.update', $project),
                'clone' => route('projects.clone', $project),
                'destroy' => route('projects.destroy', $project),
                'restore' => route('projects.restore', $project),
                'editors_update' => route('projects.editors.update', $project),
                'export_docx' => route('projects.export.docx', $project),
                'export_xlsx' => route('projects.export.xlsx', $project),
                'export_google_docs' => route('projects.export.google-docs', $project),
                'export_google_sheets' => route('projects.export.google-sheets', $project),
                'documents' => route('projects.documents.index', $project),
                ...ProjectAiUrls::forProject($project),
                'ai_generate' => route('projects.ai.generate', $project),
                'ai_apply' => route('projects.ai.apply', $project),
                'canvas' => route('projects.canvas.show', $project),
                'funnels_store' => route('projects.funnels.store', $project),
                'integrations_store' => route('projects.integrations.store', $project),
                'integrations_reorder' => route('projects.integrations.reorder', $project),
                'integrations_bulk_destroy' => route('projects.integrations.bulk-destroy', $project),
                'work_stages_store' => route('projects.work-stages.store', $project),
                'work_stages_reorder' => route('projects.work-stages.reorder', $project),
                'work_stages_bulk_destroy' => route('projects.work-stages.bulk-destroy', $project),
                'skk_fields_store' => route('projects.skk-fields.store', $project),
                'skk_fields_reorder' => route('projects.skk-fields.reorder', $project),
                'brief_documents_store' => route('projects.brief-documents.store', $project),
                'brief_documents_index' => route('projects.brief-documents.index', $project),
                'funnels_bulk_destroy' => route('projects.funnels.bulk-destroy', $project),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeSkkSections(Project $project): array
    {
        $grouped = $project->skkFields->groupBy('entity_type');

        return collect(ProjectSkkField::TYPES)->map(function (string $entityType) use ($project, $grouped) {
            $fields = $grouped->get($entityType, collect());

            return [
                'entity_type' => $entityType,
                'label' => ProjectSkkField::TYPE_LABELS[$entityType],
                'bulk_destroy_url' => route('projects.skk-fields.sections.destroy', [$project, $entityType]),
                'fields' => $fields->map(
                    fn ($field) => SkkFieldPresenter::forFrontend($field, $project)
                )->values(),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeIntegrations(Project $project): array
    {
        return $project->integrations->map(
            fn ($integration) => IntegrationPresenter::forFrontend($integration, $project)
        )->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeWorkStages(Project $project): array
    {
        return $project->workStages->map(
            fn ($stage) => WorkStagePresenter::forFrontend($stage, $project)
        )->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeFunnels(Project $project): array
    {
        return $project->funnels->map(fn ($funnel) => [
            'id' => $funnel->id,
            'name' => $funnel->name,
            'stages_count' => $funnel->stages_count,
            'stages_label' => $this->stagesLabel($funnel->stages_count),
            'show_url' => route('projects.funnels.show', [$project, $funnel]),
            'canvas_url' => route('projects.funnels.canvas.show', [$project, $funnel]),
            'destroy_url' => route('projects.funnels.destroy', [$project, $funnel]),
        ])->values()->all();
    }

    private function stagesLabel(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 'этап';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return 'этапа';
        }

        return 'этапов';
    }
}
