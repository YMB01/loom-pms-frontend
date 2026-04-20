<?php

namespace App\Providers;

use App\Http\Responses\ApiResponse;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Listeners\RecordBackupFailureInDatabase;
use App\Listeners\RecordBackupInDatabase;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        File::ensureDirectoryExists(storage_path('backups'));

        Event::listen(BackupWasSuccessful::class, RecordBackupInDatabase::class);
        Event::listen(BackupHasFailed::class, RecordBackupFailureInDatabase::class);

        $this->configureRateLimiting();

        $this->bindCompanyScoped('property', Property::class, fn ($q, int $cid) => $q->where('company_id', $cid));
        $this->bindCompanyScoped('unit', Unit::class, fn ($q, int $cid) => $q->forCompany($cid));
        $this->bindCompanyScoped('tenant', Tenant::class, fn ($q, int $cid) => $q->where('company_id', $cid));
        $this->bindCompanyScoped('lease', Lease::class, fn ($q, int $cid) => $q->forCompany($cid));
        $this->bindCompanyScoped('invoice', Invoice::class, fn ($q, int $cid) => $q->forCompany($cid));
        $this->bindCompanyScoped('payment', Payment::class, fn ($q, int $cid) => $q->forCompany($cid));
        $this->bindCompanyScoped('maintenance', MaintenanceRequest::class, fn ($q, int $cid) => $q->forCompany($cid));
    }

    /**
     * @param  \Closure(\Illuminate\Database\Eloquent\Builder, int): \Illuminate\Database\Eloquent\Builder  $scope
     */
    private function bindCompanyScoped(string $key, string $modelClass, \Closure $scope): void
    {
        Route::bind($key, function (string $value) use ($modelClass, $scope) {
            $user = auth()->user();
            if ($user?->company_id === null) {
                throw new HttpResponseException(
                    ApiResponse::error('No company context for this user.', 403)
                );
            }

            $cid = (int) $user->company_id;

            $query = $modelClass::query();
            $query = $scope($query, $cid);

            return $query->whereKey($value)->firstOrFail();
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('api-protected', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }
}
