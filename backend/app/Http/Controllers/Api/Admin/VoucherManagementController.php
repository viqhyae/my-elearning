<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VoucherManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $vouchers = Voucher::query()
            ->with(['courses:id,title,slug'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Voucher $voucher): array => $this->toPayload($voucher))
            ->values();

        return response()->json($vouchers);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $voucher = Voucher::query()->create([
            'name' => $payload['name'],
            'code' => Str::upper($payload['code']),
            'promo_price' => $payload['promo_price'],
            'is_active' => $payload['is_active'],
            'starts_at' => $payload['starts_at'] ? Carbon::parse($payload['starts_at']) : null,
            'ends_at' => $payload['ends_at'] ? Carbon::parse($payload['ends_at']) : null,
            'notes' => $payload['notes'] ?? null,
        ]);

        $voucher->courses()->sync($payload['course_ids']);
        $voucher->load(['courses:id,title,slug']);

        return response()->json($this->toPayload($voucher), 201);
    }

    public function update(Request $request, Voucher $voucher): JsonResponse
    {
        $payload = $this->validatePayload($request, $voucher->id);

        $voucher->update([
            'name' => $payload['name'],
            'code' => Str::upper($payload['code']),
            'promo_price' => $payload['promo_price'],
            'is_active' => $payload['is_active'],
            'starts_at' => $payload['starts_at'] ? Carbon::parse($payload['starts_at']) : null,
            'ends_at' => $payload['ends_at'] ? Carbon::parse($payload['ends_at']) : null,
            'notes' => $payload['notes'] ?? null,
        ]);

        $voucher->courses()->sync($payload['course_ids']);
        $voucher->load(['courses:id,title,slug']);

        return response()->json($this->toPayload($voucher));
    }

    public function destroy(Voucher $voucher): JsonResponse
    {
        $voucher->delete();

        return response()->json([
            'message' => 'Voucher berhasil dihapus.',
        ]);
    }

    /**
     * @return array{
     *     name: string,
     *     code: string,
     *     promo_price: int,
     *     is_active: bool,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     notes: string|null,
     *     course_ids: array<int, int>
     * }
     */
    private function validatePayload(Request $request, ?int $ignoreVoucherId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('vouchers', 'code')->ignore($ignoreVoucherId),
            ],
            'promo_price' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'integer', Rule::exists('courses', 'id')],
        ]);
    }

    private function toPayload(Voucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'promo_price' => (int) $voucher->promo_price,
            'promo_price_label' => 'IDR '.number_format((int) $voucher->promo_price, 0, ',', '.'),
            'is_active' => (bool) $voucher->is_active,
            'starts_at' => optional($voucher->starts_at)->toISOString(),
            'ends_at' => optional($voucher->ends_at)->toISOString(),
            'notes' => $voucher->notes,
            'course_ids' => $voucher->courses->pluck('id')->values()->all(),
            'courses' => $voucher->courses->map(
                fn ($course): array => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                ]
            )->values()->all(),
            'updated_at' => optional($voucher->updated_at)->toISOString(),
        ];
    }
}
