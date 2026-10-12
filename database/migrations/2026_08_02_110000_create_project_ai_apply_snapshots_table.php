<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_ai_apply_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_ai_message_id')->constrained('project_ai_messages')->cascadeOnDelete();
            $table->json('payload');
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();

            $table->unique('project_ai_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ai_apply_snapshots');
    }
};
