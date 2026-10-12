<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_amocrm_audit_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // Полная выгрузка amoCRM для аналитики (все сделки, задачи, события и справочники).
            $table->longText('payload');
            $table->timestamp('fetched_at')->nullable();
            // Выгрузка временная: удаляется через сутки после записи, проект не затрагивается.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'fetched_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_amocrm_audit_exports');
    }
};
