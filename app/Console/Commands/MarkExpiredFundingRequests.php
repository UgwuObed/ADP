<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;

class MarkExpiredFundingRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:mark-expired-funding';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired funding requests as expired';

    /**
     * Execute the console command.
     */
    public function handle(WalletService $walletService): int
    {
        $this->info('Checking for expired funding requests...');

        $count = $walletService->markExpiredRequests();

        if ($count > 0) {
            $this->info("Marked {$count} funding request(s) as expired.");
        } else {
            $this->info('No expired funding requests found.');
        }

        return Command::SUCCESS;
    }
}