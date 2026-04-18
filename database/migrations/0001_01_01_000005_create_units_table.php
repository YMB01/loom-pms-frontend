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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('unit_number');
            $table->string('type')->nullable();
            $table->string('floor')->nullable();
            $table->decimal('size_sqm', 10, 2)->nullable();
            $table->decimal('rent_amount', 12, 2);
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available')->index();
            $table->timestamps();

            $table->unique(['property_id', 'unit_number']);
            $table->index(['property_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
