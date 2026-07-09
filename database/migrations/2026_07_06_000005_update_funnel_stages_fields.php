<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->text('action')->nullable()->after('name');
            $table->text('comment')->nullable()->after('action');
        });

        $stages = DB::table('funnel_stages')
            ->whereNotNull('description')
            ->get(['id', 'description']);

        foreach ($stages as $stage) {
            DB::table('funnel_stages')
                ->where('id', $stage->id)
                ->update(['comment' => $stage->description]);
        }

        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        $stages = DB::table('funnel_stages')
            ->whereNotNull('comment')
            ->get(['id', 'comment']);

        foreach ($stages as $stage) {
            DB::table('funnel_stages')
                ->where('id', $stage->id)
                ->update(['description' => $stage->comment]);
        }

        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->dropColumn(['action', 'comment']);
        });
    }
};
