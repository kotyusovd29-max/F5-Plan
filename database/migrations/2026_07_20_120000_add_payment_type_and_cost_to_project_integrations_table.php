<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_integrations', function (Blueprint $table) {
            $table->string('payment_type', 32)->nullable()->after('billing_quantity');
            $table->decimal('cost', 12, 2)->nullable()->after('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('project_integrations', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'cost']);
        });
    }
};
