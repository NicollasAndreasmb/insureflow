<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('seeding.default_password')
            ?? throw new RuntimeException('Set SEED_DEFAULT_PASSWORD in .env before running the seeders.');

        $users = [
            ['name' => 'Ana Administradora', 'email' => 'admin@insureflow.test', 'role' => UserRole::Admin],
            ['name' => 'Bruno Corretor', 'email' => 'corretor@insureflow.test', 'role' => UserRole::Broker],
            ['name' => 'Carla Assistente', 'email' => 'assistente@insureflow.test', 'role' => UserRole::Assistant],
        ];

        foreach ($users as $user) {
            User::factory()->create([...$user, 'password' => $password]);
        }

        User::factory()->count(3)->broker()->create(['password' => $password]);
        User::factory()->assistant()->create(['password' => $password]);
        User::factory()->assistant()->inactive()->create(['password' => $password]);
    }
}
