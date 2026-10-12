<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class EnsureAdminCommand extends Command
{
    protected $signature = 'f5:ensure-admin';

    protected $description = 'Create or restore the default admin account';

    public function handle(): int
    {
        $email = 'admin@f5plan.local';

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Администратор',
                'password' => $email,
                'is_admin' => true,
                'role' => User::ROLE_ADMIN,
                'rop_id' => null,
            ]
        );

        $this->info('Admin account ready.');
        $this->line('Email:    '.$email);
        $this->line('Password: '.$email);
        $this->line('ID:       '.$user->id);

        return self::SUCCESS;
    }
}
