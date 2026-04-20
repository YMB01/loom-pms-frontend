<?php

namespace Database\Seeders;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceVendor;
use Illuminate\Database\Seeder;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $materials = MarketplaceCategory::query()->create([
            'name' => 'Materials & Supplies',
            'icon' => 'package',
            'description' => 'Safety, security, and building supplies',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $cleaning = MarketplaceCategory::query()->create([
            'name' => 'Cleaning Services',
            'icon' => 'sparkles',
            'description' => 'Commercial and residential cleaning',
            'is_active' => true,
            'display_order' => 2,
        ]);

        $maintenance = MarketplaceCategory::query()->create([
            'name' => 'Maintenance Crews',
            'icon' => 'wrench',
            'description' => 'Licensed trades and repairs',
            'is_active' => true,
            'display_order' => 3,
        ]);

        $utilities = MarketplaceCategory::query()->create([
            'name' => 'Utilities & Delivery',
            'icon' => 'truck',
            'description' => 'Water, waste, power, and connectivity',
            'is_active' => true,
            'display_order' => 4,
        ]);

        $materialProducts = [
            'Fire extinguishers', 'Smoke detectors', 'CCTV cameras', 'Emergency exit signs',
            'First aid kits', 'Water tanks', 'LED lighting', 'Door access control', 'Intercom systems',
        ];
        $v = $this->makeVendor($materials, 'Materials Hub Ethiopia', 'Wholesale safety & electrical supplies');
        foreach ($materialProducts as $i => $name) {
            $this->product($v, $materials, $name, 1200 + ($i * 100), 'per_unit', 'same_day');
        }

        $cleaningNames = [
            'ProClean Building Services', 'ShineBright Facilities', 'FreshSpace Cleaning',
            'GreenClean Eco Services', 'Elite Janitorial Services', 'AquaJet Pressure Washing',
        ];
        foreach ($cleaningNames as $name) {
            $ven = $this->makeVendor($cleaning, $name, 'Professional cleaning');
            $this->product($ven, $cleaning, 'Standard cleaning visit', 2500, 'per_visit', 'next_day', 'sale');
        }

        $crewNames = [
            'Master Plumbers', 'SafeWire Electricians', 'CoolAir HVAC', 'BuildRight Carpentry',
            'PaintPro Decorators', 'LiftTech Elevator Services', 'SecureGuard Security', 'PestAway Exterminators', 'FixIt Handyman',
        ];
        foreach ($crewNames as $name) {
            $ven = $this->makeVendor($maintenance, $name, 'On-call maintenance crew');
            $this->product($ven, $maintenance, 'Service call', 1500, 'per_hour', '2_days', 'hot');
        }

        $utilNames = [
            'AquaFlow Water Delivery', 'CleanBin Waste Management', 'SwiftGas Cylinder Delivery',
            'SolarPower Installation', 'FiberNet Internet Services', 'GenSet Backup Power',
        ];
        foreach ($utilNames as $name) {
            $ven = $this->makeVendor($utilities, $name, 'Utilities & logistics');
            $this->product($ven, $utilities, 'Monthly service', 3500, 'per_month', 'weekly', 'new');
        }
    }

    private function makeVendor(MarketplaceCategory $cat, string $name, string $desc): MarketplaceVendor
    {
        return MarketplaceVendor::query()->create([
            'company_id' => null,
            'category_id' => $cat->id,
            'name' => $name,
            'description' => $desc,
            'phone' => '+251911'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'email' => \Illuminate\Support\Str::slug($name).'@marketplace.loom.demo',
            'address' => 'Addis Ababa, Ethiopia',
            'rating' => round(random_int(35, 50) / 10, 1),
            'is_active' => true,
            'is_approved' => true,
            'approved_by' => null,
        ]);
    }

    private function product(
        MarketplaceVendor $vendor,
        MarketplaceCategory $cat,
        string $name,
        float $price,
        string $unit,
        string $availability,
        ?string $badge = null
    ): void {
        MarketplaceProduct::query()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $cat->id,
            'name' => $name,
            'description' => 'Marketplace listing',
            'price' => $price,
            'unit' => $unit,
            'availability' => $availability,
            'badge' => $badge,
            'is_active' => true,
        ]);
    }
}
