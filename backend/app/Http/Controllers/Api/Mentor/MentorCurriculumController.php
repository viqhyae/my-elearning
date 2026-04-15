<?php

namespace App\Http\Controllers\Api\Mentor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MentorCurriculumController extends Controller
{
    public function updateCourse(Request $request, Course $course): JsonResponse
    {
        if (! $this->canAccessCourse($request, $course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kursus ini.',
            ], 403);
        }

        $payload = $request->validate([
            'description' => ['nullable', 'string'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', 'max:80'],
            'trailer_video_url' => ['nullable', 'string', 'max:500'],
        ]);

        $course->update([
            'description' => $payload['description'] ?? null,
            'tools' => $payload['tools'] ?? [],
            'trailer_video_url' => $payload['trailer_video_url'] ?? null,
        ]);

        return response()->json([
            'message' => 'Informasi kursus berhasil diperbarui.',
            'course' => $this->serializeCourse($course->fresh(['modules.lessons', 'mentor:id,name,email'])),
        ]);
    }

    public function uploadTrailer(Request $request, Course $course): JsonResponse
    {
        if (! $this->canAccessCourse($request, $course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kursus ini.',
            ], 403);
        }

        $payload = $request->validate([
            'video' => ['required', 'file', 'max:102400', 'mimetypes:video/mp4,video/webm,video/quicktime'],
        ]);

        $file = $payload['video'];
        $targetDirectory = public_path('uploads/trailers');

        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $filename = sprintf(
            'course-%d-%s-%s.%s',
            $course->id,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $file->getClientOriginalExtension()
        );

        $file->move($targetDirectory, $filename);

        $relativePath = '/uploads/trailers/'.$filename;
        $course->update([
            'trailer_video_url' => rtrim(config('app.url'), '/').$relativePath,
        ]);

        return response()->json([
            'message' => 'Video trailer berhasil diupload.',
            'trailer_video_url' => $course->trailer_video_url,
        ]);
    }

    public function storeModule(Request $request, Course $course): JsonResponse
    {
        if (! $this->canAccessCourse($request, $course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kursus ini.',
            ], 403);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order_no' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['required', 'boolean'],
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'order_no' => $payload['order_no'] ?? $this->nextModuleOrder($course),
            'is_published' => $payload['is_published'],
        ]);

        return response()->json([
            'message' => 'Modul berhasil ditambahkan.',
            'module' => $this->serializeModule($module->fresh(['lessons'])),
        ], 201);
    }

    public function updateModule(Request $request, CourseModule $module): JsonResponse
    {
        if (! $this->canAccessCourse($request, $module->course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke modul ini.',
            ], 403);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order_no' => ['required', 'integer', 'min:1'],
            'is_published' => ['required', 'boolean'],
        ]);

        $module->update([
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'order_no' => $payload['order_no'],
            'is_published' => $payload['is_published'],
        ]);

        return response()->json([
            'message' => 'Modul berhasil diperbarui.',
            'module' => $this->serializeModule($module->fresh(['lessons'])),
        ]);
    }

    public function destroyModule(Request $request, CourseModule $module): JsonResponse
    {
        if (! $this->canAccessCourse($request, $module->course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke modul ini.',
            ], 403);
        }

        $module->delete();

        return response()->json([
            'message' => 'Modul berhasil dihapus.',
        ]);
    }

    public function storeLesson(Request $request, CourseModule $module): JsonResponse
    {
        if (! $this->canAccessCourse($request, $module->course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke modul ini.',
            ], 403);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:120'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'order_no' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['required', 'boolean'],
        ]);

        $lesson = CourseLesson::query()->create([
            'module_id' => $module->id,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'video_url' => $payload['video_url'] ?? null,
            'topics' => $payload['topics'] ?? [],
            'tools' => $payload['tools'] ?? [],
            'duration_minutes' => $payload['duration_minutes'] ?? null,
            'order_no' => $payload['order_no'] ?? $this->nextLessonOrder($module),
            'is_published' => $payload['is_published'],
        ]);

        return response()->json([
            'message' => 'Lesson berhasil ditambahkan.',
            'lesson' => $this->serializeLesson($lesson),
        ], 201);
    }

    public function updateLesson(Request $request, CourseLesson $lesson): JsonResponse
    {
        if (! $this->canAccessCourse($request, $lesson->module->course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke lesson ini.',
            ], 403);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:120'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'order_no' => ['required', 'integer', 'min:1'],
            'is_published' => ['required', 'boolean'],
        ]);

        $lesson->update([
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'video_url' => $payload['video_url'] ?? null,
            'topics' => $payload['topics'] ?? [],
            'tools' => $payload['tools'] ?? [],
            'duration_minutes' => $payload['duration_minutes'] ?? null,
            'order_no' => $payload['order_no'],
            'is_published' => $payload['is_published'],
        ]);

        return response()->json([
            'message' => 'Lesson berhasil diperbarui.',
            'lesson' => $this->serializeLesson($lesson->fresh()),
        ]);
    }

    public function destroyLesson(Request $request, CourseLesson $lesson): JsonResponse
    {
        if (! $this->canAccessCourse($request, $lesson->module->course)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke lesson ini.',
            ], 403);
        }

        $lesson->delete();

        return response()->json([
            'message' => 'Lesson berhasil dihapus.',
        ]);
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

    private function nextModuleOrder(Course $course): int
    {
        $maxOrder = CourseModule::query()
            ->where('course_id', $course->id)
            ->max('order_no');

        return (int) $maxOrder + 1;
    }

    private function nextLessonOrder(CourseModule $module): int
    {
        $maxOrder = CourseLesson::query()
            ->where('module_id', $module->id)
            ->max('order_no');

        return (int) $maxOrder + 1;
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
            'mentor' => $course->mentor ? [
                'id' => $course->mentor->id,
                'name' => $course->mentor->name,
                'email' => $course->mentor->email,
            ] : null,
            'modules' => $course->modules->sortBy('order_no')->map(
                fn (CourseModule $module): array => $this->serializeModule($module)
            )->values()->all(),
        ];
    }

    private function serializeModule(CourseModule $module): array
    {
        return [
            'id' => $module->id,
            'course_id' => $module->course_id,
            'title' => $module->title,
            'description' => $module->description,
            'order_no' => $module->order_no,
            'is_published' => (bool) $module->is_published,
            'lessons' => $module->lessons->sortBy('order_no')->map(
                fn (CourseLesson $lesson): array => $this->serializeLesson($lesson)
            )->values()->all(),
        ];
    }

    private function serializeLesson(CourseLesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'module_id' => $lesson->module_id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'video_url' => $lesson->video_url,
            'topics' => $lesson->topics ?: [],
            'tools' => $lesson->tools ?: [],
            'duration_minutes' => $lesson->duration_minutes,
            'order_no' => $lesson->order_no,
            'is_published' => (bool) $lesson->is_published,
        ];
    }
}

