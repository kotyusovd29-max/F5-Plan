<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@f5plan.local';

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Администратор',
                'password' => $email,
                'is_admin' => true,
                'role' => User::ROLE_ADMIN,
                'rop_id' => null,
            ]
        );

        $this->call(F5WidgetSeeder::class);
        $this->call(StarterPackageSeeder::class);
    }
}
