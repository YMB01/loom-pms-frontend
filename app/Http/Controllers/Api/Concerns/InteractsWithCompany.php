<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;

trait InteractsWithCompany
{
    protected function companyId(): int
    {
        $id = auth()->user()?->company_id;

        if ($id === null) {
            throw new HttpResponseException(
                ApiResponse::error('No company context for this user.', 403)
            );
        }

        return (int) $id;
    }
}
