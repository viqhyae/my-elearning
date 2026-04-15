<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password123';

    /**
     * @var array<int, array{name: string, email: string, role: string, status: string, avatar_url: string|null}>
     */
    private const DUMMY_USERS = [
        [
            'name' => 'Admin LMS',
            'email' => 'admin@elearning.local',
            'role' => 'admin',
            'status' => 'active',
            'avatar_url' => '/images/avatar-alya.svg',
        ],
        [
            'name' => 'Mentor LMS',
            'email' => 'mentor@elearning.local',
            'role' => 'mentor',
            'status' => 'active',
            'avatar_url' => '/images/avatar-bima.svg',
        ],
        [
            'name' => 'Student LMS',
            'email' => 'student@elearning.local',
            'role' => 'student',
            'status' => 'active',
            'avatar_url' => '/images/avatar-citra.svg',
        ],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::DUMMY_USERS as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'role' => $user['role'],
                    'status' => $user['status'],
                    'avatar_url' => $user['avatar_url'],
                ]
            );
        }
    }
}
