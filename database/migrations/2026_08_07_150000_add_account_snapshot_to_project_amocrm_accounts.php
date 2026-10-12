<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_amocrm_accounts', function (Blueprint $table) {
            $table->json('account_snapshot')->nullable()->after('last_ai_audit_result');
            $table->timestamp('account_snapshot_at')->nullable()->after('account_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('project_amocrm_accounts', function (Blueprint $table) {
            $table->dropColumn(['account_snapshot', 'account_snapshot_at']);
        });
    }
};
