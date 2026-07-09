<?php

use App\Support\FunnelStageColors;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('comment');
        });

        $stages = DB::table('funnel_stages')
            ->orderBy('funnel_id')
            ->orderBy('sort_order')
            ->get(['id', 'sort_order']);

        foreach ($stages as $stage) {
            DB::table('funnel_stages')
                ->where('id', $stage->id)
                ->update([
                    'color' => FunnelStageColors::defaultForIndex(max(0, (int) $stage->sort_order - 1)),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
