<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->timestamp('share_expires_at')->nullable()->after('share_token');
            $table->string('share_password')->nullable()->after('share_expires_at');
            $table->boolean('share_comments_enabled')->default(true)->after('share_password');
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn(['share_expires_at', 'share_password', 'share_comments_enabled']);
        });
    }
};
