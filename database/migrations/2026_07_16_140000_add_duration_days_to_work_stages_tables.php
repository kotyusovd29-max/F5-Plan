<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_work_stages', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->nullable()->after('cost');
        });

        if (Schema::hasTable('starter_package_work_stages')) {
            Schema::table('starter_package_work_stages', function (Blueprint $table) {
                $table->unsignedInteger('duration_days')->nullable()->after('cost');
            });
        }
    }

    public function down(): void
    {
        Schema::table('project_work_stages', function (Blueprint $table) {
            $table->dropColumn('duration_days');
        });

        if (Schema::hasTable('starter_package_work_stages')) {
            Schema::table('starter_package_work_stages', function (Blueprint $table) {
                $table->dropColumn('duration_days');
            });
        }
    }
};
