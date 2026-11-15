<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 900; // 15 minutes

    #[Exposed]
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $credentials = $this->getCredentialsFromFormData($data);
        $email = Str::lower(trim((string) ($credentials['email'] ?? '')));
        $ip = (string) request()->ip();

        $accountKey = $this->getAccountThrottleKey($email);
        $ipKey = $this->getIpThrottleKey($ip);

        if (
            RateLimiter::tooManyAttempts($accountKey, self::MAX_ATTEMPTS) ||
            RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS)
        ) {
            $seconds = max(
                RateLimiter::availableIn($accountKey),
                RateLimiter::availableIn($ipKey),
            );

            $this->throwLockoutValidationException($seconds);
        }

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();
        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $remember = $data['remember'] ?? false;

        $this->fireAttemptingEvent($authGuard, $credentials, $remember);

        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->recordFailedAttempt($accountKey, $ipKey);
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (! $this->isUserAllowedToAccessPanel($user)) {
            $this->recordFailedAttempt($accountKey, $ipKey);
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (! $authGuard->attemptWhen(
            $credentials,
            fn (Authenticatable $attemptUser): bool => $this->isUserAllowedToAccessPanel($attemptUser),
            $remember,
        )) {
            $this->recordFailedAttempt($accountKey, $ipKey);
            $this->throwFailureValidationException();
        }

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);

        session()->regenerate();
        session(['panel_last_activity_at' => now()->timestamp]);

        return app(LoginResponse::class);
    }

    public static function getAccountThrottleKey(string $email): string
    {
        return 'login:account:' . Str::lower(trim($email));
    }

    public static function getIpThrottleKey(string $ip): string
    {
        return 'login:ip:' . trim($ip);
    }

    protected function recordFailedAttempt(string $accountKey, string $ipKey): void
    {
        RateLimiter::hit($accountKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);
    }

    protected function throwLockoutValidationException(int $seconds): never
    {
        $minutes = (int) ceil($seconds / 60);

        Notification::make()
            ->title('Login dikunci sementara')
            ->body("Terlalu banyak percobaan gagal. Silakan coba lagi dalam {$minutes} menit.")
            ->danger()
            ->send();

        throw ValidationException::withMessages([
            'data.email' => "Akun atau IP dikunci selama {$minutes} menit karena 5 kali gagal login.",
        ]);
    }
}
