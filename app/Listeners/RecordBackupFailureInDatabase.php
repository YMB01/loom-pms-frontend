<?php

namespace App\Listeners;

use App\Models\BackupRecord;
use Spatie\Backup\Events\BackupHasFailed;

class RecordBackupFailureInDatabase
{
    public function handle(BackupHasFailed $event): void
    {
        $path = $event->backupName ?? ('failed-'.now()->format('Y-m-d-H-i-s').'.zip');

        BackupRecord::query()->create([
            'filename' => $path,
            'size' => 0,
            'status' => 'failed',
            'created_at' => now(),
        ]);
    }
}
