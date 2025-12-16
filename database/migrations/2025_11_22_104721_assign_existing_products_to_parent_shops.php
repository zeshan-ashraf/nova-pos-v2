<?php

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the first parent shop (mother shop)
        $parentShop = Shop::where('is_parent', true)->orderBy('id')->first();
        
        if ($parentShop) {
            // Assign all existing products (where shop_id is null) to the parent shop
            Product::whereNull('shop_id')->update(['shop_id' => $parentShop->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally, you can set all products back to null
        // Product::whereNotNull('shop_id')->update(['shop_id' => null]);
    }
};
