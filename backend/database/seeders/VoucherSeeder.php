<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VoucherSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $projectCourse = Course::query()->where('slug', 'project-management-essentials')->first();
        $communicationCourse = Course::query()->where('slug', 'komunikasi-profesional')->first();

        $voucher = Voucher::query()->updateOrCreate(
            ['code' => 'HEMAT50K'],
            [
                'name' => 'Promo Hemat Bootcamp',
                'promo_price' => 249000,
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(2),
                'ends_at' => Carbon::now()->addMonths(2),
                'notes' => 'Promo untuk kelas awal semester.',
            ]
        );

        $courseIds = collect([$projectCourse?->id, $communicationCourse?->id])
            ->filter(fn ($id) => is_int($id))
            ->values()
            ->all();

        if ($courseIds !== []) {
            $voucher->courses()->sync($courseIds);
        }
    }
}
