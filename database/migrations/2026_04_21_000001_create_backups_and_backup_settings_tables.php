<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'created_at']);
        });

        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->string('frequency', 16)->default('daily');
            $table->timestamps();
        });

        DB::table('backup_settings')->insert([
            'frequency' => 'daily',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
        Schema::dropIfExists('backups');
    }
};
