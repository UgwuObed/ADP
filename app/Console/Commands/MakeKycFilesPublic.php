<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KycApplication;
use Illuminate\Support\Facades\Storage;

class MakeKycFilesPublic extends Command
{
    protected $signature = 'kyc:make-public';
    protected $description = 'Make all existing KYC documents publicly accessible';

    public function handle()
    {
        $this->info('Making existing KYC files public...');
        
        $kycApplications = KycApplication::all();
        $successCount = 0;
        $failCount = 0;

        foreach ($kycApplications as $kyc) {
            $this->info("Processing KYC ID: {$kyc->id}");
            
            if ($kyc->business_certificate) {
                if (Storage::disk('s3')->exists($kyc->business_certificate)) {
                    try {
                        Storage::disk('s3')->setVisibility($kyc->business_certificate, 'public');
                        $this->info("  ✓ Business certificate made public");
                        $successCount++;
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed: " . $e->getMessage());
                        $failCount++;
                    }
                } else {
                    $this->warn("  ⚠ Business certificate not found");
                }
            }
            
            if ($kyc->tax_certificate) {
                if (Storage::disk('s3')->exists($kyc->tax_certificate)) {
                    try {
                        Storage::disk('s3')->setVisibility($kyc->tax_certificate, 'public');
                        $this->info("  ✓ Tax certificate made public");
                        $successCount++;
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed: " . $e->getMessage());
                        $failCount++;
                    }
                } else {
                    $this->warn("  ⚠ Tax certificate not found");
                }
            }
            
            $this->newLine();
        }

        $this->info("✅ Successfully made {$successCount} files public!");
        if ($failCount > 0) {
            $this->error("❌ Failed to make {$failCount} files public");
        }
        
        return 0;
    }
}