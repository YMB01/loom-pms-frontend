<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SuperAdminLoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SuperAdminAuthController extends Controller
{
    public function login(SuperAdminLoginRequest $request): JsonResponse
    {
        $expectedEmail = config('super_admin.email');
        $expectedPassword = config('super_admin.password');

        if (! is_string($expectedEmail) || $expectedEmail === ''
            || ! is_string($expectedPassword) || $expectedPassword === '') {
            return ApiResponse::error('Super admin is not configured.', 503);
        }

        $email = $request->validated('email');
        $password = $request->validated('password');

        if (! hash_equals(strtolower($expectedEmail), strtolower($email))
            || ! hash_equals($expectedPassword, $password)) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        $plainToken = Str::random(64);
        $ttlHours = max(1, (int) config('super_admin.token_ttl_hours', 8));
        $key = 'super_admin:'.hash('sha256', $plainToken);

        Cache::put($key, ['email' => $email], now()->addHours($ttlHours));

        return ApiResponse::success([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_in_hours' => $ttlHours,
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if ($token) {
            Cache::forget('super_admin:'.hash('sha256', $token));
        }

        return ApiResponse::success([], 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        $email = null;
        if ($token) {
            $payload = Cache::get('super_admin:'.hash('sha256', $token));
            $email = is_array($payload) ? ($payload['email'] ?? null) : null;
        }

        return ApiResponse::success([
            'role' => 'super_admin',
            'email' => $email,
        ], '');
    }
}
