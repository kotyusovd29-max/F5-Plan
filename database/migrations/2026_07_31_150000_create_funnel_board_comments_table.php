<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_board_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->foreignId('funnel_stage_id')->nullable()->constrained('funnel_stages')->nullOnDelete();
            $table->string('author_name', 120);
            $table->text('body');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'created_at']);
            $table->index(['funnel_id', 'funnel_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_board_comments');
    }
};
