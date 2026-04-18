<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\CompanyScopedRequest;

class DashboardIndexRequest extends CompanyScopedRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
