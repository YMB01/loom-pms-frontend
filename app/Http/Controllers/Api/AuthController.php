<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = DB::transaction(function () use ($request) {
            $company = Company::create([
                'name' => $request->validated('company_name'),
                'email' => $request->validated('email'),
                'phone' => $request->validated('phone'),
                'currency' => 'ETB',
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $request->validated('company_name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'role' => UserRole::CompanyAdmin,
                'phone' => $request->validated('phone'),
            ]);

            $user->load('company');
            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => UserResource::make($user)->resolve(),
                'token' => $token,
            ];
        });

        return ApiResponse::success($data, 'Registration successful.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        $user->load('company');
        $token = $user->createToken('auth-token')->plainTextToken;

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve(),
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success([], 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('company');

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve(),
        ], '');
    }
}
