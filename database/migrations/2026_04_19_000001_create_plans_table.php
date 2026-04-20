<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('max_properties')->nullable();
            $table->unsignedInteger('max_units')->nullable();
            $table->unsignedInteger('max_tenants')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('plans')->insert([
            [
                'name' => 'Free',
                'slug' => 'free',
                'price' => 0,
                'max_properties' => 1,
                'max_units' => 10,
                'max_tenants' => 20,
                'features' => json_encode(['core_pms', 'single_property']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 29,
                'max_properties' => 5,
                'max_units' => 50,
                'max_tenants' => 100,
                'features' => json_encode(['core_pms', 'reports', 'sms']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 79,
                'max_properties' => null,
                'max_units' => null,
                'max_tenants' => null,
                'features' => json_encode(['core_pms', 'reports', 'sms', 'priority_support', 'unlimited']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
