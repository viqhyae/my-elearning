<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'status', 'created_at'])
            ->orderBy('id')
            ->get()
            ->map(function (User $user): array {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'avatar_url' => $user->avatar_url,
                    'created_at' => optional($user->created_at)->toISOString(),
                ];
            });

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'in:admin,mentor,student'],
            'status' => ['required', 'in:active,inactive'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $password = $payload['password'] ?: 'password123';

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'role' => $payload['role'],
            'status' => $payload['status'],
            'password' => Hash::make($password),
            'avatar_url' => null,
        ]);

        return response()->json([
            'message' => 'User baru berhasil dibuat.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'avatar_url' => $user->avatar_url,
                'created_at' => optional($user->created_at)->toISOString(),
            ],
            'password' => $password,
        ], 201);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $payload = $request->validate([
            'role' => ['required', 'in:admin,mentor,student'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $currentUser = $request->user();

        if ($currentUser && $currentUser->id === $user->id && $payload['status'] !== 'active') {
            return response()->json([
                'message' => 'Admin tidak bisa menonaktifkan akun sendiri.',
            ], 422);
        }

        $user->update([
            'role' => $payload['role'],
            'status' => $payload['status'],
        ]);

        return response()->json([
            'message' => 'Role user berhasil diperbarui.',
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

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $payload = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $user->update([
            'password' => Hash::make($payload['password']),
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password user berhasil direset.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
