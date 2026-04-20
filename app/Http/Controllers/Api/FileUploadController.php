<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InteractsWithCompany;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    use InteractsWithCompany;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'folder' => ['required', 'string', 'in:leases,maintenance,profiles,branding'],
        ]);

        $path = $request->file('file')->store("uploads/{$data['folder']}", 'public');
        $url = Storage::disk('public')->url($path);

        return ApiResponse::success([
            'path' => $path,
            'url' => $url,
            'size' => $request->file('file')->getSize(),
            'mime_type' => $request->file('file')->getMimeType(),
        ], 'Uploaded.');
    }
}
