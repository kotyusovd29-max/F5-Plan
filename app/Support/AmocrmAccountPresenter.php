<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectAmocrmAccount;
use App\Services\AmoCrmAiSeedService;

class AmocrmAccountPresenter
{
    /**
     * @return array{
     *     connected: bool,
     *     subdomain: ?string,
     *     account_name: ?string,
     *     account_id: ?int,
     *     last_exported_at: ?string,
     *     last_export_result: ?array<string, mixed>,
     *     last_ai_audit_at: ?string,
     *     last_ai_audit_result: ?string,
     *     snapshot_at: ?string,
     *     snapshot_summary: array<string, mixed>|null,
     *     urls: array{
     *         connect: string,
     *         disconnect: string,
     *         snapshot: string,
     *         pipelines: string,
     *         import_pipelines: string,
     *         preview: string,
     *         audit: string,
     *         audit_stream: string,
     *         audit_docx: string,
     *         audit_google_docs: string,
     *         export: string,
     *         ai_seed_preview: string,
     *         ai_seed: string
     *     },
     *     ai_seed_templates: list<array{id: string, label: string, message: string}>
     * }
     */
    public static function forFrontend(Project $project): array
    {
        $account = $project->amocrmAccount;
        $snapshot = is_array($account?->account_snapshot) ? $account->account_snapshot : null;

        return [
            'connected' => $account instanceof ProjectAmocrmAccount,
            'subdomain' => $account?->subdomain,
            'account_name' => $account?->account_name,
            'account_id' => $account?->account_id,
            'last_exported_at' => $account?->last_exported_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'last_export_result' => $account?->last_export_result,
            'last_ai_audit_at' => $account?->last_ai_audit_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'last_ai_audit_result' => $account?->last_ai_audit_result,
            'snapshot_at' => $account?->account_snapshot_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            'snapshot_summary' => is_array($snapshot['summary'] ?? null) ? $snapshot['summary'] : null,
            'ai_seed_templates' => AmoCrmAiSeedService::templates(),
            'urls' => [
                'connect' => route('projects.amocrm.connect', $project),
                'disconnect' => route('projects.amocrm.disconnect', $project),
                'snapshot' => route('projects.amocrm.snapshot', $project),
                'pipelines' => route('projects.amocrm.pipelines', $project),
                'import_pipelines' => route('projects.amocrm.import-pipelines', $project),
                'preview' => route('projects.amocrm.preview', $project),
                'audit' => route('projects.amocrm.audit', $project),
                'audit_stream' => route('projects.amocrm.audit.stream', $project),
                'audit_docx' => route('projects.amocrm.audit-docx', $project),
                'audit_google_docs' => route('projects.amocrm.audit-google-docs', $project),
                'export' => route('projects.amocrm.export', $project),
                'ai_seed_preview' => route('projects.amocrm.ai-seed.preview', $project),
                'ai_seed' => route('projects.amocrm.ai-seed', $project),
            ],
        ];
    }
}
