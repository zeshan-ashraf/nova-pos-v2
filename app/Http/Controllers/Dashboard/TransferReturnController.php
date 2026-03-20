<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Support\ActiveShop;
use App\Services\TransferReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class TransferReturnController extends Controller
{
    public function __construct(
        private TransferReturnService $transferReturnService
    ) {
        $this->middleware('permission:transfer_returns.view')->only(['pending', 'show']);
        $this->middleware('permission:transfer_returns.approve')->only(['approve']);
        $this->middleware('permission:transfer_returns.reject')->only(['reject']);
    }

    /**
     * List pending transfer return requests for the mother shop.
     */
    public function pending()
    {
        $authUser = auth()->user();
        $motherShopId = $authUser->shop_id;

        if (!$motherShopId) {
            abort(403, 'Only mother shop users can review transfer returns.');
        }

        // Join purchase_returns -> purchases -> orders to scope by mother shop sale.
        $returns = PurchaseReturn::query()
            ->with(['purchase', 'purchase.order', 'shop'])
            ->where('status', 'pending')
            ->whereHas('purchase', function ($q) use ($motherShopId) {
                $q->whereNotNull('source_sale_id')
                    ->whereHas('order', function ($q2) use ($motherShopId) {
                        $q2->where('shop_id', $motherShopId);
                    });
            })
            ->orderByDesc('return_date')
            ->paginate(50);

        return view('transfer-returns.pending', [
            'returns' => $returns,
        ]);
    }

    /**
     * Show a single transfer return request for review.
     */
    public function show(int $id)
    {
        $authUser = auth()->user();
        $motherShopId = $authUser->shop_id;

        $return = PurchaseReturn::with([
            'purchase',
            'purchase.order',
            'details.product.parent',
            'shop',
        ])->findOrFail($id);

        // Ensure this request belongs to a sale from the current mother shop.
        if (!$return->purchase || !$return->purchase->order || $return->purchase->order->shop_id !== $motherShopId) {
            abort(403, 'You do not have access to this transfer return.');
        }

        return view('transfer-returns.show', [
            'purchaseReturn' => $return,
        ]);
    }

    /**
     * Approve a transfer return and execute all stock + accounting reversals.
     */
    public function approve(int $id)
    {
        $return = PurchaseReturn::with(['purchase', 'purchase.order', 'details.product', 'shop'])
            ->findOrFail($id);

        try {
            DB::transaction(function () use ($return) {
                $this->transferReturnService->approveReturn($return);
            });

            return Redirect::route('transfer-returns.pending')
                ->with('success', 'Transfer return approved successfully. Inventory and accounting entries have been updated.');
        } catch (\Throwable $e) {
            \Log::error('Transfer return approval failed', [
                'purchase_return_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Redirect::back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject a transfer return request (no stock or accounting changes).
     */
    public function reject(int $id)
    {
        $return = PurchaseReturn::findOrFail($id);

        try {
            DB::transaction(function () use ($return) {
                $this->transferReturnService->rejectReturn($return);
            });

            return Redirect::route('transfer-returns.pending')
                ->with('success', 'Transfer return rejected.');
        } catch (\Throwable $e) {
            return Redirect::back()->with('error', $e->getMessage());
        }
    }
}

