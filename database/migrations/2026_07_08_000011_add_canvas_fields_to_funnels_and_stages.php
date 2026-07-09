<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->integer('canvas_offset_x')->default(0)->after('sort_order');
            $table->integer('canvas_offset_y')->default(0)->after('canvas_offset_x');
            $table->decimal('canvas_zoom', 4, 2)->default(1)->after('canvas_offset_y');
        });

        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->integer('canvas_pos_x')->nullable()->after('sort_order');
            $table->integer('canvas_pos_y')->nullable()->after('canvas_pos_x');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->dropColumn(['canvas_pos_x', 'canvas_pos_y']);
        });

        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn(['canvas_offset_x', 'canvas_offset_y', 'canvas_zoom']);
        });
    }
};
