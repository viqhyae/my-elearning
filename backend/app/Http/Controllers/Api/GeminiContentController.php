<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
                'slug' => $course->slug,
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

        // Grid statistik landing dibuat marketing-oriented (dummy) sesuai permintaan.
        $marketingStudentsInThousands = 12;
        $marketingTotalClasses = max(150, (int) $mappedCourses->count() * 40);
        $marketingMentors = max(
            50,
            User::query()->where('role', 'mentor')->where('status', 'active')->count() + 18
        );
        $marketingRating = 4.9;

        return response()->json([
            'categories' => $mappedCourses->pluck('category')->unique()->take(6)->values()->all(),
            'categoriesPreview' => $this->buildCategoryCards($mappedCourses),
            'stats' => [
                [
                    'label' => 'Siswa Belajar',
                    'duration' => '4.0s',
                    'values' => $this->buildIntegerSlotValues($marketingStudentsInThousands),
                    'suffix' => 'K+',
                ],
                [
                    'label' => 'Total Kelas',
                    'duration' => '4.5s',
                    'values' => $this->buildIntegerSlotValues($marketingTotalClasses),
                    'suffix' => '+',
                ],
                [
                    'label' => 'Mentor Aktif',
                    'duration' => '4.2s',
                    'values' => $this->buildIntegerSlotValues($marketingMentors),
                    'suffix' => '+',
                ],
                [
                    'label' => 'Rating Global',
                    'duration' => '4.8s',
                    'values' => $this->buildDecimalSlotValues($marketingRating),
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

        $paidTransactions = PaymentTransaction::query()
            ->with(['user:id,name,avatar_url', 'course:id,title,slug,category'])
            ->where('status', 'paid')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->values();

        $recentRegistrations = $paidTransactions
            ->take(4)
            ->map(fn (PaymentTransaction $transaction): array => [
                'name' => $transaction->user?->name ?: 'Siswa Baru',
                'course' => $transaction->course?->title ?: 'Kelas Premium',
                'time' => $this->timeAgoLabel($transaction->paid_at ?: $transaction->created_at ?: $now),
                'avatar' => $transaction->user?->avatar_url ?: sprintf('https://i.pravatar.cc/150?img=%d', ($transaction->id % 70) + 1),
            ])
            ->values()
            ->all();

        $publishedCourses = Course::query()
            ->with(['mentor:id,name'])
            ->withCount([
                'paymentTransactions as paid_students_count' => fn ($query) => $query->where('status', 'paid'),
                'modules',
            ])
            ->withSum([
                'paymentTransactions as paid_revenue' => fn ($query) => $query->where('status', 'paid'),
            ], 'final_price')
            ->where('is_published', true)
            ->orderByDesc('updated_at')
            ->get()
            ->values();

        $instructorCourses = $publishedCourses
            ->sortByDesc(fn (Course $course): int => (int) ($course->paid_revenue ?? 0))
            ->take(6)
            ->map(fn (Course $course): array => [
                'title' => $course->title,
                'slug' => $course->slug,
                'img' => $this->courseImageBySlug($course->slug),
                'status' => $course->is_published ? 'Published' : 'Draft',
                'students' => $this->formatCompactNumber((int) ($course->paid_students_count ?? 0)),
                'revenue' => $this->formatJuta((int) ($course->paid_revenue ?? 0)),
                'category' => $course->category ?: 'General',
                'level' => $course->level ?: 'Pemula',
                'mentor' => $course->mentor?->name ?: 'Tim Mentor',
                'updatedAt' => optional($course->updated_at)->format('d M Y'),
                'description' => Str::limit((string) ($course->description ?? ''), 120),
                'modulesCount' => (int) ($course->modules_count ?? 0),
                'lessonsCount' => max(0, (int) ($course->modules_count ?? 0) * 4),
                'completionRate' => min(95, max(40, 45 + (int) floor(((int) ($course->paid_students_count ?? 0)) / 15))),
            ])
            ->values()
            ->all();

        $studentRoleScores = [];
        foreach ($paidTransactions as $transaction) {
            $course = $transaction->course;
            $roadmapKey = $this->resolveRoadmapRole(
                (string) ($course?->title ?? ''),
                (string) ($course?->category ?? ''),
                (string) ($course?->slug ?? '')
            );

            $studentRoleScores[$roadmapKey] = ($studentRoleScores[$roadmapKey] ?? 0) + 1;
        }

        $instructorRoleScores = [];
        foreach ($publishedCourses as $course) {
            $roadmapKey = $this->resolveRoadmapRole(
                (string) $course->title,
                (string) ($course->category ?? ''),
                (string) $course->slug
            );
            $studentsWeight = max(1, (int) ($course->paid_students_count ?? 0));
            $revenueWeight = max(1, (int) round(((int) ($course->paid_revenue ?? 0)) / 250000));

            $instructorRoleScores[$roadmapKey] = ($instructorRoleScores[$roadmapKey] ?? 0)
                + $studentsWeight
                + $revenueWeight;
        }

        $studentOverviewDistribution = $this->normalizeRoadmapDistribution($studentRoleScores);
        $instructorOverviewDistribution = $this->normalizeRoadmapDistribution($instructorRoleScores);
        $dominantStudentRoadmap = $this->dominantRoadmap($studentOverviewDistribution);
        $dominantInstructorRoadmap = $this->dominantRoadmap($instructorOverviewDistribution);

        $studentActiveCourses = $paidTransactions->pluck('course_id')->filter()->unique()->count();
        $studentActiveLearners = $paidTransactions->pluck('user_id')->filter()->unique()->count();
        $studentCompletionRate = min(98, 52 + ($studentActiveCourses * 9));
        $studentCertificates = (int) floor($studentActiveCourses * 0.65);

        $instructorTotalStudents = (int) $publishedCourses->sum(
            fn (Course $course): int => (int) ($course->paid_students_count ?? 0)
        );
        $instructorTotalRevenue = (int) $publishedCourses->sum(
            fn (Course $course): int => (int) ($course->paid_revenue ?? 0)
        );
        $averageRevenuePerClass = $publishedCourses->count() > 0
            ? (int) round($instructorTotalRevenue / max(1, $publishedCourses->count()))
            : 0;

        $notifications = $this->buildNotifications(
            $paidTransactions,
            $recentRegistrations,
            $startOfCurrentMonth,
            $now
        );

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
            'notifications' => $notifications,
            'studentOverview' => [
                'title' => 'Overview Belajar Siswa',
                'subtitle' => 'Performa roadmap berdasarkan transaksi kelas berbayar.',
                'dominantRole' => $dominantStudentRoadmap['label'],
                'dominantPercent' => $dominantStudentRoadmap['percent'],
                'roleDistribution' => $studentOverviewDistribution,
                'metrics' => [
                    [
                        'label' => 'Kelas Aktif',
                        'value' => number_format($studentActiveCourses, 0, ',', '.'),
                        'helper' => 'Kelas yang sudah dibeli dan dipelajari',
                    ],
                    [
                        'label' => 'Siswa Aktif',
                        'value' => number_format($studentActiveLearners, 0, ',', '.'),
                        'helper' => 'Akun siswa dengan transaksi paid',
                    ],
                    [
                        'label' => 'Rata-rata Completion',
                        'value' => sprintf('%d%%', $studentCompletionRate),
                        'helper' => 'Estimasi kemajuan lintas kelas',
                    ],
                    [
                        'label' => 'Sertifikat Potensial',
                        'value' => number_format($studentCertificates, 0, ',', '.'),
                        'helper' => 'Estimasi sertifikat dari kelas tuntas',
                    ],
                ],
            ],
            'instructorOverview' => [
                'title' => 'Overview Performa Instruktur',
                'subtitle' => 'Distribusi minat siswa pada roadmap kelas yang Anda ajarkan.',
                'dominantRole' => $dominantInstructorRoadmap['label'],
                'dominantPercent' => $dominantInstructorRoadmap['percent'],
                'roleDistribution' => $instructorOverviewDistribution,
                'metrics' => [
                    [
                        'label' => 'Total Siswa',
                        'value' => $this->formatCompactNumber($instructorTotalStudents),
                        'helper' => 'Akumulasi peserta kelas berbayar',
                    ],
                    [
                        'label' => 'Kelas Published',
                        'value' => number_format($publishedCourses->count(), 0, ',', '.'),
                        'helper' => 'Kelas aktif di katalog Segara Digital',
                    ],
                    [
                        'label' => 'Revenue Kotor',
                        'value' => $this->formatJuta($instructorTotalRevenue),
                        'helper' => 'Total pemasukan dari transaksi paid',
                    ],
                    [
                        'label' => 'Rata-rata/Kelas',
                        'value' => $this->formatJuta($averageRevenuePerClass),
                        'helper' => 'Pendapatan rata-rata per kelas',
                    ],
                ],
            ],
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
            [
                'icon' => 'ph-duotone ph-device-mobile',
                'color' => 'text-orange-500',
                'hoverColor' => 'group-hover:text-orange-400',
                'hoverAnim' => 'group-hover:animate-bounce-gentle group-hover:drop-shadow-[0_0_15px_rgba(249,115,22,0.6)]',
            ],
            [
                'icon' => 'ph-duotone ph-chart-line-up',
                'color' => 'text-sky-500',
                'hoverColor' => 'group-hover:text-sky-400',
                'hoverAnim' => 'group-hover:animate-pulse-fast group-hover:drop-shadow-[0_0_15px_rgba(14,165,233,0.6)]',
            ],
            [
                'icon' => 'ph-duotone ph-robot',
                'color' => 'text-indigo-500',
                'hoverColor' => 'group-hover:text-indigo-400',
                'hoverAnim' => 'group-hover:animate-fly-up group-hover:drop-shadow-[0_0_15px_rgba(99,102,241,0.6)]',
            ],
            [
                'icon' => 'ph-duotone ph-briefcase',
                'color' => 'text-amber-500',
                'hoverColor' => 'group-hover:text-amber-400',
                'hoverAnim' => 'group-hover:animate-swing group-hover:drop-shadow-[0_0_15px_rgba(245,158,11,0.6)]',
            ],
        ];

        $groupedByRole = $courses
            ->groupBy(fn (array $course): string => $this->roleLabelFromCategory((string) ($course['category'] ?? 'General')))
            ->map(fn (Collection $items): int => $items->count())
            ->sortDesc()
            ->take(8);

        if ($groupedByRole->isEmpty()) {
            return [
                [
                    ...$presets[0],
                    'name' => 'Web Development',
                    'count' => 0,
                ],
            ];
        }

        $cards = [];
        $index = 0;

        foreach ($groupedByRole as $name => $count) {
            $preset = $presets[$index % count($presets)];
            $cards[] = [
                ...$preset,
                'name' => (string) $name,
                'count' => (int) $count,
            ];
            $index++;
        }

        return $cards;
    }

    private function roleLabelFromCategory(string $category): string
    {
        $normalized = Str::lower(trim($category));

        if (Str::contains($normalized, ['web', 'frontend'])) {
            return 'Frontend Engineer';
        }

        if (Str::contains($normalized, ['backend', 'database', 'api'])) {
            return 'Backend Engineer';
        }

        if (Str::contains($normalized, ['devops', 'cloud', 'server'])) {
            return 'DevOps Engineer';
        }

        if (Str::contains($normalized, ['mobile', 'android', 'ios'])) {
            return 'Mobile Developer';
        }

        if (Str::contains($normalized, ['ui', 'ux', 'design'])) {
            return 'UI/UX Designer';
        }

        if (Str::contains($normalized, ['data', 'analytics', 'machine learning', 'ai'])) {
            return 'Data Scientist';
        }

        if (Str::contains($normalized, ['security', 'cyber'])) {
            return 'Cyber Security Engineer';
        }

        if (Str::contains($normalized, ['soft skill', 'komunikasi', 'communication'])) {
            return 'People & Communication Specialist';
        }

        if (Str::contains($normalized, ['productivity', 'project'])) {
            return 'Project & Product Specialist';
        }

        return $category;
    }

    /**
     * @param Collection<int, PaymentTransaction> $paidTransactions
     * @param array<int, array{name: string, course: string, time: string, avatar: string}> $recentRegistrations
     * @return array<int, array{id: string, title: string, message: string, time: string, tone: string}>
     */
    private function buildNotifications(
        Collection $paidTransactions,
        array $recentRegistrations,
        Carbon $startOfCurrentMonth,
        Carbon $now
    ): array {
        $latestPaid = $paidTransactions->first();
        $pendingTransactions = PaymentTransaction::query()->where('status', 'pending')->count();
        $paidThisMonth = PaymentTransaction::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$startOfCurrentMonth, $now])
            ->count();
        $newPublishedCourses = Course::query()
            ->where('is_published', true)
            ->whereBetween('published_at', [$startOfCurrentMonth, $now])
            ->count();

        $notifications = [];

        if ($latestPaid instanceof PaymentTransaction) {
            $notifications[] = [
                'id' => sprintf('payment-%d', $latestPaid->id),
                'title' => 'Pembayaran Baru Terkonfirmasi',
                'message' => sprintf(
                    '%s baru menyelesaikan pembayaran untuk kelas %s.',
                    $latestPaid->user?->name ?: 'Seorang siswa',
                    $latestPaid->course?->title ?: 'Premium Class'
                ),
                'time' => $this->timeAgoLabel($latestPaid->paid_at ?: $latestPaid->created_at ?: $now),
                'tone' => 'success',
            ];
        }

        if ($pendingTransactions > 0) {
            $notifications[] = [
                'id' => 'pending-payment',
                'title' => 'Checkout Menunggu Pembayaran',
                'message' => sprintf('%d transaksi masih berstatus pending.', $pendingTransactions),
                'time' => 'Baru saja',
                'tone' => 'warning',
            ];
        }

        $notifications[] = [
            'id' => 'paid-this-month',
            'title' => 'Performa Bulan Ini',
            'message' => sprintf('Total %d transaksi paid tercatat bulan ini.', $paidThisMonth),
            'time' => 'Update harian',
            'tone' => 'info',
        ];

        if ($newPublishedCourses > 0) {
            $notifications[] = [
                'id' => 'new-course',
                'title' => 'Kelas Baru Dipublikasikan',
                'message' => sprintf('%d kelas baru tayang bulan ini.', $newPublishedCourses),
                'time' => 'Update katalog',
                'tone' => 'success',
            ];
        }

        if (count($notifications) < 3 && count($recentRegistrations) > 0) {
            $latestRegistration = $recentRegistrations[0];
            $notifications[] = [
                'id' => 'latest-registration',
                'title' => 'Aktivitas Siswa Terbaru',
                'message' => sprintf(
                    '%s baru membeli kelas %s.',
                    $latestRegistration['name'],
                    $latestRegistration['course']
                ),
                'time' => $latestRegistration['time'],
                'tone' => 'info',
            ];
        }

        return array_slice($notifications, 0, 5);
    }

    /**
     * @param array<string, int|float> $scores
     * @return array<int, array{key: string, label: string, color: string, percent: int}>
     */
    private function normalizeRoadmapDistribution(array $scores): array
    {
        $metaList = $this->roadmapRoleMeta();

        $baseScores = [];
        foreach ($metaList as $meta) {
            $baseScores[$meta['key']] = 0.0;
        }

        foreach ($scores as $key => $score) {
            if (! array_key_exists($key, $baseScores)) {
                continue;
            }
            $baseScores[$key] += (float) $score;
        }

        $totalScore = array_sum($baseScores);
        if ($totalScore <= 0) {
            return [
                ['key' => 'frontend', 'label' => 'Frontend Developer', 'color' => '#3B82F6', 'percent' => 28],
                ['key' => 'backend', 'label' => 'Backend Developer', 'color' => '#0EA5E9', 'percent' => 22],
                ['key' => 'devops', 'label' => 'DevOps Engineer', 'color' => '#10B981', 'percent' => 16],
                ['key' => 'mobile', 'label' => 'Mobile Developer', 'color' => '#F97316', 'percent' => 11],
                ['key' => 'uiux', 'label' => 'UI/UX Designer', 'color' => '#EC4899', 'percent' => 13],
                ['key' => 'data', 'label' => 'Data Scientist', 'color' => '#8B5CF6', 'percent' => 10],
            ];
        }

        $distribution = [];
        $runningPercent = 0;
        $highestKey = 'frontend';
        $highestValue = -1.0;

        foreach ($metaList as $meta) {
            $key = $meta['key'];
            $value = $baseScores[$key] ?? 0.0;
            $percent = (int) floor(($value / $totalScore) * 100);
            $runningPercent += $percent;

            if ($value > $highestValue) {
                $highestValue = $value;
                $highestKey = $key;
            }

            $distribution[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'percent' => $percent,
            ];
        }

        $remaining = 100 - $runningPercent;
        if ($remaining > 0) {
            foreach ($distribution as &$item) {
                if ($item['key'] === $highestKey) {
                    $item['percent'] += $remaining;
                    break;
                }
            }
            unset($item);
        }

        return $distribution;
    }

    /**
     * @param array<int, array{key: string, label: string, color: string, percent: int}> $distribution
     * @return array{label: string, percent: int}
     */
    private function dominantRoadmap(array $distribution): array
    {
        $dominant = collect($distribution)->sortByDesc('percent')->first();

        if (! is_array($dominant)) {
            return [
                'label' => 'Frontend Developer',
                'percent' => 0,
            ];
        }

        return [
            'label' => (string) ($dominant['label'] ?? 'Frontend Developer'),
            'percent' => (int) ($dominant['percent'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, color: string}>
     */
    private function roadmapRoleMeta(): array
    {
        return [
            ['key' => 'frontend', 'label' => 'Frontend Developer', 'color' => '#3B82F6'],
            ['key' => 'backend', 'label' => 'Backend Developer', 'color' => '#0EA5E9'],
            ['key' => 'devops', 'label' => 'DevOps Engineer', 'color' => '#10B981'],
            ['key' => 'mobile', 'label' => 'Mobile Developer', 'color' => '#F97316'],
            ['key' => 'uiux', 'label' => 'UI/UX Designer', 'color' => '#EC4899'],
            ['key' => 'data', 'label' => 'Data Scientist', 'color' => '#8B5CF6'],
        ];
    }

    private function resolveRoadmapRole(string $title, string $category, string $slug): string
    {
        $haystack = Str::lower(trim(implode(' ', [$title, $category, $slug])));

        if (Str::contains($haystack, ['frontend', 'nuxt', 'vue', 'javascript', 'html', 'css', 'web'])) {
            return 'frontend';
        }

        if (Str::contains($haystack, ['backend', 'api', 'laravel', 'golang', 'php', 'database', 'postgres', 'redis'])) {
            return 'backend';
        }

        if (Str::contains($haystack, ['devops', 'docker', 'kubernetes', 'nginx', 'cloud', 'server', 'ci/cd'])) {
            return 'devops';
        }

        if (Str::contains($haystack, ['mobile', 'flutter', 'android', 'ios', 'react native'])) {
            return 'mobile';
        }

        if (Str::contains($haystack, ['ui', 'ux', 'design', 'figma', 'komunikasi', 'communication'])) {
            return 'uiux';
        }

        if (Str::contains($haystack, ['data', 'analytics', 'analysis', 'machine learning', 'ai'])) {
            return 'data';
        }

        return 'frontend';
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
            'project-management-essentials' => 'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=800&q=80',
            'komunikasi-profesional' => 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=800&q=80',
            'data-analysis-fundamentals' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&q=80',
            'fullstack-web-nuxt-4-laravel-13-api-enterprise' => 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=800&q=80',
            'dasar-pemrograman-web-html-css-js' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=800&q=80',
            'modern-javascript-masterclass-es6-plus' => 'https://images.unsplash.com/photo-1579468118864-1b9ea3c0db4a?w=800&q=80',
            'mastering-postgresql-17-redis-7-caching' => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=800&q=80',
            'laravel-sanctum-auth-rest-api-security' => 'https://images.unsplash.com/photo-1555099962-4199c345e5dd?w=800&q=80',
            'golang-microservices-grpc-architecture' => 'https://images.unsplash.com/photo-1623479322729-28b25c16b011?w=800&q=80',
            'docker-compose-nginx-reverse-proxy-setup' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&q=80',
            'kubernetes-k8s-cicd-pipelines-mastery' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=800&q=80',
            'ui-ux-design-system-untuk-aplikasi-saas' => 'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?w=800&q=80',
            'figma-to-code-ui-ux-advanced-prototyping' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=800&q=80',
            'belajar-fundamental-python-data-science' => 'https://images.unsplash.com/photo-1526379879527-8559ecfcaec0?w=800&q=80',
            'machine-learning-with-python-scikit-learn' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&q=80',
            'mastering-react-native-2026' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=800&q=80',
            'flutter-state-management-bloc-riverpod' => 'https://images.unsplash.com/photo-1526498460520-4c246339dccb?w=800&q=80',
            'cyber-security-fundamental-untuk-web-engineer' => 'https://images.unsplash.com/photo-1510511459019-5dda7724fd87?w=800&q=80',
            default => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=800&q=80',
        };
    }
}
