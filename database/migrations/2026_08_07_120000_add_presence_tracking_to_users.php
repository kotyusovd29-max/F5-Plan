<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('online_seconds_total')->default(0)->after('ai_model');
            $table->unsignedBigInteger('online_seconds_today')->default(0)->after('online_seconds_total');
            $table->date('online_day')->nullable()->after('online_seconds_today');
            $table->timestamp('last_seen_at')->nullable()->after('online_day');
        });

        Schema::create('user_presence_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_presence_sessions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'online_seconds_total',
                'online_seconds_today',
                'online_day',
                'last_seen_at',
            ]);
        });
    }
};
