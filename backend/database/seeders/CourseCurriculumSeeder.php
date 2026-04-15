<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Database\Seeder;

class CourseCurriculumSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $blueprints = [
            'project-management-essentials' => [
                [
                    'title' => 'Fundamental Project Planning',
                    'description' => 'Menyusun objective, timeline, dan deliverable.',
                    'lessons' => [
                        [
                            'title' => 'Menentukan Scope dan Objective',
                            'description' => 'Teknik menyusun scope proyek yang jelas.',
                            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                            'topics' => ['Scope definition', 'Success metrics', 'Stakeholder mapping'],
                            'tools' => ['Notion', 'Trello'],
                            'duration_minutes' => 25,
                        ],
                        [
                            'title' => 'Membangun Timeline Realistis',
                            'description' => 'Membuat timeline sprint dan milestone.',
                            'video_url' => 'https://www.youtube.com/watch?v=FTQbiNvZqaY',
                            'topics' => ['Milestone', 'Buffer time', 'Critical path'],
                            'tools' => ['Gantt chart', 'Spreadsheet'],
                            'duration_minutes' => 30,
                        ],
                    ],
                ],
                [
                    'title' => 'Monitoring dan Retrospektif',
                    'description' => 'Evaluasi progress dan continuous improvement.',
                    'lessons' => [
                        [
                            'title' => 'Weekly Progress Review',
                            'description' => 'Metrik dan format review mingguan.',
                            'video_url' => 'https://www.youtube.com/watch?v=9bZkp7q19f0',
                            'topics' => ['KPI tracking', 'Status report'],
                            'tools' => ['Looker Studio', 'Google Sheets'],
                            'duration_minutes' => 22,
                        ],
                    ],
                ],
            ],
            'komunikasi-profesional' => [
                [
                    'title' => 'Professional Messaging',
                    'description' => 'Menulis pesan kerja yang jelas dan efektif.',
                    'lessons' => [
                        [
                            'title' => 'Struktur Email Profesional',
                            'description' => 'Format subject, context, action point.',
                            'video_url' => 'https://www.youtube.com/watch?v=QH2-TGUlwu4',
                            'topics' => ['Subject clarity', 'CTA', 'Tone'],
                            'tools' => ['Gmail', 'Google Docs'],
                            'duration_minutes' => 18,
                        ],
                        [
                            'title' => 'Komunikasi Asinkron Tim',
                            'description' => 'Best practice komunikasi lintas divisi.',
                            'video_url' => 'https://www.youtube.com/watch?v=3JZ_D3ELwOQ',
                            'topics' => ['Async update', 'Escalation path'],
                            'tools' => ['Slack', 'Notion'],
                            'duration_minutes' => 20,
                        ],
                    ],
                ],
            ],
            'data-analysis-fundamentals' => [
                [
                    'title' => 'Data Preparation',
                    'description' => 'Data cleaning dan validasi dasar.',
                    'lessons' => [
                        [
                            'title' => 'Cleaning Dataset',
                            'description' => 'Menangani missing values dan outlier.',
                            'video_url' => 'https://www.youtube.com/watch?v=fJ9rUzIMcZQ',
                            'topics' => ['Missing value', 'Outlier', 'Type casting'],
                            'tools' => ['Google Colab', 'Spreadsheet'],
                            'duration_minutes' => 27,
                        ],
                    ],
                ],
                [
                    'title' => 'Data Storytelling',
                    'description' => 'Menyampaikan insight ke stakeholder.',
                    'lessons' => [
                        [
                            'title' => 'Membuat Dashboard Insight',
                            'description' => 'Prinsip visualisasi data untuk keputusan bisnis.',
                            'video_url' => 'https://www.youtube.com/watch?v=L_jWHffIx5E',
                            'topics' => ['Chart selection', 'Narrative flow'],
                            'tools' => ['Looker Studio', 'PowerPoint'],
                            'duration_minutes' => 24,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($blueprints as $slug => $modules) {
            $course = Course::query()->where('slug', $slug)->first();

            if (! $course) {
                continue;
            }

            foreach ($modules as $moduleIndex => $moduleData) {
                $module = CourseModule::query()->updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'title' => $moduleData['title'],
                    ],
                    [
                        'description' => $moduleData['description'],
                        'order_no' => $moduleIndex + 1,
                        'is_published' => true,
                    ]
                );

                foreach ($moduleData['lessons'] as $lessonIndex => $lessonData) {
                    CourseLesson::query()->updateOrCreate(
                        [
                            'module_id' => $module->id,
                            'title' => $lessonData['title'],
                        ],
                        [
                            'description' => $lessonData['description'],
                            'video_url' => $lessonData['video_url'],
                            'topics' => $lessonData['topics'],
                            'tools' => $lessonData['tools'],
                            'duration_minutes' => $lessonData['duration_minutes'],
                            'order_no' => $lessonIndex + 1,
                            'is_published' => true,
                        ]
                    );
                }
            }
        }
    }
}
