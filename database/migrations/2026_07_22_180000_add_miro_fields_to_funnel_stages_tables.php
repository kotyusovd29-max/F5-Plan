<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->string('stage_type')->nullable()->after('comment');
            $table->string('deadline')->nullable()->after('stage_type');
            $table->string('kind', 16)->default('normal')->after('deadline');
            $table->json('outcomes')->nullable()->after('kind');
        });

        Schema::table('library_funnel_stages', function (Blueprint $table) {
            $table->string('stage_type')->nullable()->after('comment');
            $table->string('deadline')->nullable()->after('stage_type');
            $table->string('kind', 16)->default('normal')->after('deadline');
            $table->json('outcomes')->nullable()->after('kind');
        });

        Schema::table('starter_package_funnel_stages', function (Blueprint $table) {
            $table->string('stage_type')->nullable()->after('comment');
            $table->string('deadline')->nullable()->after('stage_type');
            $table->string('kind', 16)->default('normal')->after('deadline');
            $table->json('outcomes')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_stages', function (Blueprint $table) {
            $table->dropColumn(['stage_type', 'deadline', 'kind', 'outcomes']);
        });

        Schema::table('library_funnel_stages', function (Blueprint $table) {
            $table->dropColumn(['stage_type', 'deadline', 'kind', 'outcomes']);
        });

        Schema::table('starter_package_funnel_stages', function (Blueprint $table) {
            $table->dropColumn(['stage_type', 'deadline', 'kind', 'outcomes']);
        });
    }
};
