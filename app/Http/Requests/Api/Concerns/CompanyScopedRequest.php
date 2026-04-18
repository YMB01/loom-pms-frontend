<?php

namespace App\Http\Requests\Api\Concerns;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class CompanyScopedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company_id !== null;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error('No company context for this user.', 403)
        );
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'data' => $validator->errors(),
                'message' => $validator->errors()->first() ?: 'The given data was invalid.',
            ], 422)
        );
    }
}
