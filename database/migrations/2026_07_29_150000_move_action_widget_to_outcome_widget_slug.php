<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'funnel_stages',
        'library_funnel_stages',
        'starter_package_funnel_stages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'action_widget_slug')) {
                continue;
            }

            DB::table($tableName)
                ->whereNotNull('action_widget_slug')
                ->where('action_widget_slug', '!=', '')
                ->orderBy('id')
                ->each(function (object $stage) use ($tableName): void {
                    $outcomes = json_decode((string) ($stage->outcomes ?? '[]'), true);

                    if (! is_array($outcomes) || $outcomes === []) {
                        return;
                    }

                    $slug = trim((string) $stage->action_widget_slug);
                    $changed = false;

                    foreach ($outcomes as $index => $row) {
                        if (! is_array($row)) {
                            continue;
                        }

                        $existing = trim((string) ($row['widget_slug'] ?? ''));

                        if ($existing !== '') {
                            continue;
                        }

                        $outcomes[$index]['widget_slug'] = $slug;
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table($tableName)->where('id', $stage->id)->update([
                            'outcomes' => json_encode(array_values($outcomes), JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('action_widget_slug');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'action_widget_slug')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('action_widget_slug')->nullable()->after('action');
            });
        }
    }
};
