<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_google_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_file_id');
            $table->string('name');
            $table->string('url');
            $table->string('source', 32)->default('project_plan');
            $table->foreignId('project_ai_message_id')->nullable()->constrained('project_ai_messages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'google_file_id']);
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_google_documents');
    }
};
