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
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('rent_amount', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->enum('status', ['active', 'expiring', 'terminated'])->default('active')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['unit_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
