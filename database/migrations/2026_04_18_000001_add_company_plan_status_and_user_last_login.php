<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('plan', ['free', 'basic', 'pro'])->default('free')->after('currency');
            $table->enum('status', ['active', 'suspended', 'trial'])->default('active')->after('plan');
            $table->timestamp('trial_ends_at')->nullable()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['plan', 'status', 'trial_ends_at']);
        });
    }
};
