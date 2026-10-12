<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_skk_fields', function (Blueprint $table) {
            $table->string('value_type', 20)->default('text')->after('name');
            $table->boolean('is_required')->default(false)->after('value_type');
            $table->boolean('show_in_card')->default(true)->after('is_required');
            $table->boolean('show_in_list')->default(false)->after('show_in_card');
            $table->boolean('show_in_filter')->default(false)->after('show_in_list');
            $table->json('options')->nullable()->after('show_in_filter');
            $table->foreignId('funnel_stage_id')->nullable()->after('options')->constrained('funnel_stages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_skk_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funnel_stage_id');
            $table->dropColumn([
                'value_type',
                'is_required',
                'show_in_card',
                'show_in_list',
                'show_in_filter',
                'options',
            ]);
        });
    }
};
