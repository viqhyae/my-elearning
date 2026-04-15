<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CourseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $mentorId = User::query()->where('email', 'mentor@elearning.local')->value('id');

        $courses = [
            [
                'title' => 'Project Management Essentials',
                'slug' => 'project-management-essentials',
                'description' => 'Belajar membuat rencana kerja, timeline, dan tracking progress tim.',
                'level' => 'Pemula',
                'duration_weeks' => 4,
                'category' => 'Productivity',
                'price_amount' => 349000,
                'currency' => 'IDR',
                'trailer_video_url' => 'https://www.youtube.com/embed/2ReR1YJrNOM',
                'tools' => ['Notion', 'Trello', 'Google Sheets'],
            ],
            [
                'title' => 'Komunikasi Profesional',
                'slug' => 'komunikasi-profesional',
                'description' => 'Meningkatkan keterampilan komunikasi lintas tim dan lintas divisi.',
                'level' => 'Menengah',
                'duration_weeks' => 3,
                'category' => 'Soft Skills',
                'price_amount' => 299000,
                'currency' => 'IDR',
                'trailer_video_url' => 'https://www.youtube.com/embed/UNP03fDSj1U',
                'tools' => ['Zoom', 'Miro', 'Google Docs'],
            ],
            [
                'title' => 'Data Analysis Fundamentals',
                'slug' => 'data-analysis-fundamentals',
                'description' => 'Pahami dasar analisis data untuk pengambilan keputusan berbasis metrik.',
                'level' => 'Pemula',
                'duration_weeks' => 6,
                'category' => 'Analytics',
                'price_amount' => 459000,
                'currency' => 'IDR',
                'trailer_video_url' => 'https://www.youtube.com/embed/ua-CiDNNj30',
                'tools' => ['Google Colab', 'Looker Studio', 'Spreadsheet'],
            ],
        ];

        foreach ($courses as $course) {
            Course::query()->updateOrCreate(
                ['slug' => $course['slug']],
                [
                    ...$course,
                    'mentor_user_id' => $mentorId,
                    'is_published' => true,
                    'published_at' => Carbon::now(),
                ]
            );
        }
    }
}
