<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@multistore.test'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        $admin->syncRoles([UserRole::Admin->value]);
    }
}
