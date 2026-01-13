<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckVFDConnection extends Command
{
    protected $signature = 'vfd:check';
    protected $description = 'Check VFD API configuration and connectivity';

    public function handle()
    {
        $this->info('🔍 Checking VFD Configuration...');
        $this->newLine();

        // Check config
        $baseUrl = config('services.vfd.base_url');
        $accessToken = config('services.vfd.access_token');

        $this->info('📋 Configuration:');
        $this->line("  Base URL: " . ($baseUrl ?: '❌ NOT SET'));
        $this->line("  Access Token: " . ($accessToken ? '✅ SET (' . strlen($accessToken) . ' chars)' : '❌ NOT SET'));
        $this->newLine();

        if (empty($baseUrl) || empty($accessToken)) {
            $this->error('❌ VFD configuration is incomplete!');
            $this->info('💡 Make sure these are set in your .env file:');
            $this->line('  VFD_BASE_URL');
            $this->line('  VFD_ACCESS_TOKEN');
            return 1;
        }

        // Test connectivity
        $this->info('🌐 Testing connectivity to VFD API...');
        
        try {
            $testUrl = "{$baseUrl}/account/enquiry";
            
            $this->line("  Testing: {$testUrl}");
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'AccessToken' => $accessToken,
                    'Accept' => 'application/json',
                ])
                ->get($testUrl);

            $this->newLine();
            $this->info('📊 Response Details:');
            $this->line("  Status Code: " . $response->status());
            $this->line("  Response Time: " . ($response->handlerStats()['total_time'] ?? 'N/A') . 's');
            
            $data = $response->json();
            
            if ($response->successful() && isset($data['status'])) {
                $this->info("✅ VFD API is reachable!");
                $this->line("  VFD Status: " . $data['status']);
                $this->line("  VFD Message: " . ($data['message'] ?? 'N/A'));
            } else {
                $this->warn("⚠️  API responded but with unexpected format");
                $this->line("  Response: " . json_encode($data));
            }

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->error('❌ Connection Failed!');
            $this->error("  Error: " . $e->getMessage());
            $this->newLine();
            $this->info('💡 Possible issues:');
            $this->line('  - Server firewall blocking outbound HTTPS connections');
            $this->line('  - DNS resolution issues');
            $this->line('  - SSL certificate problems');
            $this->line('  - VFD API is down');
            return 1;
        } catch (\Exception $e) {
            $this->error('❌ Request Failed!');
            $this->error("  Error: " . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('✅ All checks passed!');
        return 0;
    }
}