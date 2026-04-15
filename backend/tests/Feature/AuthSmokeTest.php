<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
    }

    #[DataProvider('dummyAccountsProvider')]
    public function test_dummy_account_can_login_and_access_expected_dashboard(
        string $email,
        string $password,
        string $role,
        string $dashboardPath
    ): void {
        $loginResponse = $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.email', $email)
            ->assertJsonPath('user.role', $role)
            ->assertJsonPath('user.status', 'active')
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role', 'status'],
            ]);

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $email)
            ->assertJsonPath('role', $role);

        $this->withToken($token)
            ->getJson($dashboardPath)
            ->assertOk();
    }

    public function test_invalid_password_is_rejected(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@elearning.local',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_student_cannot_access_admin_dashboard(): void
    {
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'student@elearning.local',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::query()
            ->where('email', 'student@elearning.local')
            ->update(['status' => 'inactive']);

        $this->postJson('/api/login', [
            'email' => 'student@elearning.local',
            'password' => 'password123',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Akun tidak aktif. Silakan hubungi admin.');
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function dummyAccountsProvider(): array
    {
        return [
            'admin account' => [
                'admin@elearning.local',
                'password123',
                'admin',
                '/api/admin/dashboard',
            ],
            'mentor account' => [
                'mentor@elearning.local',
                'password123',
                'mentor',
                '/api/mentor/dashboard',
            ],
            'student account' => [
                'student@elearning.local',
                'password123',
                'student',
                '/api/student/dashboard',
            ],
        ];
    }
}
