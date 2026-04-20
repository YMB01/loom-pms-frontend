<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\Marketplace\MarketplaceCatalogController;
use App\Http\Controllers\Api\Marketplace\MarketplaceOrderController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Portal\PortalAuthController;
use App\Http\Controllers\Api\Portal\PortalTenantController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SmsLogController;
use App\Http\Controllers\Api\WhatsappLogController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminAuthController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminBackupController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminBillingController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminCompanyController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminMarketplaceController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminMessageController;
use App\Http\Controllers\Api\SuperAdmin\SuperAdminPlanController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\InboxMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('throttle:api-public')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::post('/super-admin/login', [SuperAdminAuthController::class, 'login']);

    Route::post('/stripe/webhook', StripeWebhookController::class);

    Route::post('/portal/request-otp', [PortalAuthController::class, 'requestOtp']);
    Route::post('/portal/verify-otp', [PortalAuthController::class, 'verifyOtp']);
});

Route::middleware(['auth:sanctum', 'portal.tenant', 'throttle:api-protected'])->prefix('portal')->group(function () {
    Route::get('/me', [PortalTenantController::class, 'me']);
    Route::get('/invoices', [PortalTenantController::class, 'invoices']);
    Route::get('/payments', [PortalTenantController::class, 'payments']);
    Route::post('/payments', [PortalTenantController::class, 'payRent']);
    Route::get('/maintenance', [PortalTenantController::class, 'maintenanceIndex']);
    Route::post('/maintenance', [PortalTenantController::class, 'maintenanceStore']);
    Route::get('/lease', [PortalTenantController::class, 'lease']);
    Route::post('/lease/renewal', [PortalTenantController::class, 'leaseRenewal']);
});

Route::middleware(['super.admin', 'throttle:api-protected'])->prefix('super-admin')->group(function () {
    Route::post('/logout', [SuperAdminAuthController::class, 'logout']);
    Route::get('/me', [SuperAdminAuthController::class, 'me']);
    Route::get('/dashboard', [SuperAdminDashboardController::class, 'index']);
    Route::get('/plans', [SuperAdminPlanController::class, 'index']);
    Route::get('/companies', [SuperAdminCompanyController::class, 'index']);
    Route::patch('/companies/{company}/subscription', [SuperAdminCompanyController::class, 'updateSubscription']);
    Route::patch('/companies/{company}/activate', [SuperAdminCompanyController::class, 'activate']);
    Route::patch('/companies/{company}/suspend', [SuperAdminCompanyController::class, 'suspend']);
    Route::delete('/companies/{company}', [SuperAdminCompanyController::class, 'destroy']);

    Route::get('/messages', [SuperAdminMessageController::class, 'index']);
    Route::post('/messages', [SuperAdminMessageController::class, 'store']);

    Route::get('/backups/settings', [SuperAdminBackupController::class, 'settings']);
    Route::patch('/backups/settings', [SuperAdminBackupController::class, 'updateSettings']);
    Route::post('/backups/run', [SuperAdminBackupController::class, 'run']);
    Route::get('/backups', [SuperAdminBackupController::class, 'index']);
    Route::get('/backups/{backup}/download', [SuperAdminBackupController::class, 'download']);
    Route::delete('/backups/{backup}', [SuperAdminBackupController::class, 'destroy']);

    Route::get('/billing', [SuperAdminBillingController::class, 'index']);

    Route::get('/marketplace/vendors', [SuperAdminMarketplaceController::class, 'vendorsIndex']);
    Route::post('/marketplace/vendors', [SuperAdminMarketplaceController::class, 'vendorsStore']);
    Route::put('/marketplace/vendors/{vendor}', [SuperAdminMarketplaceController::class, 'vendorsUpdate']);
    Route::delete('/marketplace/vendors/{vendor}', [SuperAdminMarketplaceController::class, 'vendorsDestroy']);
    Route::put('/marketplace/vendors/{vendor}/approve', [SuperAdminMarketplaceController::class, 'vendorsApprove']);

    Route::get('/marketplace/products', [SuperAdminMarketplaceController::class, 'productsIndex']);
    Route::post('/marketplace/products', [SuperAdminMarketplaceController::class, 'productsStore']);
    Route::put('/marketplace/products/{product}', [SuperAdminMarketplaceController::class, 'productsUpdate']);
    Route::delete('/marketplace/products/{product}', [SuperAdminMarketplaceController::class, 'productsDestroy']);

    Route::get('/marketplace/orders', [SuperAdminMarketplaceController::class, 'ordersIndex']);

    Route::get('/marketplace/categories', [SuperAdminMarketplaceController::class, 'categoriesIndex']);
    Route::post('/marketplace/categories', [SuperAdminMarketplaceController::class, 'categoriesStore']);
    Route::put('/marketplace/categories/{category}', [SuperAdminMarketplaceController::class, 'categoriesUpdate']);
});

Route::middleware(['auth:sanctum', 'company.staff', 'company.subscription', 'throttle:api-protected'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/billing/options', [BillingController::class, 'options']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::post('/billing/portal', [BillingController::class, 'portal']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/settings/branding', [SettingsController::class, 'branding']);
    Route::put('/settings/branding', [SettingsController::class, 'updateBranding']);
    Route::put('/settings/password', [SettingsController::class, 'updatePassword']);
    Route::get('/settings/team', [SettingsController::class, 'team']);
    Route::post('/settings/team', [SettingsController::class, 'invite']);
    Route::delete('/settings/team/{member}', [SettingsController::class, 'removeTeamMember']);
    Route::get('/settings/sms', [SettingsController::class, 'sms']);
    Route::put('/settings/sms', [SettingsController::class, 'updateSms']);
    Route::get('/settings/subscription', [SettingsController::class, 'subscription']);
    Route::post('/settings/subscription/upgrade', [SettingsController::class, 'subscriptionUpgrade']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{in_app_notification}/read', [NotificationController::class, 'read']);
    Route::put('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::get('/notifications/count', [NotificationController::class, 'count']);

    Route::post('/upload', [FileUploadController::class, 'store']);

    Route::get('/reports/occupancy', [ReportController::class, 'occupancy']);
    Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/reports/overdue', [ReportController::class, 'overdue']);
    Route::get('/reports/maintenance', [ReportController::class, 'maintenanceReport']);
    Route::get('/reports/leases', [ReportController::class, 'leases']);
    Route::get('/reports/sms', [ReportController::class, 'sms']);

    Route::get('/marketplace/categories', [MarketplaceCatalogController::class, 'categories']);
    Route::get('/marketplace/vendors', [MarketplaceCatalogController::class, 'vendors']);
    Route::get('/marketplace/products', [MarketplaceCatalogController::class, 'products']);
    Route::get('/marketplace/orders', [MarketplaceOrderController::class, 'index']);
    Route::post('/marketplace/orders', [MarketplaceOrderController::class, 'store']);
    Route::put('/marketplace/orders/{marketplace_order}', [MarketplaceOrderController::class, 'update']);

    Route::post('/invoices/generate-monthly', [InvoiceController::class, 'generateMonthly']);
    Route::get('/invoices/chart-summary', [InvoiceController::class, 'chartSummary']);
    Route::apiResource('invoices', InvoiceController::class);

    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('units', UnitController::class);
    Route::get('/tenants/{tenant}/payment-history-chart', [TenantController::class, 'paymentHistoryChart']);
    Route::apiResource('tenants', TenantController::class);
    Route::apiResource('leases', LeaseController::class);
    Route::apiResource('payments', PaymentController::class);
    Route::get('/maintenance/stats-summary', [MaintenanceController::class, 'statsSummary']);
    Route::apiResource('maintenance', MaintenanceController::class);

    Route::get('/sms-logs', [SmsLogController::class, 'index']);
    Route::get('/whatsapp-logs', [WhatsappLogController::class, 'index']);

    Route::get('/inbox/messages/unread-count', [InboxMessageController::class, 'unreadCount']);
    Route::get('/inbox/messages', [InboxMessageController::class, 'index']);
    Route::post('/inbox/messages/read-all', [InboxMessageController::class, 'markAllRead']);
    Route::post('/inbox/messages/{message}/read', [InboxMessageController::class, 'markRead']);
});
