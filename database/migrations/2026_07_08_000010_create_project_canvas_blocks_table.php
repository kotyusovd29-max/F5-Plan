<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_canvas_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('block_key', 64);
            $table->string('block_type', 32);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('title');
            $table->integer('pos_x')->default(0);
            $table->integer('pos_y')->default(0);
            $table->unsignedInteger('width')->default(300);
            $table->unsignedInteger('z_index')->default(1);
            $table->timestamps();

            $table->unique(['project_id', 'block_key']);
            $table->index(['project_id', 'z_index']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->integer('canvas_offset_x')->default(0)->after('client_tasks');
            $table->integer('canvas_offset_y')->default(0)->after('canvas_offset_x');
            $table->decimal('canvas_zoom', 4, 2)->default(1)->after('canvas_offset_y');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['canvas_offset_x', 'canvas_offset_y', 'canvas_zoom']);
        });

        Schema::dropIfExists('project_canvas_blocks');
    }
};
