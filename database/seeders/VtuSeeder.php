<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Network;
use App\Models\DataPlan;
use App\Models\ElectricityProvider;
use App\Models\CommissionSetting;

class VtuSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedNetworks();
        $this->seedDataPlans();
        $this->seedCommissionSettings();
    }

    private function seedNetworks(): void
    {
        $networks = [
            ['name' => 'MTN', 'code' => 'mtn', 'logo' => '/images/networks/mtn.png'],
            ['name' => 'Glo', 'code' => 'glo', 'logo' => '/images/networks/glo.png'],
            ['name' => 'Airtel', 'code' => 'airtel', 'logo' => '/images/networks/airtel.png'],
            ['name' => '9mobile', 'code' => '9mobile', 'logo' => '/images/networks/9mobile.png'],
        ];

        foreach ($networks as $network) {
            Network::updateOrCreate(
                ['code' => $network['code']],
                array_merge($network, ['is_active' => true, 'airtime_enabled' => true, 'data_enabled' => true])
            );
        }
    }

    private function seedDataPlans(): void
    {
        // NOTE: Data plans should be synced from Topupbox API
        // Run: php artisan topupbox:sync-plans
        // 
        // The data_code field should contain the tariffTypeId from Topupbox
        // 
        // Example structure from API:
        // GET https://api.topupbox.com/api/v2/w1/data-price-point/MTN
        // Response contains tariffTypeId which you use for purchases
        //
        // For now, we'll leave this empty - sync from API instead
        
        \Illuminate\Support\Facades\Log::info('Data plans should be synced from Topupbox API. Run: php artisan topupbox:sync-plans');
    }

 

    private function seedCommissionSettings(): void
    {
        // Default airtime discounts per network
        $airtimeSettings = [
            ['product_type' => 'airtime', 'network' => 'mtn', 'default_discount_percent' => 3.0],
            ['product_type' => 'airtime', 'network' => 'glo', 'default_discount_percent' => 4.0],
            ['product_type' => 'airtime', 'network' => 'airtel', 'default_discount_percent' => 3.5],
            ['product_type' => 'airtime', 'network' => '9mobile', 'default_discount_percent' => 4.0],
        ];

        foreach ($airtimeSettings as $setting) {
            CommissionSetting::updateOrCreate(
                ['product_type' => $setting['product_type'], 'network' => $setting['network']],
                array_merge($setting, ['is_active' => true, 'min_amount' => 50, 'max_amount' => 50000])
            );
        }


    }
}