<?php

namespace NinjaPortal\Api\Http\Controllers\V1\User\Auth;

use Illuminate\Http\Request;
use NinjaPortal\Api\Contracts\Auth\AuthFlowInterface;
use NinjaPortal\Api\Http\Controllers\Controller;
use NinjaPortal\Api\Http\Requests\V1\User\ForgotPasswordRequest;
use NinjaPortal\Api\Http\Requests\V1\User\RegisterRequest;
use NinjaPortal\Api\Http\Requests\V1\User\ResetPasswordRequest;
use NinjaPortal\Portal\Contracts\Services\UserServiceInterface;
use Throwable;

/**
 * @group Auth (Consumer)
 */
class AuthController extends Controller
{
    public function __construct(protected AuthFlowInterface $authFlow) {}

    /**
     * Register (consumer)
     *
     * @bodyParam email string required Example: user@example.com
     * @bodyParam password string required Example: secret
     * @bodyParam password_confirmation string required Example: secret
     * @bodyParam name string Optional Example: Jane Doe
     * @bodyParam first_name string Optional Example: Jane
     * @bodyParam last_name string Optional Example: Doe
     */
    public function register(RegisterRequest $request, UserServiceInterface $users)
    {
        $data = $this->normalizeNameFields($request->validated());
        $user = $users->create($data);

        return response()->created('Registered.', $this->authFlow->issueForUser($user, 'consumer'));
    }

    /**
     * Login (consumer)
     *
     * @bodyParam email string required The user's email. Example: user@example.com
     * @bodyParam password string required The user's password. Example: secret
     *
     * @response 200 {"token_type":"Bearer","access_token":"<jwt>","expires_in":900,"refresh_token":"<token>"}
     * @response 202 {"success":true,"status":202,"message":"MFA challenge required.","data":{"mfa_required":true,"challenge_type":"login","challenge_token":"<token>","driver":"email_otp","context":"consumer","purpose":"login","expires_at":"2026-02-24T20:30:00Z","can_resend":true,"masked_destination":"j***e@example.com"},"meta":null}
     * @response 422 {"message":"Validation failed.","errors":{"email":["Invalid credentials."]}}
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        return response()->success('Logged in.', $this->authFlow->attemptLogin(
            (string) $data['email'],
            (string) $data['password'],
            'consumer'
        ));
    }

    /**
     * Refresh token (consumer)
     *
     * @bodyParam refresh_token string required The refresh token. Example: <token>
     *
     * @response 200 {"token_type":"Bearer","access_token":"<jwt>","expires_in":900,"refresh_token":"<token>"}
     */
    public function refresh(Request $request)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        return response()->success('Token refreshed.', $this->authFlow->refresh((string) $data['refresh_token'], 'consumer'));
    }

    /**
     * Logout (consumer)
     *
     * @bodyParam refresh_token string required The refresh token to revoke. Example: <token>
     *
     * @authenticated
     *
     * @response 200 {"message":"Logged out."}
     */
    public function logout(Request $request)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $this->authFlow->logout((string) $data['refresh_token'], 'consumer');

        return response()->success('Logged out.');
    }

    /**
     * Request password reset (consumer)
     *
     * @bodyParam email string required Example: user@example.com
     */
    public function forgotPassword(ForgotPasswordRequest $request, UserServiceInterface $users)
    {
        $email = (string) $request->validated('email');

        try {
            $users->requestPasswordReset($email);
        } catch (Throwable) {
            // Avoid leaking whether the user exists.
        }

        return response()->success('If the account exists, a reset link has been sent.');
    }

    /**
     * Reset password (consumer)
     *
     * @bodyParam email string required Example: user@example.com
     * @bodyParam token string required Example: <token>
     * @bodyParam password string required Example: newsecret
     * @bodyParam password_confirmation string required Example: newsecret
     */
    public function resetPassword(ResetPasswordRequest $request, UserServiceInterface $users)
    {
        $data = $request->validated();

        $ok = $users->resetPassword(
            $data['email'],
            $data['password'],
            $data['token']
        );

        if (! $ok) {
            return response()->errors('Invalid reset token.', [], 422);
        }

        return response()->success('Password reset successfully.');
    }

    protected function normalizeNameFields(array $data): array
    {
        $hasParts = array_key_exists('first_name', $data) || array_key_exists('last_name', $data);
        if ($hasParts) {
            return $data;
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $data;
        }

        $segments = preg_split('/\s+/', $name);
        $data['first_name'] = $segments[0] ?? $name;
        $data['last_name'] = count($segments) > 1 ? implode(' ', array_slice($segments, 1)) : '';
        unset($data['name']);

        return $data;
    }
}
