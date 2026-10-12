<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('board_share_token', 64)->nullable()->unique()->after('canvas_zoom');
            $table->timestamp('board_share_expires_at')->nullable()->after('board_share_token');
            $table->string('board_share_password')->nullable()->after('board_share_expires_at');
            $table->boolean('board_share_comments_enabled')->default(true)->after('board_share_password');
        });

        $rows = DB::table('funnels')
            ->whereNotNull('share_token')
            ->orderBy('id')
            ->get([
                'project_id',
                'share_token',
                'share_expires_at',
                'share_password',
                'share_comments_enabled',
            ]);

        $seen = [];

        foreach ($rows as $row) {
            $projectId = (int) $row->project_id;

            if (isset($seen[$projectId])) {
                continue;
            }

            $seen[$projectId] = true;

            DB::table('projects')
                ->where('id', $projectId)
                ->whereNull('board_share_token')
                ->update([
                    'board_share_token' => $row->share_token,
                    'board_share_expires_at' => $row->share_expires_at,
                    'board_share_password' => $row->share_password,
                    'board_share_comments_enabled' => $row->share_comments_enabled === null
                        ? true
                        : (bool) $row->share_comments_enabled,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'board_share_token',
                'board_share_expires_at',
                'board_share_password',
                'board_share_comments_enabled',
            ]);
        });
    }
};
