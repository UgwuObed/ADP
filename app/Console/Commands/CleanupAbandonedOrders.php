<?php



namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupAbandonedOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:cleanup-abandoned 
                            {--hours=2 : Hours after which to consider order abandoned}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up orders that were created but payment was never completed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $cutoff = Carbon::now()->subHours($hours);
        
        $this->info("Looking for abandoned orders older than {$hours} hours (before {$cutoff->format('Y-m-d H:i:s')})...");
        
        // Find orders that are still pending payment after specified hours
        $abandonedOrders = Order::where('payment_status', 'pending')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->with(['items.product', 'storefront'])
            ->get();

        if ($abandonedOrders->isEmpty()) {
            $this->info('✅ No abandoned orders found.');
            return 0;
        }

        $this->warn("Found {$abandonedOrders->count()} abandoned orders:");
        
        // Display table of abandoned orders
        $tableData = $abandonedOrders->map(function ($order) {
            return [
                'Order #' => $order->order_number,
                'Created' => $order->created_at->format('Y-m-d H:i:s'),
                'Age (hours)' => $order->created_at->diffInHours(now()),
                'Items' => $order->items->count(),
                'Total' => '₦' . number_format($order->total, 2),
                'Store' => $order->storefront->business_name ?? 'N/A'
            ];
        })->toArray();

        $this->table(
            array_keys($tableData[0]),
            $tableData
        );

        if ($dryRun) {
            $this->info('🔍 DRY RUN: These orders would be deleted (use without --dry-run to actually delete)');
            return 0;
        }

        // Confirmation prompt
        if (!$force) {
            if (!$this->confirm('Do you want to delete these abandoned orders?')) {
                $this->info('❌ Operation cancelled.');
                return 0;
            }
        }

        $deletedCount = 0;
        $errorCount = 0;

        foreach ($abandonedOrders as $order) {
            try {
                $orderNumber = $order->order_number;
                $itemsCount = $order->items->count();
                
                // Log the deletion
                Log::info('Deleting abandoned order', [
                    'order_number' => $orderNumber,
                    'order_id' => $order->id,
                    'items_count' => $itemsCount,
                    'total' => $order->total,
                    'created_at' => $order->created_at,
                    'age_hours' => $order->created_at->diffInHours(now())
                ]);
                
                // Delete order items first
                $order->items()->delete();
                
                // Delete the order
                $order->delete();
                
                $this->line("✅ Deleted order #{$orderNumber} ({$itemsCount} items)");
                $deletedCount++;
                
            } catch (\Exception $e) {
                $this->error("❌ Failed to delete order #{$order->order_number}: " . $e->getMessage());
                
                Log::error('Failed to delete abandoned order', [
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $errorCount++;
            }
        }

        // Summary
        $this->newLine();
        $this->info("🧹 Cleanup completed:");
        $this->line("   • {$deletedCount} orders deleted successfully");
        
        if ($errorCount > 0) {
            $this->warn("   • {$errorCount} orders failed to delete (check logs)");
        }
        
        Log::info('Abandoned orders cleanup completed', [
            'deleted_count' => $deletedCount,
            'error_count' => $errorCount,
            'hours_threshold' => $hours
        ]);

        return 0;
    }
}

