<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'role' => ['nullable', 'string', 'in:student,mentor,career'],
        ]);

        $selectedRole = $payload['role'] ?? 'student';
        $role = $selectedRole === 'mentor' ? 'mentor' : 'student';

        /** @var User $user */
        $user = User::query()->create([
            'name' => trim($payload['name']),
            'email' => strtolower(trim($payload['email'])),
            'password' => $payload['password'],
            'role' => $role,
            'status' => 'active',
            'avatar_url' => null,
        ]);

        $token = $user->createToken('lms-token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Akun tidak aktif. Silakan hubungi admin.',
            ], 403);
        }

        $user->tokens()->delete();

        $token = $user->createToken('lms-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->serializeUser($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
