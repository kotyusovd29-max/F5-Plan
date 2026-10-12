<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('starter_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('business_sphere');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('starter_package_funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('starter_package_id')->constrained('starter_packages')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('starter_package_funnel_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('starter_package_funnel_id')->constrained('starter_package_funnels')->cascadeOnDelete();
            $table->string('name');
            $table->text('action')->nullable();
            $table->text('comment')->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('starter_package_skk_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('starter_package_id')->constrained('starter_packages')->cascadeOnDelete();
            $table->string('entity_type');
            $table->string('name');
            $table->string('value_type')->default('text');
            $table->boolean('is_required')->default(false);
            $table->boolean('show_in_card')->default(true);
            $table->boolean('show_in_list')->default(false);
            $table->boolean('show_in_filter')->default(false);
            $table->json('options')->nullable();
            $table->string('funnel_name')->nullable();
            $table->string('stage_name')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('starter_package_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('starter_package_id')->constrained('starter_packages')->cascadeOnDelete();
            $table->string('name');
            $table->string('widget_slug')->nullable();
            $table->string('widget_url')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedInteger('billing_quantity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('starter_package_work_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('starter_package_id')->constrained('starter_packages')->cascadeOnDelete();
            $table->string('name');
            $table->text('composition')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('starter_package_work_stages');
        Schema::dropIfExists('starter_package_integrations');
        Schema::dropIfExists('starter_package_skk_fields');
        Schema::dropIfExists('starter_package_funnel_stages');
        Schema::dropIfExists('starter_package_funnels');
        Schema::dropIfExists('starter_packages');
    }
};
