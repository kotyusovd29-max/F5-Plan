<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_ai_messages', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }

    public function down(): void
    {
        Schema::table('project_ai_messages', function (Blueprint $table) {
            $table->string('provider', 20)->default('f5ai')->after('thread');
        });
    }
};
