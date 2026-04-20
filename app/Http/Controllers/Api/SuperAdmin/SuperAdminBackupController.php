<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateBackupSettingsRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\BackupRecord;
use App\Models\BackupSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SuperAdminBackupController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = BackupRecord::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (BackupRecord $b) => [
                'id' => $b->id,
                'filename' => $b->displayName(),
                'disk_path' => $b->filename,
                'size' => (int) $b->size,
                'status' => $b->status,
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        return ApiResponse::success(['backups' => $rows], '');
    }

    public function settings(): JsonResponse
    {
        $setting = BackupSetting::query()->first();

        return ApiResponse::success([
            'frequency' => $setting?->frequency ?? 'daily',
        ], '');
    }

    public function updateSettings(UpdateBackupSettingsRequest $request): JsonResponse
    {
        $setting = BackupSetting::query()->firstOrNew([]);
        $setting->frequency = $request->validated('frequency');
        $setting->save();

        return ApiResponse::success([
            'frequency' => $setting->frequency,
        ], 'Backup schedule updated.');
    }

    public function run(): JsonResponse
    {
        File::ensureDirectoryExists(storage_path('backups'));

        RunDatabaseBackupJob::dispatch();

        return ApiResponse::success([
            'queued' => true,
        ], 'Database backup has been queued. It will appear in the list when complete.');
    }

    public function download(BackupRecord $backup): BinaryFileResponse|JsonResponse
    {
        $disk = Storage::disk('backups');
        if (! $disk->exists($backup->filename)) {
            return ApiResponse::error('Backup file is no longer on disk.', 404);
        }

        $absolute = $disk->path($backup->filename);

        return response()->download($absolute, $backup->displayName(), [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function destroy(BackupRecord $backup): JsonResponse
    {
        $disk = Storage::disk('backups');
        if ($disk->exists($backup->filename)) {
            $disk->delete($backup->filename);
        }

        $backup->delete();

        return ApiResponse::success([], 'Backup deleted.');
    }
}
