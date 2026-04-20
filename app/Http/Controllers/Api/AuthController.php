<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanyStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

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
                'status' => CompanyStatus::Active,
            ]);

            $freePlan = Plan::query()->where('slug', 'free')->firstOrFail();

            Subscription::query()->create([
                'company_id' => $company->id,
                'plan_id' => $freePlan->id,
                'status' => SubscriptionStatus::Trial,
                'trial_ends_at' => Carbon::now()->addDays(14),
                'current_period_start' => null,
                'current_period_end' => null,
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $request->validated('company_name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'role' => UserRole::CompanyAdmin,
                'phone' => $request->validated('phone'),
            ]);

            $user->load(['company.subscription.plan']);
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
        $email = $request->validated('email');
        $user = User::query()
            ->whereRaw('lower(email) = lower(?)', [$email])
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $user->load(['company.subscription.plan']);
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
        $user->load(['company.subscription.plan']);

        return ApiResponse::success([
            'user' => UserResource::make($user)->resolve(),
        ], '');
    }
}
