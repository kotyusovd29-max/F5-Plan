<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_integrations', function (Blueprint $table) {
            $table->string('widget_slug')->nullable()->after('name');
            $table->string('widget_url')->nullable()->after('widget_slug');
        });
    }

    public function down(): void
    {
        Schema::table('project_integrations', function (Blueprint $table) {
            $table->dropColumn(['widget_slug', 'widget_url']);
        });
    }
};
