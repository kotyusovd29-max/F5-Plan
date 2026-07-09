<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['deals_fields', 'contacts_fields', 'companies_fields']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('deals_fields')->nullable()->after('client_tasks');
            $table->text('contacts_fields')->nullable()->after('deals_fields');
            $table->text('companies_fields')->nullable()->after('contacts_fields');
        });
    }
};
