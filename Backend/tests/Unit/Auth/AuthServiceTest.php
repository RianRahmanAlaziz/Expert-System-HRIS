<?php

namespace Tests\Unit\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = app(AuthService::class);
    }

    public function test_it_can_login_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $result = $this->authService->login([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame($user->id, $result->id);
    }

    public function test_it_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->expectException(ValidationException::class);

        $this->authService->login([
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);
    }

    public function test_it_logs_activity_when_user_logs_in(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->authService->login([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $log = ActivityLog::query()
            ->where('action', 'login')
            ->where('module', 'authentication')
            ->where('target_type', $user->getMorphClass())
            ->where('target_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($user->id, $log->user_id);

        $this->assertSame(
            'test@example.com',
            $log->new_values['email']
        );
    }

    public function test_it_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->authService->changePassword(
            $user,
            'new-password123'
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'new-password123',
                $user->password
            )
        );
    }

    public function test_it_logs_activity_when_changing_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->authService->changePassword(
            $user,
            'new-password123'
        );

        $log = ActivityLog::query()
            ->where('action', 'change_password')
            ->where('module', 'authentication')
            ->where('target_type', $user->getMorphClass())
            ->where('target_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($user->id, $log->user_id);

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }

    public function test_it_can_send_forgot_password_link(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with([
                'email' => $user->email,
            ])
            ->andReturn(Password::RESET_LINK_SENT);

        $result = $this->authService->forgotPassword(
            $user->email
        );

        $this->assertSame(
            Password::RESET_LINK_SENT,
            $result
        );
    }

    public function test_it_can_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $result = $this->authService->resetPassword(
            $user->email,
            'new-password123',
            $token
        );

        $this->assertSame(
            Password::PASSWORD_RESET,
            $result
        );

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'new-password123',
                $user->password
            )
        );
    }

    public function test_it_logs_activity_when_resetting_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $this->authService->resetPassword(
            $user->email,
            'new-password123',
            $token
        );

        $log = ActivityLog::query()
            ->where('action', 'reset_password')
            ->where('module', 'authentication')
            ->where('target_type', $user->getMorphClass())
            ->where('target_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $user->id,
            $log->user_id
        );

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
    }
}
