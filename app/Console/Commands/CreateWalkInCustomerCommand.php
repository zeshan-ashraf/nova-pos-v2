<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\Customer;
use App\Services\ShopSetupService;
use Illuminate\Console\Command;

class CreateWalkInCustomerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:create-walkin {shop_id : The ID of the shop}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a WalkIn customer for the specified shop';

    /**
     * Execute the console command.
     */
    public function handle(ShopSetupService $shopSetupService)
    {
        $shopId = $this->argument('shop_id');

        // Check if WalkIn customer already exists for this shop
        $existingCustomer = Customer::where('name', 'Walk-In Customer')
            ->where('shop_id', $shopId)
            ->first();

        if ($existingCustomer) {
            $this->error("WalkIn customer already exists for shop ID: {$shopId}");
            return 1;
        }

        // Get shop to pass to service (for shop name in success message)
        $shop = Shop::find($shopId);

        if (!$shop) {
            $this->error("Shop with ID {$shopId} not found.");
            return 1;
        }

        // Create WalkIn customer using service
        $result = $shopSetupService->createWalkInCustomer($shop);

        if ($result['success']) {
            $this->info("WalkIn customer created successfully for Shop name: {$shop->name}");
            return 0;
        } else {
            $this->error($result['error'] ?? 'Failed to create WalkIn customer.');
            return 1;
        }
    }
}


