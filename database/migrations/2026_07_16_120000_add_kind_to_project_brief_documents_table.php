<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_brief_documents', function (Blueprint $table) {
            $table->string('kind', 32)->default('tz')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_brief_documents', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
