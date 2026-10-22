<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServiceRequestLookupSeeder::class);
        $this->call(LocationSeeder::class);
        $this->call(DepartmentSeeder::class);

        User::updateOrCreate(
            ['email' => 'maeslabonjr@capsu.edu.ph'],
            [
                'name' => 'Marvin Arlante Eslabon Jr.',
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_admin' => false,
            ],
        );

        User::updateOrCreate(
            ['email' => 'admin@capsu.edu.ph'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_admin' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'mis@capsu.edu.ph'],
            [
                'name' => 'MIS Staff',
                'password' => Hash::make('password'),
                'role' => 'mis_staff',
                'is_admin' => false,
            ],
        );

        User::updateOrCreate(
            ['email' => 'hr@capsu.edu.ph'],
            [
                'name' => 'HR Department',
                'password' => Hash::make('password'),
                'role' => 'hr',
                'is_admin' => false,
            ],
        );
    }
}
