<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->unsignedBigInteger('amocrm_id')->nullable()->after('sort_order');
        });

        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->unsignedBigInteger('amocrm_id')->nullable()->after('sort_order');
        });

        Schema::table('project_skk_fields', function (Blueprint $table) {
            $table->unsignedBigInteger('amocrm_id')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn('amocrm_id');
        });

        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->dropColumn('amocrm_id');
        });

        Schema::table('project_skk_fields', function (Blueprint $table) {
            $table->dropColumn('amocrm_id');
        });
    }
};
