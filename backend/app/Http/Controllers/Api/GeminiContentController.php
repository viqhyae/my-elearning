<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GeminiContentController extends Controller
{
    public function frontend(): JsonResponse
    {
        $courses = Course::query()
            ->with(['mentor:id,name'])
            ->withCount([
                'paymentTransactions as paid_students_count' => fn ($query) => $query->where('status', 'paid'),
            ])
            ->orderByDesc('id')
            ->get();

        $mappedCourses = $courses->map(function (Course $course): array {
            $mentorName = $course->mentor?->name ?: 'Tim Mentor';
            $paidStudents = (int) ($course->paid_students_count ?? 0);
            $rating = $this->courseRating($course->id);

            return [
                'id' => $course->id,
                'title' => $course->title,
                'category' => $course->category ?: 'General',
                'level' => $course->level ?: 'Pemula',
                'instructor' => $mentorName,
                'instructorInitials' => $this->initials($mentorName),
                'price' => $course->price_amount > 0 ? $this->formatCurrency((int) $course->price_amount) : 'Gratis',
                'rating' => number_format($rating, 1, '.', ''),
                'students' => $this->formatCompactNumber($paidStudents),
                'image' => $this->courseImageBySlug($course->slug),
            ];
        })->values();

        $studentsCount = User::query()->where('role', 'student')->count();
        $mentorCount = User::query()->where('role', 'mentor')->where('status', 'active')->count();
        $ratingAverage = $mappedCourses->isNotEmpty()
            ? round(
                $mappedCourses->avg(fn (array $course): float => (float) $course['rating']) ?: 4.8,
                1
            )
            : 4.8;

        return response()->json([
            'categories' => $mappedCourses->pluck('category')->unique()->take(6)->values()->all(),
            'categoriesPreview' => $this->buildCategoryCards($mappedCourses),
            'stats' => [
                [
                    'label' => 'Siswa Belajar',
                    'duration' => '4.0s',
                    'values' => $this->buildIntegerSlotValues($studentsCount),
                    'suffix' => $studentsCount >= 1000 ? 'K+' : '+',
                ],
                [
                    'label' => 'Total Kelas',
                    'duration' => '4.5s',
                    'values' => $this->buildIntegerSlotValues($mappedCourses->count()),
                    'suffix' => '+',
                ],
                [
                    'label' => 'Mentor Aktif',
                    'duration' => '4.2s',
                    'values' => $this->buildIntegerSlotValues($mentorCount),
                    'suffix' => '+',
                ],
                [
                    'label' => 'Rating Global',
                    'duration' => '4.8s',
                    'values' => $this->buildDecimalSlotValues($ratingAverage),
                    'suffix' => '/5',
                ],
            ],
            'allCourses' => $mappedCourses,
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $now = Carbon::now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $startOfCurrentMonth->copy()->subMonthNoOverflow();
        $endOfPreviousMonth = $startOfCurrentMonth->copy()->subSecond();

        $totalRevenue = (int) PaymentTransaction::query()
            ->where('status', 'paid')
            ->sum('final_price');

        $revenueCurrentMonth = (int) PaymentTransaction::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startOfCurrentMonth, $now])
            ->sum('final_price');

        $revenuePreviousMonth = (int) PaymentTransaction::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->sum('final_price');

        $totalStudents = User::query()->where('role', 'student')->count();
        $newStudentsCurrentMonth = User::query()
            ->where('role', 'student')
            ->whereBetween('created_at', [$startOfCurrentMonth, $now])
            ->count();
        $newStudentsPreviousMonth = User::query()
            ->where('role', 'student')
            ->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->count();

        $activeInstructors = User::query()
            ->where('role', 'mentor')
            ->where('status', 'active')
            ->count();
        $newInstructorsCurrentMonth = User::query()
            ->where('role', 'mentor')
            ->whereBetween('created_at', [$startOfCurrentMonth, $now])
            ->count();
        $newInstructorsPreviousMonth = User::query()
            ->where('role', 'mentor')
            ->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->count();

        $totalCourses = Course::query()->count();
        $newCoursesCurrentMonth = Course::query()
            ->whereBetween('created_at', [$startOfCurrentMonth, $now])
            ->count();
        $newCoursesPreviousMonth = Course::query()
            ->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->count();

        $recentRegistrations = PaymentTransaction::query()
            ->with(['user:id,name,avatar_url', 'course:id,title'])
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->take(4)
            ->get()
            ->map(fn (PaymentTransaction $transaction): array => [
                'name' => $transaction->user?->name ?: 'Siswa Baru',
                'course' => $transaction->course?->title ?: 'Kelas Premium',
                'time' => $this->timeAgoLabel($transaction->paid_at ?: $transaction->created_at ?: $now),
                'avatar' => $transaction->user?->avatar_url ?: sprintf('https://i.pravatar.cc/150?img=%d', ($transaction->id % 70) + 1),
            ])
            ->values()
            ->all();

        $instructorCourses = Course::query()
            ->withCount([
                'paymentTransactions as paid_students_count' => fn ($query) => $query->where('status', 'paid'),
            ])
            ->withSum([
                'paymentTransactions as paid_revenue' => fn ($query) => $query->where('status', 'paid'),
            ], 'final_price')
            ->where('is_published', true)
            ->orderByDesc('paid_revenue')
            ->orderByDesc('paid_students_count')
            ->orderByDesc('id')
            ->take(2)
            ->get()
            ->map(fn (Course $course): array => [
                'title' => $course->title,
                'img' => $this->courseImageBySlug($course->slug),
                'status' => $course->is_published ? 'Published' : 'Draft',
                'students' => $this->formatCompactNumber((int) ($course->paid_students_count ?? 0)),
                'revenue' => $this->formatJuta((int) ($course->paid_revenue ?? 0)),
            ])
            ->values()
            ->all();

        return response()->json([
            'adminStats' => [
                [
                    'label' => 'Total Pemasukan',
                    'value' => $this->formatJuta($totalRevenue),
                    'icon' => 'ph-fill ph-money',
                    'colorClass' => 'bg-emerald-50 text-emerald-600',
                    'trend' => $this->trendPercent($revenueCurrentMonth, $revenuePreviousMonth),
                ],
                [
                    'label' => 'Siswa Terdaftar',
                    'value' => number_format($totalStudents, 0, ',', '.'),
                    'icon' => 'ph-fill ph-users',
                    'colorClass' => 'bg-blue-50 text-blue-600',
                    'trend' => $this->trendPercent($newStudentsCurrentMonth, $newStudentsPreviousMonth),
                ],
                [
                    'label' => 'Instruktur Aktif',
                    'value' => number_format($activeInstructors, 0, ',', '.'),
                    'icon' => 'ph-fill ph-chalkboard-teacher',
                    'colorClass' => 'bg-purple-50 text-purple-600',
                    'trend' => $this->trendPercent($newInstructorsCurrentMonth, $newInstructorsPreviousMonth),
                ],
                [
                    'label' => 'Kelas Tersedia',
                    'value' => number_format($totalCourses, 0, ',', '.'),
                    'icon' => 'ph-fill ph-play-circle',
                    'colorClass' => 'bg-orange-50 text-orange-600',
                    'trend' => $this->trendPercent($newCoursesCurrentMonth, $newCoursesPreviousMonth),
                ],
            ],
            'recentRegistrations' => $recentRegistrations,
            'instructorCourses' => $instructorCourses,
        ]);
    }

    /**
     * @param Collection<int, array<string, mixed>> $courses
     * @return array<int, array{name: string, icon: string, count: int, color: string, hoverColor: string, hoverAnim: string}>
     */
    private function buildCategoryCards(Collection $courses): array
    {
        $presets = [
            [
                'icon' => 'ph-duotone ph-browser',
                'color' => 'text-blue-500',
                'hoverColor' => 'group-hover:text-blue-400',
                'hoverAnim' => 'group-hover:animate-bounce-gentle group-hover:drop-shadow-[0_0_15px_rgba(59,130,246,0.6)]',
            ],
            [
                'icon' => 'ph-duotone ph-database',
                'color' => 'text-emerald-500',
                'hoverColor' => 'group-hover:text-emerald-400',
                'hoverAnim' => 'group-hover:animate-pulse-fast group-hover:drop-shadow-[0_0_15px_rgba(16,185,129,0.6)]',
            ],
            [
                'icon' => 'ph-duotone ph-cloud-arrow-up',
                'color' => 'text-purple-500',
                'hoverColor' => 'group-hover:text-purple-400',
                'hoverAnim' => 'group-hover:animate-fly-up group-hover:drop-shadow-[0_0_15px_rgba(168,85,247,0.6)]',
            ],
            [
                'icon' => 'ph-duotone ph-paint-brush-broad',
                'color' => 'text-pink-500',
                'hoverColor' => 'group-hover:text-pink-400',
                'hoverAnim' => 'group-hover:animate-swing group-hover:drop-shadow-[0_0_15px_rgba(236,72,153,0.6)]',
            ],
        ];

        $grouped = $courses
            ->groupBy(fn (array $course): string => (string) ($course['category'] ?? 'General'))
            ->map(fn (Collection $items): int => $items->count())
            ->sortDesc()
            ->take(4)
            ->values();

        if ($grouped->isEmpty()) {
            return [
                [
                    ...$presets[0],
                    'name' => 'Web Development',
                    'count' => 0,
                ],
            ];
        }

        $categoryNames = $courses
            ->groupBy(fn (array $course): string => (string) ($course['category'] ?? 'General'))
            ->sortByDesc(fn (Collection $items): int => $items->count())
            ->keys()
            ->take(4)
            ->values();

        return $categoryNames
            ->map(function (string $name, int $index) use ($grouped, $presets): array {
                $preset = $presets[$index % count($presets)];

                return [
                    ...$preset,
                    'name' => $name,
                    'count' => (int) ($grouped[$index] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function buildIntegerSlotValues(int $target): array
    {
        $target = max(0, $target);
        $steps = 8;
        $values = [];

        for ($i = 0; $i <= $steps; $i++) {
            $value = (int) round(($target / $steps) * $i);
            $values[] = number_format($value, 0, ',', '.');
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function buildDecimalSlotValues(float $target): array
    {
        $target = max(0.0, $target);
        $start = max(0.0, $target - 3.2);
        $steps = 8;
        $values = [];

        for ($i = 0; $i <= $steps; $i++) {
            $value = $start + (($target - $start) / $steps) * $i;
            $values[] = number_format($value, 1, '.', '');
        }

        return $values;
    }

    private function courseRating(int $courseId): float
    {
        return min(5.0, 4.5 + (($courseId * 7) % 6) / 10);
    }

    private function initials(string $name): string
    {
        $parts = collect(explode(' ', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_substr($part, 0, 1));

        if ($parts->isEmpty()) {
            return 'TM';
        }

        return mb_strtoupper($parts->implode(''));
    }

    private function formatCurrency(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function formatJuta(int $amount): string
    {
        if ($amount <= 0) {
            return 'Rp 0 Jt';
        }

        $million = $amount / 1_000_000;
        $formatted = rtrim(rtrim(number_format($million, 1, '.', ''), '0'), '.');

        return sprintf('Rp %s Jt', $formatted);
    }

    private function formatCompactNumber(int $value): string
    {
        if ($value >= 1000) {
            $compact = $value / 1000;

            return rtrim(rtrim(number_format($compact, 1, '.', ''), '0'), '.') . 'k';
        }

        return number_format($value, 0, ',', '.');
    }

    private function trendPercent(int $current, int $previous): int
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    private function timeAgoLabel(Carbon $timestamp): string
    {
        $minutes = $timestamp->diffInMinutes(Carbon::now());

        if ($minutes < 60) {
            return sprintf('%d Menit lalu', max(1, $minutes));
        }

        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return sprintf('%d Jam lalu', $hours);
        }

        $days = (int) floor($hours / 24);

        return sprintf('%d Hari lalu', $days);
    }

    private function courseImageBySlug(string $slug): string
    {
        return match ($slug) {
            'project-management-essentials' => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=800&q=80',
            'komunikasi-profesional' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&q=80',
            'data-analysis-fundamentals' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&q=80',
            default => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=800&q=80',
        };
    }
}

