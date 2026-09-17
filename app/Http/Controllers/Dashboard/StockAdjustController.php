<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Stock\StockService;
use App\Support\ActiveShop;
use App\Support\ProductUnitValidator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Handles stock adjustment requests from the product list modal.
 * Only authenticated users with product (inventory) permission; no direct stock editing.
 */
class StockAdjustController extends Controller
{
    public function __construct(
        private StockService $stockService,
        private ProductUnitValidator $units
    ) {}

    /**
     * POST /stock/adjust — validate, ensure shop access, perform adjustment, return JSON.
     */
    public function adjust(Request $request): JsonResponse
    {
        // Server-side validation (mirrors frontend rules; never trust client)
        $validated = $request->validate([
            'product_id'       => 'required|integer|exists:products,id',
            'adjustment_type' => 'required|string|in:damaged,expired,lost_theft,stock_found,manual_add,manual_remove',
            'qty'             => 'required|numeric|gt:0',
            'date'            => 'required|date',
            'time'            => 'required|date_format:H:i',
            'reason'          => 'required|string|min:1|max:2000',
        ], [
            'product_id.required'   => 'Product is required.',
            'product_id.exists'     => 'Product not found.',
            'adjustment_type.in'    => 'Invalid adjustment type.',
            'qty.min'               => 'Quantity must be greater than 0.',
            'time.required'         => 'Time is required.',
            'time.date_format'       => 'Time must be in HH:MM format.',
            'reason.required'       => 'Reason / notes are required.',
        ]);

        // Combine date and time to datetime (Y-m-d H:i:s) for storage
        $adjustmentDatetime = Carbon::parse($validated['date'] . ' ' . $validated['time'])->format('Y-m-d H:i:s');

        $product = Product::findOrFail($validated['product_id']);
        $this->ensureShopAccess($product);

        // Out-types: qty must not exceed current stock (validated again in StockService)
        $outTypes = ['damaged', 'expired', 'lost_theft', 'manual_remove'];
        if (in_array($validated['adjustment_type'], $outTypes, true)) {
            $current = $this->units->formatQuantity($product->product_store ?? '0');
            if ($this->units->compare($validated['qty'], $current) > 0) {
                return response()->json([
                    'message' => "Quantity cannot exceed current stock ({$current}).",
                    'errors'  => ['qty' => ["Quantity cannot exceed current stock ({$current})."]],
                ], 422);
            }
        }

        $outTypes = ['damaged', 'expired', 'lost_theft', 'manual_remove'];
        $direction = in_array($validated['adjustment_type'], $outTypes, true) ? 'out' : 'in';

        // Map UI adjustment type to stock_log source_type for loss/expired/theft (triggers system expense)
        $lossSourceType = match ($validated['adjustment_type']) {
            'damaged' => 'loss',
            'expired' => 'expired',
            'lost_theft' => 'theft',
            default => null,
        };

        try {
            $this->stockService->adjustStock(
                $product,
                $validated['qty'],
                $direction,
                $validated['reason'],
                $adjustmentDatetime,
                $lossSourceType
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => ['qty' => [$e->getMessage()]],
            ], 422);
        }

        $newStock = $product->fresh()->product_store ?? 0;
        return response()->json([
            'message'   => 'Stock adjusted successfully.',
            'new_stock' => $newStock,
        ], 200);
    }

    /**
     * Ensure the current user has access to the product's shop (no direct stock editing by unauthorized shops).
     */
    protected function ensureShopAccess(Product $product): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            if ($product->shop_id && !$visibleShopIds->contains($product->shop_id)) {
                abort(403, 'You do not have access to this product.');
            }
        }
    }
}
