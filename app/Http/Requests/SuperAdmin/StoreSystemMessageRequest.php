<?php

namespace App\Http\Requests\SuperAdmin;

use App\Enums\MessageAudience;
use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemMessageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'type' => ['required', 'string', Rule::in(array_column(MessageType::cases(), 'value'))],
            'sent_to' => ['required', 'string', Rule::in(array_column(MessageAudience::cases(), 'value'))],
            'company_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->input('sent_to') === MessageAudience::Specific->value),
                Rule::exists('companies', 'id'),
            ],
            'send_email' => ['sometimes', 'boolean'],
        ];
    }
}
