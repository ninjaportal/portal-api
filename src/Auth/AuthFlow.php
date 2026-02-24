<?php

namespace NinjaPortal\Api\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use NinjaPortal\Api\Contracts\Auth\AuthFlowInterface;
use NinjaPortal\Api\Contracts\Auth\TokenServiceInterface;
use NinjaPortal\Api\Events\Auth\LoginAttemptedEvent;
use NinjaPortal\Api\Events\Auth\LoginFailedEvent;
use NinjaPortal\Api\Events\Auth\LoginSucceededEvent;
use NinjaPortal\Api\Support\PortalApiContext;

class AuthFlow implements AuthFlowInterface
{
    public function __construct(
        protected TokenServiceInterface $tokens,
        protected PortalApiContext $context
    ) {}

    public function attemptLogin(string $email, string $password, string $context): array
    {
        $normalizedEmail = trim(strtolower($email));

        Event::dispatch(new LoginAttemptedEvent($context, $normalizedEmail));

        $user = $this->findByEmail($normalizedEmail, $context);

        if (! $user || ! Hash::check($password, (string) ($user->password ?? ''))) {
            Event::dispatch(new LoginFailedEvent($context, $normalizedEmail, 'invalid_credentials'));

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $payload = $this->tokens->issue($user, $context);

        Event::dispatch(new LoginSucceededEvent($context, $normalizedEmail, $user));

        return $payload;
    }

    public function issueForUser(Authenticatable $user, string $context): array
    {
        return $this->tokens->issue($user, $context);
    }

    public function refresh(string $refreshToken, string $context): array
    {
        return $this->tokens->refresh($refreshToken, $context);
    }

    public function logout(string $refreshToken, string $context): void
    {
        $this->tokens->revoke($refreshToken, $context);
    }

    protected function findByEmail(string $email, string $context): ?Authenticatable
    {
        $modelClass = $this->context->modelClassForContext($context);

        /** @var Model|null $user */
        $user = $modelClass::query()->where('email', $email)->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
