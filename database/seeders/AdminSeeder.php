<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('ADMIN_EMAIL');
        $name     = env('ADMIN_NAME', 'Administrator Dinas');
        $password = env('ADMIN_PASSWORD', 'password');

        if (empty($email)) {
            $this->command?->warn('ADMIN_EMAIL not set in .env, skipping admin seeder.');
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'      => $name,
                'password'  => Hash::make($password),
                'role'      => 'admin_dinas',
                'is_active' => true,
            ]
        );

        $this->command?->info("Admin user [{$email}] created/updated.");
    }
}
