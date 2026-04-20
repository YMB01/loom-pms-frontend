<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    use InteractsWithCompany;

    public function show(Request $request): JsonResponse
    {
        $company = Company::query()->with('subscription.plan')->findOrFail($this->companyId());
        $user = $request->user();

        return ApiResponse::success([
            'company' => [
                'name' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'currency' => $company->currency,
                'logo' => $company->logo,
            ],
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], '');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'logo' => ['nullable', 'string'],
        ]);

        $company = Company::query()->findOrFail($this->companyId());
        $company->update($data);

        return ApiResponse::success([], 'Settings updated.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::error('Current password is incorrect.', 422);
        }

        $user->update(['password' => $data['password']]);

        return ApiResponse::success([], 'Password updated.');
    }

    public function team(Request $request): JsonResponse
    {
        $users = User::query()
            ->where('company_id', $this->companyId())
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'phone', 'created_at']);

        return ApiResponse::success(['team' => $users], '');
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:company_admin,property_manager'],
        ]);

        $plain = Str::password(12);

        $user = User::query()->create([
            'company_id' => $this->companyId(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $plain,
            'role' => UserRole::from($data['role']),
        ]);

        return ApiResponse::success([
            'user_id' => $user->id,
            'temporary_password' => $plain,
        ], 'Team member created. Share the temporary password securely.', 201);
    }

    public function removeTeamMember(Request $request, User $member): JsonResponse
    {
        if ($member->company_id !== $this->companyId()) {
            return ApiResponse::error('Not found.', 404);
        }

        if ($member->id === $request->user()->id) {
            return ApiResponse::error('You cannot remove yourself.', 422);
        }

        $member->delete();

        return ApiResponse::success([], 'User removed.');
    }

    public function sms(Request $request): JsonResponse
    {
        $company = Company::query()->findOrFail($this->companyId());

        return ApiResponse::success([
            'sms_provider' => $company->sms_provider,
            'sms_sender_id' => $company->sms_sender_id,
            'sms_api_key_set' => filled($company->sms_api_key),
        ], '');
    }

    public function updateSms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sms_provider' => ['nullable', 'string', 'in:africastalking,twilio'],
            'sms_api_key' => ['nullable', 'string'],
            'sms_sender_id' => ['nullable', 'string'],
        ]);

        $company = Company::query()->findOrFail($this->companyId());
        $company->update($data);

        return ApiResponse::success([], 'SMS settings updated.');
    }

    public function subscription(Request $request): JsonResponse
    {
        $company = Company::query()->with(['subscription.plan'])->findOrFail($this->companyId());

        $sub = $company->subscription;

        return ApiResponse::success([
            'subscription' => $sub ? [
                'status' => $sub->status->value,
                'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
                'plan' => $sub->plan ? [
                    'name' => $sub->plan->name,
                    'slug' => $sub->plan->slug,
                    'price' => (string) $sub->plan->price,
                ] : null,
            ] : null,
        ], '');
    }

    public function branding(Request $request): JsonResponse
    {
        $company = Company::query()->findOrFail($this->companyId());

        return ApiResponse::success([
            'brand_name' => $company->brand_name,
            'primary_color' => $company->primary_color ?? '#2563eb',
            'logo' => $company->logo,
            'company_name' => $company->name,
        ], '');
    }

    public function updateBranding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'max:16', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
            'logo' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        $company = Company::query()->findOrFail($this->companyId());
        $company->update(array_filter($data, fn ($v) => $v !== null));

        return ApiResponse::success([], 'Branding updated.');
    }

    public function subscriptionUpgrade(Request $request, StripeBillingService $stripe): JsonResponse
    {
        $data = $request->validate([
            'plan_slug' => ['required', 'string', 'in:basic,pro'],
        ]);

        $company = Company::query()->findOrFail($this->companyId());

        try {
            $url = $stripe->createCheckoutSession($company, $data['plan_slug']);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 503);
        }

        return ApiResponse::success(['url' => $url], '');
    }
}
