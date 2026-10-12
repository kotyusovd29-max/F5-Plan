<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_ai_messages', function (Blueprint $table) {
            $table->string('thread', 32)->default('project')->after('project_id');
            $table->index(['project_id', 'thread']);
        });
    }

    public function down(): void
    {
        Schema::table('project_ai_messages', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'thread']);
            $table->dropColumn('thread');
        });
    }
};
