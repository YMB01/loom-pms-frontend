<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('marketplace_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained('marketplace_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category_id', 'is_active', 'is_approved']);
            $table->index('company_id');
        });

        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('marketplace_vendors')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('marketplace_categories')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('unit', 32);
            $table->string('availability', 32);
            $table->string('badge', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['vendor_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
        });

        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('marketplace_vendors')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('marketplace_products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->date('preferred_date')->nullable();
            $table->text('special_instructions')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expires_at']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('type', 16)->default('info')->index();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_read', 'created_at']);
        });

        Schema::create('lease_renewal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo')->nullable();
            $table->string('sms_provider')->nullable();
            $table->text('sms_api_key')->nullable();
            $table->string('sms_sender_id')->nullable();
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->string('lease_document')->nullable()->after('status');
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            $table->json('photos')->nullable()->after('description');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('id_document')->nullable()->after('id_number');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('id_document');
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('photos');
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('lease_document');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['logo', 'sms_provider', 'sms_api_key', 'sms_sender_id']);
        });

        Schema::dropIfExists('lease_renewal_requests');
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('marketplace_vendors');
        Schema::dropIfExists('marketplace_categories');
    }
};
