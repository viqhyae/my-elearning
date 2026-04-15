<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\DummyLmsData;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(DummyLmsData::adminDashboard());
    }

    public function courses(): JsonResponse
    {
        $courses = Course::query()
            ->select(['id', 'title', 'slug', 'level', 'category', 'is_published', 'updated_at'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Course $course): array {
                $enrolled = 40 + ($course->id * 17) % 120;
                $completionRate = 50 + ($course->id * 11) % 45;

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'level' => $course->level,
                    'category' => $course->category,
                    'status' => $course->is_published ? 'Published' : 'Draft',
                    'enrolled_students' => $enrolled,
                    'completion_rate' => $completionRate,
                    'last_updated' => optional($course->updated_at)->toISOString(),
                    'image_url' => $this->courseImageBySlug($course->slug),
                ];
            });

        return response()->json($courses);
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
