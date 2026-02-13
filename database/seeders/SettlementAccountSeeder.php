<?php

namespace Database\Seeders;

use App\Models\SettlementAccount;
use Illuminate\Database\Seeder;

class SettlementAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            [
                'bank_name' => 'VFD',
                'account_number' => '1021559817',
                'account_name' => 'AGBEDEMEJI LTD-OPERATIONAL ACCOUNT ',
                'description' => 'Primary settlement account for wallet funding',
                'is_active' => true,
                'daily_limit' => 500000000,
                'priority' => 10,
            ]
        ];

        foreach ($accounts as $account) {
            SettlementAccount::create($account);
        }

        $this->command->info('Settlement accounts seeded successfully!');
    }
}