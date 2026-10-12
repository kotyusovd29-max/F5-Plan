<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_funnels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('library_funnel_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_funnel_id')->constrained('library_funnels')->cascadeOnDelete();
            $table->string('name');
            $table->text('action')->nullable();
            $table->text('comment')->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('library_skk_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->string('name');
            $table->string('value_type')->default('text');
            $table->boolean('is_required')->default(false);
            $table->boolean('show_in_card')->default(true);
            $table->boolean('show_in_list')->default(false);
            $table->boolean('show_in_filter')->default(false);
            $table->json('options')->nullable();
            $table->string('stage_name')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_skk_fields');
        Schema::dropIfExists('library_funnel_stages');
        Schema::dropIfExists('library_funnels');
    }
};
