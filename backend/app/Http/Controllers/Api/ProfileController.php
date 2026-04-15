<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'avatar_url' => ['nullable', 'string', 'max:1000'],
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

    public function uploadAvatar(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:3072', 'mimetypes:image/jpeg,image/png,image/webp'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var UploadedFile $file */
        $file = $payload['avatar'];

        $targetDirectory = public_path('uploads/avatars');

        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $extension = Str::lower($file->getClientOriginalExtension());

        if ($extension === '') {
            $extension = match ($file->getMimeType()) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }

        $filename = sprintf(
            'avatar-%d-%s-%s.%s',
            $user->id,
            now()->format('YmdHis'),
            Str::lower(Str::random(8)),
            $extension
        );

        $file->move($targetDirectory, $filename);

        $relativePath = '/uploads/avatars/'.$filename;
        $avatarUrl = rtrim(config('app.url'), '/').$relativePath;

        $this->deleteOldLocalAvatar($user->avatar_url);

        $user->update([
            'avatar_url' => $avatarUrl,
        ]);

        return response()->json([
            'message' => 'Foto profil berhasil diupload.',
            'avatar_url' => $avatarUrl,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'avatar_url' => $avatarUrl,
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
        $appBase = rtrim(config('app.url'), '/');

        if ($normalized === '') {
            return true;
        }

        if (str_starts_with($normalized, '/uploads/avatars/')) {
            return true;
        }

        if (str_starts_with($normalized, '/images/') && str_ends_with($normalized, '.svg')) {
            return true;
        }

        if ($appBase !== '' && str_starts_with($normalized, $appBase.'/uploads/avatars/')) {
            return true;
        }

        return (bool) preg_match('/^https?:\/\/[^\s]+$/i', $normalized);
    }

    private function deleteOldLocalAvatar(?string $avatarUrl): void
    {
        if (! $avatarUrl) {
            return;
        }

        $normalized = trim($avatarUrl);
        $appBase = rtrim(config('app.url'), '/');

        if (str_starts_with($normalized, '/uploads/avatars/')) {
            $relativePath = $normalized;
        } elseif ($appBase !== '' && str_starts_with($normalized, $appBase.'/uploads/avatars/')) {
            $relativePath = substr($normalized, strlen($appBase));
        } else {
            return;
        }

        if (! is_string($relativePath) || trim($relativePath) === '') {
            return;
        }

        $absolutePath = public_path(ltrim($relativePath, '/'));

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
