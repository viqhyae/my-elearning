<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $avatarUrl = $payload['avatar_url'] ?? null;

        if ($avatarUrl !== null && ! $this->isAllowedAvatarUrl($avatarUrl)) {
            return response()->json([
                'message' => 'Format URL foto tidak valid. Gunakan URL http/https atau path lokal /images/*.svg.',
            ], 422);
        }

        $user->update([
            'name' => $payload['name'],
            'avatar_url' => $avatarUrl,
        ]);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'current_password' => ['required', 'string', 'min:8', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($payload['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        if ($payload['current_password'] === $payload['password']) {
            return response()->json([
                'message' => 'Password baru harus berbeda dari password saat ini.',
            ], 422);
        }

        $user->update([
            'password' => $payload['password'],
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login ulang.',
        ]);
    }

    private function isAllowedAvatarUrl(string $value): bool
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return true;
        }

        if (str_starts_with($normalized, '/images/') && str_ends_with($normalized, '.svg')) {
            return true;
        }

        return (bool) preg_match('/^https?:\/\/[^\s]+$/i', $normalized);
    }
}
