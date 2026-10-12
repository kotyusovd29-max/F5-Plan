<?php

namespace App\Services;

use App\Exceptions\AmoCrmException;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Project;
use App\Models\ProjectAmocrmAccount;
use App\Models\ProjectSkkField;
use App\Support\AmoCrmMapper;
use Illuminate\Support\Facades\DB;

class AmoCrmAccountService
{
    public function __construct(
        private readonly AmoCrmAccountSnapshotService $snapshots
    ) {}

    /**
     * @return array{account: ProjectAmocrmAccount, account_name: string, account_id: int, snapshot_summary: array<string, mixed>}
     */
    public function connect(Project $project, string $subdomain, string $accessToken): array
    {
        $subdomain = AmoCrmMapper::normalizeSubdomain($subdomain);
        $accessToken = trim($accessToken);

        if ($subdomain === '' || ! preg_match('/^[a-z0-9][a-z0-9\-]*$/i', $subdomain)) {
            throw new AmoCrmException('Укажите корректный subdomain аккаунта amoCRM.');
        }

        if ($accessToken === '') {
            throw new AmoCrmException('Укажите access token amoCRM.');
        }

        $tempAccount = new ProjectAmocrmAccount([
            'subdomain' => $subdomain,
            'access_token' => $accessToken,
        ]);

        $client = new AmoCrmClient($tempAccount);
        $info = $client->fetchAccount();

        if ($info['id'] <= 0) {
            throw new AmoCrmException('Не удалось получить данные аккаунта amoCRM.');
        }

        $previousAccountId = $project->amocrmAccount?->account_id;

        $account = $project->amocrmAccount()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'subdomain' => $subdomain,
                'access_token' => $accessToken,
                'account_id' => $info['id'],
                'account_name' => $info['name'],
            ]
        );

        if ($previousAccountId !== null && (int) $previousAccountId !== (int) $info['id']) {
            $this->clearSyncIds($project);
            $account->forceFill([
                'last_exported_at' => null,
                'last_export_result' => null,
                'account_snapshot' => null,
                'account_snapshot_at' => null,
            ])->save();
        }

        $snapshotSummary = [];

        try {
            $snapshot = $this->snapshots->sync($project->fresh(['amocrmAccount']));
            $snapshotSummary = is_array($snapshot['summary'] ?? null) ? $snapshot['summary'] : [];
        } catch (\Throwable $exception) {
            report($exception);
        }

        return [
            'account' => $account->fresh(),
            'account_name' => $info['name'],
            'account_id' => $info['id'],
            'snapshot_summary' => $snapshotSummary,
        ];
    }

    public function disconnect(Project $project): void
    {
        $project->amocrmAccount?->delete();

        // Временные выгрузки для аудита удаляем вместе с подключением.
        $project->amocrmAuditExports()->delete();
    }

    public function clearSyncIds(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $funnelIds = Funnel::query()->where('project_id', $project->id)->pluck('id');

            Funnel::query()->where('project_id', $project->id)->update(['amocrm_id' => null]);

            if ($funnelIds->isNotEmpty()) {
                FunnelStage::query()->whereIn('funnel_id', $funnelIds)->update(['amocrm_id' => null]);
            }

            ProjectSkkField::query()->where('project_id', $project->id)->update(['amocrm_id' => null]);
        });
    }
}
