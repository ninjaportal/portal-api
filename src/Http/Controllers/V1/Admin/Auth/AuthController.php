<?php

namespace NinjaPortal\Api\Http\Controllers\V1\Admin\Auth;

use Illuminate\Http\Request;
use NinjaPortal\Api\Contracts\Auth\AuthFlowInterface;
use NinjaPortal\Api\Http\Controllers\Controller;

/**
 * @group Auth (Admin)
 */
class AuthController extends Controller
{
    public function __construct(protected AuthFlowInterface $authFlow) {}

    /**
     * Login (admin)
     *
     * @bodyParam email string required The admin email. Example: admin@example.com
     * @bodyParam password string required The admin password. Example: secret
     *
     * @response 200 {"token_type":"Bearer","access_token":"<jwt>","expires_in":900,"refresh_token":"<token>"}
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
            'admin'
        ));
    }

    /**
     * Refresh token (admin)
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

        return response()->success('Token refreshed.', $this->authFlow->refresh((string) $data['refresh_token'], 'admin'));
    }

    /**
     * Logout (admin)
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

        $this->authFlow->logout((string) $data['refresh_token'], 'admin');

        return response()->success('Logged out.');
    }
}
