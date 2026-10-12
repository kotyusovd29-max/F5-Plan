<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funnels') || Schema::hasColumn('funnels', 'board_widgets')) {
            return;
        }

        Schema::table('funnels', function (Blueprint $table) {
            $table->json('board_widgets')->nullable()->after('canvas_zoom');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('funnels') || ! Schema::hasColumn('funnels', 'board_widgets')) {
            return;
        }

        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn('board_widgets');
        });
    }
};
