<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class DummyLmsData
{
    public static function studentDashboard(): array
    {
        $now = Carbon::now();

        return [
            'summary' => [
                'active_courses' => 4,
                'completion_rate' => 68,
                'learning_streak_days' => 12,
                'certificates' => 3,
            ],
            'learning_path' => [
                [
                    'title' => 'Project Management Essentials',
                    'progress_percent' => 78,
                    'current_lesson' => 'Menyusun Sprint Plan',
                    'next_deadline' => $now->copy()->addDays(2)->toDateString(),
                    'cover_image' => '/images/course-project.svg',
                ],
                [
                    'title' => 'Komunikasi Profesional',
                    'progress_percent' => 46,
                    'current_lesson' => 'Negosiasi dengan Stakeholder',
                    'next_deadline' => $now->copy()->addDays(4)->toDateString(),
                    'cover_image' => '/images/course-communication.svg',
                ],
                [
                    'title' => 'Data Analysis Fundamentals',
                    'progress_percent' => 33,
                    'current_lesson' => 'Data Cleaning Basics',
                    'next_deadline' => $now->copy()->addDays(6)->toDateString(),
                    'cover_image' => '/images/course-data.svg',
                ],
            ],
            'upcoming_sessions' => [
                [
                    'title' => 'Live Class: Sprint Retrospective',
                    'mentor' => 'Dina Prameswari',
                    'start_at' => $now->copy()->addDay()->setTime(19, 0, 0)->toISOString(),
                    'duration_minutes' => 90,
                    'banner_image' => '/images/course-project.svg',
                ],
                [
                    'title' => 'Workshop: Public Speaking Practice',
                    'mentor' => 'Rizky Ardiansyah',
                    'start_at' => $now->copy()->addDays(3)->setTime(20, 0, 0)->toISOString(),
                    'duration_minutes' => 75,
                    'banner_image' => '/images/course-communication.svg',
                ],
            ],
            'tasks' => [
                [
                    'title' => 'Submit assignment: Risk Register',
                    'course' => 'Project Management Essentials',
                    'status' => 'Belum selesai',
                    'due_date' => $now->copy()->addDays(2)->toDateString(),
                ],
                [
                    'title' => 'Quiz 2: Professional Email',
                    'course' => 'Komunikasi Profesional',
                    'status' => 'Belum selesai',
                    'due_date' => $now->copy()->addDays(1)->toDateString(),
                ],
                [
                    'title' => 'Peer review: Dashboard Insight',
                    'course' => 'Data Analysis Fundamentals',
                    'status' => 'Selesai',
                    'due_date' => $now->copy()->subDay()->toDateString(),
                ],
            ],
        ];
    }

    public static function adminDashboard(): array
    {
        $now = Carbon::now();

        return [
            'hero_image' => '/images/hero-admin.svg',
            'summary' => [
                'total_students' => 1248,
                'active_instructors' => 34,
                'published_courses' => 52,
                'completion_rate_avg' => 71,
            ],
            'enrollment_trend' => [
                'labels' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                'values' => [24, 31, 29, 42, 38, 27, 19],
            ],
            'course_status' => [
                ['label' => 'Published', 'value' => 52],
                ['label' => 'Draft', 'value' => 14],
                ['label' => 'Archived', 'value' => 5],
            ],
            'recent_enrollments' => [
                [
                    'student_name' => 'Alya Putri',
                    'course_title' => 'Project Management Essentials',
                    'enrolled_at' => $now->copy()->subHours(2)->toISOString(),
                    'avatar_image' => '/images/avatar-alya.svg',
                ],
                [
                    'student_name' => 'Bima Akbar',
                    'course_title' => 'Komunikasi Profesional',
                    'enrolled_at' => $now->copy()->subHours(5)->toISOString(),
                    'avatar_image' => '/images/avatar-bima.svg',
                ],
                [
                    'student_name' => 'Citra Maharani',
                    'course_title' => 'Data Analysis Fundamentals',
                    'enrolled_at' => $now->copy()->subDay()->toISOString(),
                    'avatar_image' => '/images/avatar-citra.svg',
                ],
            ],
            'pending_reviews' => [
                [
                    'item' => 'Course update: Agile Leadership',
                    'owner' => 'Mentor Team A',
                    'submitted_at' => $now->copy()->subHours(8)->toISOString(),
                ],
                [
                    'item' => 'Quiz bank: Data Viz Essentials',
                    'owner' => 'Mentor Team B',
                    'submitted_at' => $now->copy()->subDays(2)->toISOString(),
                ],
            ],
        ];
    }
}
