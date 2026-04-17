<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class PaymentTransactionSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $students = [
            ['name' => 'Diana Fitri', 'email' => 'diana@elearning.local', 'avatar_url' => 'https://i.pravatar.cc/150?img=1'],
            ['name' => 'Reza Aditya', 'email' => 'reza@elearning.local', 'avatar_url' => 'https://i.pravatar.cc/150?img=13'],
            ['name' => 'Siti Aminah', 'email' => 'siti@elearning.local', 'avatar_url' => 'https://i.pravatar.cc/150?img=5'],
            ['name' => 'Kevin Pratama', 'email' => 'kevin@elearning.local', 'avatar_url' => 'https://i.pravatar.cc/150?img=11'],
        ];

        foreach ($students as $student) {
            User::query()->updateOrCreate(
                ['email' => $student['email']],
                [
                    'name' => $student['name'],
                    'password' => Hash::make('password123'),
                    'role' => 'student',
                    'status' => 'active',
                    'avatar_url' => $student['avatar_url'],
                ]
            );
        }

        $studentUsers = User::query()
            ->whereIn('email', collect($students)->pluck('email')->all())
            ->orderBy('id')
            ->get();

        $courses = Course::query()->orderBy('id')->get();

        if ($studentUsers->isEmpty() || $courses->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        $rows = [
            ['reference' => 'TXN-GEM-0001', 'studentIndex' => 0, 'courseIndex' => 0, 'status' => 'paid', 'hoursAgo' => 2],
            ['reference' => 'TXN-GEM-0002', 'studentIndex' => 1, 'courseIndex' => 1, 'status' => 'paid', 'hoursAgo' => 5],
            ['reference' => 'TXN-GEM-0003', 'studentIndex' => 2, 'courseIndex' => 2, 'status' => 'paid', 'hoursAgo' => 12],
            ['reference' => 'TXN-GEM-0004', 'studentIndex' => 3, 'courseIndex' => 0, 'status' => 'paid', 'hoursAgo' => 30],
            ['reference' => 'TXN-GEM-0005', 'studentIndex' => 0, 'courseIndex' => 1, 'status' => 'pending', 'hoursAgo' => 1],
            ['reference' => 'TXN-GEM-0006', 'studentIndex' => 1, 'courseIndex' => 2, 'status' => 'paid', 'hoursAgo' => 72],
        ];

        foreach ($rows as $row) {
            $student = $studentUsers[$row['studentIndex'] % $studentUsers->count()];
            $course = $courses[$row['courseIndex'] % $courses->count()];
            $status = $row['status'];
            $price = (int) ($course->price_amount ?? 0);

            PaymentTransaction::query()->updateOrCreate(
                ['reference' => $row['reference']],
                [
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'voucher_id' => null,
                    'payment_method' => 'bank_transfer',
                    'status' => $status,
                    'original_price' => $price,
                    'discount_amount' => 0,
                    'final_price' => $price,
                    'paid_at' => $status === 'paid' ? $now->copy()->subHours($row['hoursAgo']) : null,
                    'created_at' => $now->copy()->subHours($row['hoursAgo']),
                    'updated_at' => $now->copy()->subHours(max(0, $row['hoursAgo'] - 1)),
                ]
            );
        }
    }
}

