<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoomPmsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $company = Company::query()->create([
                'name' => 'Loom Solutions PLC',
                'email' => 'info@loomsolutions.com',
                'phone' => '+251911000000',
                'address' => 'Bole Road, Addis Ababa, Ethiopia',
                'currency' => 'ETB',
            ]);

            User::query()->create([
                'company_id' => $company->id,
                'name' => 'admin',
                'email' => 'admin@loomsolutions.com',
                'password' => 'admin123',
                'role' => UserRole::CompanyAdmin,
                'phone' => '+251911000001',
            ]);

            User::query()->create([
                'company_id' => $company->id,
                'name' => 'manager',
                'email' => 'manager@loomsolutions.com',
                'password' => 'admin123',
                'role' => UserRole::PropertyManager,
                'phone' => '+251911000002',
            ]);

            $propertyData = [
                [
                    'name' => 'Bole Heights Residence',
                    'type' => 'Residential',
                    'address' => 'Bole Subcity, Woreda 03',
                    'city' => 'Addis Ababa',
                    'country' => 'Ethiopia',
                    'total_units' => 3,
                ],
                [
                    'name' => 'Kazanchis Tower',
                    'type' => 'Mixed-use',
                    'address' => 'Kazanchis, Kirkos',
                    'city' => 'Addis Ababa',
                    'country' => 'Ethiopia',
                    'total_units' => 3,
                ],
                [
                    'name' => 'Yeka Residence',
                    'type' => 'Residential',
                    'address' => 'Yeka Subcity',
                    'city' => 'Addis Ababa',
                    'country' => 'Ethiopia',
                    'total_units' => 2,
                ],
                [
                    'name' => 'Piassa Commercial',
                    'type' => 'Commercial',
                    'address' => 'Piassa, Addis Ketema',
                    'city' => 'Addis Ababa',
                    'country' => 'Ethiopia',
                    'total_units' => 2,
                ],
            ];

            $properties = collect($propertyData)->map(fn (array $row) => Property::query()->create([
                ...$row,
                'company_id' => $company->id,
            ]));

            $rentBands = [18500, 22000, 19500, 24000, 17500, 21000, 16000, 20000, 23000, 19000];

            $unitsMeta = [
                ['property' => 0, 'number' => 'A-101', 'floor' => '1', 'rent' => $rentBands[0]],
                ['property' => 0, 'number' => 'A-102', 'floor' => '1', 'rent' => $rentBands[1]],
                ['property' => 0, 'number' => 'B-201', 'floor' => '2', 'rent' => $rentBands[2]],
                ['property' => 1, 'number' => 'T1-01', 'floor' => '5', 'rent' => $rentBands[3]],
                ['property' => 1, 'number' => 'T1-02', 'floor' => '6', 'rent' => $rentBands[4]],
                ['property' => 1, 'number' => 'T1-03', 'floor' => '7', 'rent' => $rentBands[5]],
                ['property' => 2, 'number' => 'Y-1', 'floor' => 'G', 'rent' => $rentBands[6]],
                ['property' => 2, 'number' => 'Y-2', 'floor' => '1', 'rent' => $rentBands[7]],
                ['property' => 3, 'number' => 'Shop-01', 'floor' => 'G', 'rent' => $rentBands[8]],
                ['property' => 3, 'number' => 'Shop-02', 'floor' => 'G', 'rent' => $rentBands[9]],
            ];

            $units = collect($unitsMeta)->map(function (array $meta) use ($properties) {
                return Unit::query()->create([
                    'property_id' => $properties[$meta['property']]->id,
                    'unit_number' => $meta['number'],
                    'type' => 'Standard',
                    'floor' => $meta['floor'],
                    'size_sqm' => fake()->randomFloat(2, 45, 120),
                    'rent_amount' => $meta['rent'],
                    'status' => UnitStatus::Available,
                ]);
            });

            $tenantData = [
                ['name' => 'Meron Haile', 'email' => 'meron.haile@demo.loom', 'phone' => '+251911100001', 'id' => 'ETH-MH-10001'],
                ['name' => 'Kebede Tadesse', 'email' => 'kebede.tadesse@demo.loom', 'phone' => '+251911100002', 'id' => 'ETH-KT-10002'],
                ['name' => 'Tigist Bekele', 'email' => 'tigist.bekele@demo.loom', 'phone' => '+251911100003', 'id' => 'ETH-TB-10003'],
                ['name' => 'Dawit Girma', 'email' => 'dawit.girma@demo.loom', 'phone' => '+251911100004', 'id' => 'ETH-DG-10004'],
                ['name' => 'Selamawit Alemu', 'email' => 'selamawit.alemu@demo.loom', 'phone' => '+251911100005', 'id' => 'ETH-SA-10005'],
                ['name' => 'Yonas Tesfaye', 'email' => 'yonas.tesfaye@demo.loom', 'phone' => '+251911100006', 'id' => 'ETH-YT-10006'],
            ];

            $tenants = collect($tenantData)->map(fn (array $row) => Tenant::query()->create([
                'company_id' => $company->id,
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'id_number' => $row['id'],
            ]));

            $leaseStart = Carbon::now()->subMonths(6)->startOfMonth();
            $leaseEnd = Carbon::now()->addMonths(6)->endOfMonth();

            $leases = collect(range(0, 5))->map(function (int $i) use ($tenants, $units, $leaseStart, $leaseEnd) {
                $unit = $units[$i];
                $tenant = $tenants[$i];
                $rent = (float) $unit->rent_amount;

                $unit->update(['status' => UnitStatus::Occupied]);

                return Lease::query()->create([
                    'tenant_id' => $tenant->id,
                    'unit_id' => $unit->id,
                    'start_date' => $leaseStart->toDateString(),
                    'end_date' => $leaseEnd->toDateString(),
                    'rent_amount' => $rent,
                    'deposit_amount' => round($rent * 0.5, 2),
                    'status' => LeaseStatus::Active,
                ]);
            });

            $now = Carbon::now();

            $invoicePlans = [
                ['offset_due' => $now->copy()->subDays(20), 'kind' => 'paid'],
                ['offset_due' => $now->copy()->subDays(10), 'kind' => 'paid'],
                ['offset_due' => $now->copy()->addDays(20), 'kind' => 'pending'],
                ['offset_due' => $now->copy()->addDays(28), 'kind' => 'pending'],
                ['offset_due' => $now->copy()->subDays(45), 'kind' => 'overdue'],
                ['offset_due' => $now->copy()->subDays(35), 'kind' => 'overdue'],
            ];

            foreach ($leases as $index => $lease) {
                $plan = $invoicePlans[$index];
                $tenant = $tenants[$index];
                $due = $plan['offset_due']->copy()->startOfDay();

                $invoice = Invoice::query()->create([
                    'lease_id' => $lease->id,
                    'tenant_id' => $tenant->id,
                    'amount' => $lease->rent_amount,
                    'due_date' => $due->toDateString(),
                    'status' => InvoiceStatus::Pending,
                ]);

                if ($plan['kind'] === 'paid') {
                    Payment::query()->create([
                        'invoice_id' => $invoice->id,
                        'tenant_id' => $tenant->id,
                        'amount' => $invoice->amount,
                        'method' => $index === 0 ? 'telebirr' : 'bank_transfer',
                        'reference' => 'PAY-DEMO-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    ]);
                    $invoice->refresh();
                }

                $invoice->syncStatusFromPayments();
            }
        });
    }
}
