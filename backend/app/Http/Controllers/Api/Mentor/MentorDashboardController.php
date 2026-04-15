<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $courses = Course::query()
            ->with(['mentor:id,name'])
            ->with(['modules.lessons'])
            ->when(
                $user?->role !== 'admin',
                fn ($query) => $query->where('mentor_user_id', $user?->id)
            )
            ->orderByDesc('updated_at')
            ->get();

        $totalModules = $courses->sum(fn (Course $course): int => $course->modules->count());
        $totalLessons = $courses->sum(
            fn (Course $course): int => $course->modules->sum(
                fn (CourseModule $module): int => $module->lessons->count()
            )
        );

        return response()->json([
            'summary' => [
                'total_courses' => $courses->count(),
                'published_courses' => $courses->where('is_published', true)->count(),
                'total_modules' => $totalModules,
                'total_lessons' => $totalLessons,
            ],
            'courses' => $courses
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'description' => $course->description,
                    'level' => $course->level,
                    'duration_weeks' => $course->duration_weeks,
                    'category' => $course->category,
                    'is_published' => (bool) $course->is_published,
                    'trailer_video_url' => $course->trailer_video_url,
                    'tools' => $course->tools ?: [],
                    'mentor_user_id' => $course->mentor_user_id,
                    'mentor_name' => $course->mentor?->name,
                    'image_url' => $this->courseImageBySlug($course->slug),
                    'module_count' => $course->modules->count(),
                    'lesson_count' => $course->modules->sum(
                        fn (CourseModule $module): int => $module->lessons->count()
                    ),
                    'updated_at' => optional($course->updated_at)->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function showCourse(Request $request, Course $course): JsonResponse
    {
        if (! $this->canAccessCourse($request, $course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kursus ini.',
            ], 403);
        }

        $course->load([
            'mentor:id,name,email',
            'modules' => fn ($query) => $query->orderBy('order_no')->orderBy('id'),
            'modules.lessons' => fn ($query) => $query->orderBy('order_no')->orderBy('id'),
        ]);

        return response()->json($this->serializeCourse($course));
    }

    private function canAccessCourse(Request $request, Course $course): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'mentor' && (int) $course->mentor_user_id === (int) $user->id;
    }

    private function serializeCourse(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description,
            'level' => $course->level,
            'duration_weeks' => $course->duration_weeks,
            'category' => $course->category,
            'is_published' => (bool) $course->is_published,
            'trailer_video_url' => $course->trailer_video_url,
            'tools' => $course->tools ?: [],
            'image_url' => $this->courseImageBySlug($course->slug),
            'mentor' => $course->mentor ? [
                'id' => $course->mentor->id,
                'name' => $course->mentor->name,
                'email' => $course->mentor->email,
            ] : null,
            'modules' => $course->modules->map(fn (CourseModule $module): array => [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'order_no' => $module->order_no,
                'is_published' => (bool) $module->is_published,
                'lessons' => $module->lessons->map(fn (CourseLesson $lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                    'video_url' => $lesson->video_url,
                    'topics' => $lesson->topics ?: [],
                    'tools' => $lesson->tools ?: [],
                    'duration_minutes' => $lesson->duration_minutes,
                    'order_no' => $lesson->order_no,
                    'is_published' => (bool) $lesson->is_published,
                ])->values()->all(),
            ])->values()->all(),
        ];
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
}

