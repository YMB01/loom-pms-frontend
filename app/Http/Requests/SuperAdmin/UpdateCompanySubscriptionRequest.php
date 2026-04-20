<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_numeric($value)) {
                        $fail('The plan id must be an integer.');

                        return;
                    }
                    if (! Plan::query()->whereKey((int) $value)->where('is_active', true)->exists()) {
                        $fail('The selected plan is invalid.');
                    }
                },
            ],
        ];
    }
}
