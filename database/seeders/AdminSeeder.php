<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@cotsu.edu.ph'],
            [
                'name' => 'System Admin',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'faculty@cotsu.edu.ph'],
            [
                'name' => 'Faculty User',
                'password' => bcrypt('faculty123'),
                'role' => 'faculty',
            ]
        );

        User::firstOrCreate(
            ['email' => 'student@cotsu.edu.ph'],
            [
                'name' => 'Student User',
                'password' => bcrypt('student123'),
                'role' => 'student',
            ]
        );
    }
}
