<?php

namespace App\Listeners;

use App\Models\BackupRecord;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupWasSuccessful;

class RecordBackupInDatabase
{
    public function handle(BackupWasSuccessful $event): void
    {
        $disk = Storage::disk($event->diskName);
        $path = $event->backupName;

        $size = 0;
        if ($disk->exists($path)) {
            try {
                $size = $disk->size($path);
            } catch (\Throwable) {
                $size = 0;
            }
        }

        BackupRecord::query()->create([
            'filename' => $path,
            'size' => $size,
            'status' => 'completed',
            'created_at' => now(),
        ]);
    }
}
