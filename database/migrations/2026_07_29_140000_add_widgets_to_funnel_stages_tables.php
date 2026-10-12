<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['funnel_stages', 'library_funnel_stages', 'starter_package_funnel_stages'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'widgets')) {
                    $table->json('widgets')->nullable()->after('outcomes');
                }

                if (! Schema::hasColumn($tableName, 'action_widget_slug')) {
                    $table->string('action_widget_slug')->nullable()->after('action');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['funnel_stages', 'library_funnel_stages', 'starter_package_funnel_stages'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'widgets')) {
                    $table->dropColumn('widgets');
                }

                if (Schema::hasColumn($tableName, 'action_widget_slug')) {
                    $table->dropColumn('action_widget_slug');
                }
            });
        }
    }
};
