<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@inventario.local'],
            [
                'name' => 'Administrador',
                'password' => 'admin1234',
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'comprador@inventario.local'],
            [
                'name' => 'Comprador',
                'password' => 'comprador1234',
                'role' => User::ROLE_COMPRADOR,
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'marketing@inventario.local'],
            [
                'name' => 'Marketing',
                'password' => 'marketing1234',
                'role' => User::ROLE_MARKETING,
            ]
        );
    }
}
