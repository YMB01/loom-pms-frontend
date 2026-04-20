<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\SendSmsJob;
use App\Models\OtpCode;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PortalAuthController extends Controller
{
    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:32'],
        ]);

        $normalized = $this->normalizePhone($data['phone']);

        $tenant = Tenant::query()
            ->where(function ($q) use ($normalized, $data): void {
                $q->where('phone', $data['phone'])
                    ->orWhere('phone', $normalized);
            })
            ->first();

        if (! $tenant) {
            return ApiResponse::error('No tenant found for this phone number.', 404);
        }

        $code = (string) random_int(100000, 999999);

        OtpCode::query()->create([
            'tenant_id' => $tenant->id,
            'code' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        SendSmsJob::dispatch(
            $normalized,
            "Your Loom tenant portal code is {$code}. It expires in 10 minutes.",
            'portal_otp',
            $tenant->company_id
        );

        $payload = [
            'sent' => true,
            'expires_in_minutes' => 10,
        ];

        // Local / QA only: allows automated tests without SMS delivery.
        if (config('app.debug')) {
            $payload['debug_code'] = $code;
        }

        return ApiResponse::success($payload, 'OTP sent.');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $normalized = $this->normalizePhone($data['phone']);

        $tenant = Tenant::query()
            ->where(function ($q) use ($normalized, $data): void {
                $q->where('phone', $data['phone'])
                    ->orWhere('phone', $normalized);
            })
            ->first();

        if (! $tenant) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        $otp = OtpCode::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $otp || ! Hash::check($data['code'], $otp->code)) {
            return ApiResponse::error('Invalid or expired code.', 401);
        }

        $otp->update(['used_at' => now()]);

        $tenant->tokens()->delete();
        $token = $tenant->createToken('tenant-portal')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'tenant_id' => $tenant->id,
        ], 'Verified.');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? $phone;

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $phone = ltrim($phone, '0');
        $code = (string) config('services.sms.default_country_code', '251');

        return '+'.$code.$phone;
    }
}
