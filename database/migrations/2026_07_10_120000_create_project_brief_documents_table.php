<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_brief_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->longText('extracted_text')->nullable();
            $table->unsignedInteger('char_count')->default(0);
            $table->timestamps();
        });

        Schema::table('project_ai_messages', function (Blueprint $table) {
            $table->foreignId('brief_document_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_brief_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_ai_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brief_document_id');
        });

        Schema::dropIfExists('project_brief_documents');
    }
};
