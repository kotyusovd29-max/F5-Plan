<?php

namespace App\Console\Commands;

use App\Models\F5Widget;
use App\Support\F5WidgetCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncF5WidgetIconsCommand extends Command
{
    protected $signature = 'f5:sync-widget-icons
                            {--write-config : Rewrite config/f5_widget_icons.php}
                            {--update-db : Update icon column on f5_widgets}
                            {--only-missing : Skip widgets that already have an icon}';

    protected $description = 'Sync widget icons from cmdf5.ru catalog cards (and fill gaps from product pages)';

    public function handle(): int
    {
        $writeConfig = (bool) $this->option('write-config');
        $updateDb = (bool) $this->option('update-db');
        $onlyMissing = (bool) $this->option('only-missing');

        if (! $writeConfig && ! $updateDb) {
            $writeConfig = true;
            $updateDb = true;
        }

        $icons = config('f5_widget_icons', []);
        if (! is_array($icons)) {
            $icons = [];
        }

        $catalogUrl = (string) (F5WidgetCatalog::config()['source_url'] ?? 'https://cmdf5.ru/widjety-amocrm');
        $fromCatalog = $this->fetchCatalogIcons($catalogUrl);
        $this->info('Catalog card icons: '.count($fromCatalog));

        foreach ($fromCatalog as $slug => $icon) {
            $localSlug = $this->normalizeCatalogSlug($slug);
            if ($localSlug === null) {
                continue;
            }

            if ($onlyMissing && filled($icons[$localSlug] ?? null)) {
                continue;
            }

            $icons[$localSlug] = $icon;
            $this->line("catalog {$localSlug}");
        }

        $widgets = F5WidgetCatalog::widgets();
        $fetched = 0;
        $kept = 0;
        $failed = 0;

        foreach ($widgets as $widget) {
            $slug = (string) ($widget['slug'] ?? '');
            if ($slug === '' || $slug === F5WidgetCatalog::CUSTOM_SLUG) {
                continue;
            }

            $existing = filled($icons[$slug] ?? null)
                ? (string) $icons[$slug]
                : (filled($widget['icon'] ?? null) ? (string) $widget['icon'] : null);

            if ($existing) {
                $icons[$slug] = $existing;
                $kept++;

                if ($updateDb) {
                    F5Widget::query()->where('slug', $slug)->update(['icon' => $existing]);
                }

                continue;
            }

            if ($onlyMissing === false) {
                // already handled above when not only-missing via catalog
            }

            $url = filled($widget['url'] ?? null) ? (string) $widget['url'] : null;
            if ($url === null || ! str_starts_with($url, 'http') || str_ends_with(rtrim($url, '/'), '/sp')) {
                // Platform package widgets without own page — reuse Platform icon
                if (filled($icons['sp'] ?? null)) {
                    $icons[$slug] = (string) $icons['sp'];
                    $fetched++;
                    $this->info("fallback {$slug} -> sp");
                    if ($updateDb) {
                        F5Widget::query()->where('slug', $slug)->update(['icon' => $icons[$slug]]);
                    }
                    continue;
                }

                $failed++;
                $this->warn("fail {$slug}: no icon");
                continue;
            }

            $icon = $this->fetchIconFromPage($url);
            if ($icon === null) {
                if (filled($icons['sp'] ?? null) && str_contains($url, '/sp')) {
                    $icons[$slug] = (string) $icons['sp'];
                    $fetched++;
                } else {
                    $failed++;
                    $this->warn("fail {$slug}: no icon on {$url}");
                }
                continue;
            }

            $icons[$slug] = $icon;
            $fetched++;
            $this->info("page  {$slug}: {$icon}");

            if ($updateDb) {
                F5Widget::query()->where('slug', $slug)->update(['icon' => $icon]);
            }

            usleep(200_000);
        }

        ksort($icons);

        if ($writeConfig) {
            $path = config_path('f5_widget_icons.php');
            $export = var_export($icons, true);
            file_put_contents($path, "<?php\n\nreturn {$export};\n");
            $this->info("Wrote {$path} (".count($icons).' icons)');
        }

        if ($updateDb) {
            F5WidgetCatalog::forgetCache();
        }

        $this->newLine();
        $this->info("Resolved: {$kept}; newly filled: {$fetched}; failed: {$failed}");

        return $failed > 0 && $fetched === 0 && $kept === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, string> slug => icon url
     */
    private function fetchCatalogIcons(string $catalogUrl): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'F5planBot/1.0 (+https://f5plan.local)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($catalogUrl);
        } catch (\Throwable $e) {
            report($e);
            $this->warn('Catalog fetch failed: '.$e->getMessage());

            return [];
        }

        if (! $response->successful()) {
            $this->warn('Catalog HTTP '.$response->status());

            return [];
        }

        $html = $response->body();
        $map = [];

        if (preg_match_all(
            '/data-original="(https:\/\/static\.tildacdn\.com\/[^"]+)"[\s\S]{0,1200}?href="(\/widjety-amocrm\/[^"]+|https:\/\/cmdf5\.ru\/widjety-amocrm\/[^"]+)"/i',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $icon = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
                $href = $match[2];
                $path = parse_url($href, PHP_URL_PATH) ?: $href;
                $slug = trim((string) basename($path));
                if ($slug !== '' && $slug !== 'widjety-amocrm') {
                    $map[$slug] = $icon;
                }
            }
        }

        return $map;
    }

    private function normalizeCatalogSlug(string $slug): ?string
    {
        $slug = trim($slug);
        if ($slug === '' || $slug === 'widjety-amocrm') {
            return null;
        }

        // Site uses underscore variant for google sheets lite
        $aliases = [
            'google-sheets_lite' => 'google-sheets-lite',
            'raspredelenie-zayavok' => 'raspredeline-zayavok',
            'vk-amocrm' => 'vk-market',
            'group-fields' => 'field-grouping',
            'no-result-task' => 'no-task-close-without-result',
            'notask-leads' => 'no-deals-without-tasks',
            'timeline-filter' => 'feed-filter',
            'list-finder' => 'list-field-search',
            'zapret-udaleniya-zadach' => 'task-delete-ban',
            'user-time-zone' => 'timezone-switch',
        ];

        return $aliases[$slug] ?? $slug;
    }

    private function fetchIconFromPage(string $url): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'F5planBot/1.0 (+https://f5plan.local)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $html = $response->body();
        $candidates = [];

        if (preg_match_all('/https?:\/\/(?:static|thb)\.tildacdn\.com\/[^"\'\s>]+\.(?:png|jpe?g|svg|webp|gif)/i', $html, $matches)) {
            foreach ($matches[0] as $src) {
                $candidates[] = html_entity_decode($src, ENT_QUOTES | ENT_HTML5);
            }
        }

        $best = null;
        $bestScore = PHP_INT_MIN;

        foreach (array_unique($candidates) as $candidate) {
            $normalized = $this->normalizeIconUrl($candidate, $url);
            if ($normalized === null || $this->isJunkIcon($normalized)) {
                continue;
            }

            $score = $this->scoreIconUrl($normalized);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $normalized;
            }
        }

        return $bestScore >= 40 ? $best : null;
    }

    private function normalizeIconUrl(string $src, string $pageUrl): ?string
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }

        if (str_starts_with($src, '//')) {
            $src = 'https:'.$src;
        }

        if (str_starts_with($src, '/')) {
            $parts = parse_url($pageUrl);
            if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
                return null;
            }
            $src = $parts['scheme'].'://'.$parts['host'].$src;
        }

        $src = preg_replace('#https?://thb\.tildacdn\.com/#', 'https://static.tildacdn.com/', $src) ?? $src;
        $src = preg_replace('#/-/resizeb?/\\d+x/#', '/', $src) ?? $src;
        $src = preg_replace('#/-/empty/#', '/', $src) ?? $src;

        if (! str_starts_with($src, 'http://') && ! str_starts_with($src, 'https://')) {
            return null;
        }

        return $src;
    }

    private function scoreIconUrl(string $url): int
    {
        $lower = mb_strtolower($url);
        $file = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        $score = 0;

        if (str_contains($lower, 'static.tildacdn.com')) {
            $score += 10;
        }

        if (preg_match('/widget|icon|logo|vidzhet|badge|bage|google|sheet|telegram/i', $file)) {
            $score += 40;
        }

        if (preg_match('/\.(png|svg|webp)$/i', $file)) {
            $score += 15;
        }

        if (preg_match('/\.(jpe?g)$/i', $file)) {
            $score -= 10;
        }

        if (preg_match('/photo_|img_|tg_image|white-phone|artboard|square_logo|blackon_|frame_|group_22527|tour_|chevron_/i', $file)) {
            $score -= 35;
        }

        return $score;
    }

    private function isJunkIcon(string $url): bool
    {
        $lower = mb_strtolower($url);

        foreach (['favicon', 'logo-f5', 'sprite', '1x1', 'pixel', 'blank', 'placeholder', '/lib/icons/'] as $junk) {
            if (str_contains($lower, $junk)) {
                return true;
            }
        }

        return false;
    }
}
