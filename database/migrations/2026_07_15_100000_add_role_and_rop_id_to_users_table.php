<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default(User::ROLE_EMPLOYEE)->after('is_admin');
            $table->foreignId('rop_id')
                ->nullable()
                ->after('role')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('users')->where('is_admin', true)->update(['role' => User::ROLE_ADMIN]);
        DB::table('users')->where('is_admin', false)->update(['role' => User::ROLE_EMPLOYEE]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rop_id');
            $table->dropColumn('role');
        });
    }
};
