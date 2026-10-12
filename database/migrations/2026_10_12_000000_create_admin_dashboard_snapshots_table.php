<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_dashboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamp('captured_at');
            $table->json('summary');
            $table->json('departments');
            $table->timestamps();
        });

        Schema::create('admin_dashboard_user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('admin_dashboard_snapshots')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('email');
            $table->string('role', 32);
            $table->string('role_label');
            $table->string('rop_name')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('last_seen_label');
            $table->unsignedBigInteger('online_seconds_today')->default(0);
            $table->string('online_today_label');
            $table->unsignedBigInteger('online_seconds_total')->default(0);
            $table->string('online_total_label');
            $table->unsignedInteger('projects_draft')->default(0);
            $table->unsignedInteger('projects_pending')->default(0);
            $table->unsignedInteger('projects_approved')->default(0);
            $table->unsignedInteger('projects_implementation')->default(0);
            $table->unsignedInteger('projects_archived')->default(0);
            $table->unsignedInteger('projects_total')->default(0);
            $table->unsignedInteger('projects_overdue')->default(0);
            $table->unsignedInteger('editor_projects')->default(0);
            $table->unsignedInteger('ai_requests')->default(0);
            $table->unsignedInteger('ai_responses')->default(0);
            $table->string('last_ai_project')->nullable();
            $table->string('last_ai_thread_label')->nullable();
            $table->string('last_ai_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dashboard_user_stats');
        Schema::dropIfExists('admin_dashboard_snapshots');
    }
};
