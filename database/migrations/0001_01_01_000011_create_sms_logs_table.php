<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('to_number')->index();
            $table->text('message');
            $table->string('trigger')->nullable()->index();
            $table->string('provider')->nullable()->index();
            $table->string('status')->index();
            $table->decimal('cost', 10, 4)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
