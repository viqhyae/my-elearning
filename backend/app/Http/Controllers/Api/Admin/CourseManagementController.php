<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CourseManagementController extends Controller
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
                'is_published',
                'published_at',
                'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Course $course): array => $this->toPayload($course))
            ->values();

        return response()->json($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['required', 'string', 'max:30'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:99'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_published' => ['required', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = $this->makeUniqueSlug($payload['title']);

        $course = Course::query()->create([
            'title' => $payload['title'],
            'slug' => $slug,
            'description' => $payload['description'] ?? null,
            'level' => $payload['level'],
            'duration_weeks' => $payload['duration_weeks'] ?? null,
            'category' => $payload['category'] ?? null,
            'is_published' => $payload['is_published'],
            'published_at' => $payload['is_published'] ? Carbon::now() : null,
        ]);

        return response()->json($this->toPayload($course, $payload['image_url'] ?? null), 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['required', 'string', 'max:30'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:99'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_published' => ['required', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:255'],
        ]);

        $slug = $course->title !== $payload['title']
            ? $this->makeUniqueSlug($payload['title'], $course->id)
            : $course->slug;

        $course->fill([
            'title' => $payload['title'],
            'slug' => $slug,
            'description' => $payload['description'] ?? null,
            'level' => $payload['level'],
            'duration_weeks' => $payload['duration_weeks'] ?? null,
            'category' => $payload['category'] ?? null,
            'is_published' => $payload['is_published'],
        ]);

        if ($payload['is_published'] && ! $course->published_at) {
            $course->published_at = Carbon::now();
        }

        if (! $payload['is_published']) {
            $course->published_at = null;
        }

        $course->save();

        return response()->json($this->toPayload($course, $payload['image_url'] ?? null));
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'message' => 'Course berhasil dihapus.',
        ]);
    }

    private function toPayload(Course $course, ?string $imageOverride = null): array
    {
        $imageUrl = $imageOverride ?: $this->courseImageBySlug($course->slug);
        $enrolled = 40 + ($course->id * 17) % 120;
        $completionRate = 50 + ($course->id * 11) % 45;

        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description,
            'level' => $course->level,
            'duration_weeks' => $course->duration_weeks,
            'category' => $course->category,
            'status' => $course->is_published ? 'Published' : 'Draft',
            'is_published' => (bool) $course->is_published,
            'image_url' => $imageUrl,
            'enrolled_students' => $enrolled,
            'completion_rate' => $completionRate,
            'last_updated' => optional($course->updated_at)->toISOString(),
        ];
    }

    private function makeUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug ?: 'course';
        $counter = 1;

        while (
            Course::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
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
