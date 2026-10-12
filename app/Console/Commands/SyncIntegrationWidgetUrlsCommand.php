<?php

namespace App\Console\Commands;

use App\Models\ProjectIntegration;
use App\Support\F5WidgetCatalog;
use Illuminate\Console\Command;

class SyncIntegrationWidgetUrlsCommand extends Command
{
    protected $signature = 'f5:sync-widget-urls';

    protected $description = 'Обновить widget_url у интеграций проектов по актуальному каталогу F5';

    public function handle(): int
    {
        $updated = 0;

        ProjectIntegration::query()
            ->whereNotNull('widget_slug')
            ->where('widget_slug', '!=', '')
            ->where('widget_slug', '!=', F5WidgetCatalog::CUSTOM_SLUG)
            ->orderBy('id')
            ->each(function (ProjectIntegration $integration) use (&$updated): void {
                $url = F5WidgetCatalog::resolveUrl($integration->widget_slug);

                if ($url === null || $url === $integration->widget_url) {
                    return;
                }

                $integration->forceFill(['widget_url' => $url])->save();
                $updated++;
            });

        $this->info("Обновлено ссылок: {$updated}");

        return self::SUCCESS;
    }
}
