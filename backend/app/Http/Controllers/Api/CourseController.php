<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::query()
            ->select([
                'id',
                'title',
                'slug',
                'description',
                'level',
                'duration_weeks',
                'category',
                'price_amount',
                'currency',
                'trailer_video_url',
                'tools',
                'mentor_user_id',
            ])
            ->with(['mentor:id,name'])
            ->withCount(['modules'])
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderBy('title')
            ->get()
            ->map(function (Course $course): array {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'description' => $course->description,
                    'level' => $course->level,
                    'duration' => $course->duration_weeks ? $course->duration_weeks.' minggu' : null,
                    'category' => $course->category,
                    'price_amount' => (int) $course->price_amount,
                    'currency' => $course->currency ?: 'IDR',
                    'price_label' => ($course->currency ?: 'IDR').' '.number_format((int) $course->price_amount, 0, ',', '.'),
                    'image_url' => $this->courseImageBySlug($course->slug),
                    'trailer_video_url' => $course->trailer_video_url,
                    'tools' => $course->tools ?: [],
                    'mentor_name' => $course->mentor?->name,
                    'total_modules' => $course->modules_count,
                    'rating_avg' => $this->averageRating($course->slug),
                ];
            });

        return response()->json($courses);
    }

    public function show(string $slug): JsonResponse
    {
        $course = Course::query()
            ->with([
                'mentor:id,name,email',
                'modules' => fn ($query) => $query->where('is_published', true),
                'modules.lessons' => fn ($query) => $query->where('is_published', true),
            ])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (! $course) {
            return response()->json([
                'message' => 'Kursus tidak ditemukan.',
            ], 404);
        }

        $reviews = $this->reviewsBySlug($course->slug);

        return response()->json([
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description,
            'level' => $course->level,
            'duration' => $course->duration_weeks ? $course->duration_weeks.' minggu' : null,
            'category' => $course->category,
            'price_amount' => (int) $course->price_amount,
            'currency' => $course->currency ?: 'IDR',
            'price_label' => ($course->currency ?: 'IDR').' '.number_format((int) $course->price_amount, 0, ',', '.'),
            'image_url' => $this->courseImageBySlug($course->slug),
            'trailer_video_url' => $course->trailer_video_url,
            'tools' => $course->tools ?: [],
            'mentor' => $course->mentor ? [
                'name' => $course->mentor->name,
                'email' => $course->mentor->email,
            ] : null,
            'what_you_learn' => $this->topicsFromModules($course->modules),
            'reviews' => $reviews,
            'rating_avg' => $this->averageRating($course->slug),
            'modules' => $course->modules->map(
                fn (CourseModule $module): array => [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description,
                    'order_no' => $module->order_no,
                    'lessons' => $module->lessons->map(
                        fn (CourseLesson $lesson): array => [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'description' => $lesson->description,
                            'video_url' => $lesson->video_url,
                            'topics' => $lesson->topics ?: [],
                            'tools' => $lesson->tools ?: [],
                            'duration_minutes' => $lesson->duration_minutes,
                            'order_no' => $lesson->order_no,
                        ]
                    )->values()->all(),
                ]
            )->values()->all(),
        ]);
    }

    private function courseImageBySlug(string $slug): string
    {
        return match ($slug) {
            'project-management-essentials' => '/images/course-project.svg',
            'komunikasi-profesional' => '/images/course-communication.svg',
            'data-analysis-fundamentals' => '/images/course-data.svg',
            default => '/images/course-project.svg',
        };
    }

    /**
     * @param  Collection<int, CourseModule>  $modules
     * @return array<int, string>
     */
    private function topicsFromModules(Collection $modules): array
    {
        return $modules
            ->flatMap(
                fn (CourseModule $module) => $module->lessons->flatMap(
                    fn (CourseLesson $lesson) => $lesson->topics ?: []
                )
            )
            ->filter(fn ($topic) => is_string($topic) && trim($topic) !== '')
            ->map(fn (string $topic) => trim($topic))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function averageRating(string $slug): float
    {
        $reviews = $this->reviewsBySlug($slug);

        if ($reviews === []) {
            return 0;
        }

        $ratingSum = array_sum(array_map(fn (array $review) => (int) ($review['rating'] ?? 0), $reviews));

        return round($ratingSum / count($reviews), 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reviewsBySlug(string $slug): array
    {
        $reviews = [
            'project-management-essentials' => [
                [
                    'id' => 1,
                    'author' => 'Alya Putri',
                    'rating' => 5,
                    'comment' => 'Materi runtut, langsung bisa dipakai untuk sprint planning tim.',
                    'created_at' => '2026-03-08',
                ],
                [
                    'id' => 2,
                    'author' => 'Bima Akbar',
                    'rating' => 4,
                    'comment' => 'Template project plan-nya membantu sekali buat pekerjaan harian.',
                    'created_at' => '2026-03-20',
                ],
            ],
            'komunikasi-profesional' => [
                [
                    'id' => 3,
                    'author' => 'Citra Maharani',
                    'rating' => 5,
                    'comment' => 'Contoh komunikasi asinkronnya relevan banget untuk kerja remote.',
                    'created_at' => '2026-02-16',
                ],
                [
                    'id' => 4,
                    'author' => 'Dian Prakoso',
                    'rating' => 4,
                    'comment' => 'Sesi negosiasi menarik dan mudah dipahami.',
                    'created_at' => '2026-03-11',
                ],
            ],
            'data-analysis-fundamentals' => [
                [
                    'id' => 5,
                    'author' => 'Erika Valencia',
                    'rating' => 4,
                    'comment' => 'Dasar data cleaning dijelaskan step-by-step.',
                    'created_at' => '2026-01-25',
                ],
                [
                    'id' => 6,
                    'author' => 'Fajar Nugroho',
                    'rating' => 5,
                    'comment' => 'Bagian data storytelling sangat kepakai untuk presentasi ke stakeholder.',
                    'created_at' => '2026-03-02',
                ],
            ],
        ];

        return $reviews[$slug] ?? [];
    }
}
