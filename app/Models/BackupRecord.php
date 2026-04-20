<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRecord extends Model
{
    protected $table = 'backups';

    public $timestamps = false;

    protected $fillable = [
        'filename',
        'size',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function displayName(): string
    {
        return basename(str_replace('\\', '/', $this->filename));
    }
}
