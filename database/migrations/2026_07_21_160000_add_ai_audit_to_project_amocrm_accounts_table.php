<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_amocrm_accounts', function (Blueprint $table) {
            $table->timestamp('last_ai_audit_at')->nullable()->after('last_export_result');
            $table->longText('last_ai_audit_result')->nullable()->after('last_ai_audit_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_amocrm_accounts', function (Blueprint $table) {
            $table->dropColumn(['last_ai_audit_at', 'last_ai_audit_result']);
        });
    }
};
