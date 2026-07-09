<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_skk_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 20);
            $table->string('name');
            $table->text('comment')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'entity_type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_skk_fields');
    }
};
