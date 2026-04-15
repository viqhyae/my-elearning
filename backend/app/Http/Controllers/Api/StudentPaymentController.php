<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PaymentTransaction;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StudentPaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $transactions = PaymentTransaction::query()
            ->with([
                'course:id,title,slug,price_amount,currency',
                'voucher:id,name,code,promo_price',
            ])
            ->where('user_id', $user?->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PaymentTransaction $transaction): array => $this->toPayload($transaction))
            ->values();

        return response()->json($transactions);
    }

    public function checkout(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'course_id' => ['required', 'integer'],
            'voucher_code' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['required', 'in:bank_transfer,ewallet,qris'],
        ]);

        $user = $request->user();

        $course = Course::query()
            ->where('id', $payload['course_id'])
            ->where('is_published', true)
            ->first();

        if (! $course) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan atau belum dipublish.',
            ], 404);
        }

        $hasPaid = PaymentTransaction::query()
            ->where('user_id', $user?->id)
            ->where('course_id', $course->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaid) {
            return response()->json([
                'message' => 'Kelas ini sudah pernah dibayar oleh akun Anda.',
            ], 422);
        }

        $voucher = null;
        $discountAmount = 0;

        if (! empty($payload['voucher_code'])) {
            $voucher = $this->resolveVoucher($payload['voucher_code'], $course);

            if (! $voucher) {
                return response()->json([
                    'message' => 'Voucher tidak valid, tidak aktif, atau tidak berlaku untuk kelas ini.',
                ], 422);
            }

            $discountAmount = max(0, $course->price_amount - min($course->price_amount, $voucher->promo_price));
        }

        $finalPrice = max(0, $course->price_amount - $discountAmount);

        $transaction = PaymentTransaction::query()->create([
            'reference' => $this->generateReference(),
            'user_id' => $user?->id,
            'course_id' => $course->id,
            'voucher_id' => $voucher?->id,
            'payment_method' => $payload['payment_method'],
            'status' => 'pending',
            'original_price' => $course->price_amount,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
        ]);

        $transaction->load(['course:id,title,slug,price_amount,currency', 'voucher:id,name,code,promo_price']);

        return response()->json([
            'message' => 'Checkout berhasil dibuat. Lanjutkan ke proses bayar.',
            'transaction' => $this->toPayload($transaction),
        ], 201);
    }

    public function pay(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        $user = $request->user();

        if (! $user || ($transaction->user_id !== $user->id && $user->role !== 'admin')) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk transaksi ini.',
            ], 403);
        }

        if ($transaction->status === 'paid') {
            $transaction->load(['course:id,title,slug,price_amount,currency', 'voucher:id,name,code,promo_price']);

            return response()->json([
                'message' => 'Transaksi ini sudah dibayar sebelumnya.',
                'transaction' => $this->toPayload($transaction),
            ]);
        }

        $transaction->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        $transaction->load(['course:id,title,slug,price_amount,currency', 'voucher:id,name,code,promo_price']);

        return response()->json([
            'message' => 'Pembayaran berhasil dikonfirmasi.',
            'transaction' => $this->toPayload($transaction),
        ]);
    }

    private function resolveVoucher(string $code, Course $course): ?Voucher
    {
        $now = Carbon::now();
        $normalizedCode = Str::upper(trim($code));

        return Voucher::query()
            ->whereRaw('upper(code) = ?', [$normalizedCode])
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->whereHas('courses', fn ($query) => $query->where('courses.id', $course->id))
            ->first();
    }

    private function generateReference(): string
    {
        do {
            $reference = 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (PaymentTransaction::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function toPayload(PaymentTransaction $transaction): array
    {
        $course = $transaction->course;
        $voucher = $transaction->voucher;
        $currency = $course?->currency ?: 'IDR';

        return [
            'id' => $transaction->id,
            'reference' => $transaction->reference,
            'status' => $transaction->status,
            'payment_method' => $transaction->payment_method,
            'course' => $course ? [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ] : null,
            'voucher' => $voucher ? [
                'id' => $voucher->id,
                'name' => $voucher->name,
                'code' => $voucher->code,
                'promo_price' => $voucher->promo_price,
            ] : null,
            'original_price' => $transaction->original_price,
            'discount_amount' => $transaction->discount_amount,
            'final_price' => $transaction->final_price,
            'original_price_label' => $this->formatCurrency($transaction->original_price, $currency),
            'discount_amount_label' => $this->formatCurrency($transaction->discount_amount, $currency),
            'final_price_label' => $this->formatCurrency($transaction->final_price, $currency),
            'created_at' => optional($transaction->created_at)->toISOString(),
            'paid_at' => optional($transaction->paid_at)->toISOString(),
        ];
    }

    private function formatCurrency(int $amount, string $currency): string
    {
        return $currency.' '.number_format($amount, 0, ',', '.');
    }
}
