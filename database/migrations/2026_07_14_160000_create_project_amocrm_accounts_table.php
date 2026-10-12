<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_amocrm_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('subdomain');
            $table->text('access_token');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('account_name')->nullable();
            $table->timestamp('last_exported_at')->nullable();
            $table->json('last_export_result')->nullable();
            $table->timestamps();

            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_amocrm_accounts');
    }
};
