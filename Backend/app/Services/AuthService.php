<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(array $data): User
    {
        $user = User::query()
            ->where('email', $data['email'])
            ->first();

        if (
            $user === null ||
            ! Hash::check($data['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak sesuai.'],
            ]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill([
                'password' => Hash::make($data['password']),
            ])->save();
        }

        return $user;
    }

    public function changePassword(
        User $user,
        string $newPassword
    ): void {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }

    public function forgotPassword(string $email): string
    {
        return Password::sendResetLink([
            'email' => $email,
        ]);
    }

    public function resetPassword(
        string $email,
        string $password,
        string $token
    ): string {
        return Password::reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );
    }
}
