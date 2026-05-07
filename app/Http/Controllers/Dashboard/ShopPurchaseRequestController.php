<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ShopPurchaseRequest;
use App\Services\InterShopTransferService;
use App\Support\ActiveShop;
use App\Support\InterShopTransferStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopPurchaseRequestController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $shopId = auth()->user()->shop_id;
        if (!$shopId) {
            abort(403);
        }

        $query = ShopPurchaseRequest::query()
            ->forChildShop((int) $shopId)
            ->orderByDesc('id');

        $status = $request->query('status', 'pending');
        if ($status === 'pending') {
            $query->where('status', InterShopTransferStatus::PENDING);
        } elseif ($status === 'all') {
            // no filter
        } else {
            $query->where('status', $status);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('shop-purchase-requests.index', [
            'requests' => $requests,
            'statusFilter' => $status,
        ]);
    }

    public function show(ShopPurchaseRequest $shopPurchaseRequest): View
    {
        $authUser = auth()->user();
        $visible = ActiveShop::visibleShopIds($authUser);
        if (!$visible->contains((int) $shopPurchaseRequest->child_shop_id)) {
            abort(403, 'You do not have access to this purchase request.');
        }

        return view('shop-purchase-requests.show', [
            'req' => $shopPurchaseRequest,
        ]);
    }

    public function approve(ShopPurchaseRequest $shopPurchaseRequest, InterShopTransferService $interShopTransferService): RedirectResponse
    {
        $shopId = auth()->user()->shop_id;
        if (!$shopId || (int) $shopId !== (int) $shopPurchaseRequest->child_shop_id) {
            abort(403, 'Only the receiving child shop can approve this request.');
        }

        try {
            $interShopTransferService->approveShopPurchaseRequest(
                (int) $shopPurchaseRequest->id,
                (int) $shopId,
                (int) auth()->id()
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('shop-purchase-requests.show', $shopPurchaseRequest)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('shop-purchase-requests.show', $shopPurchaseRequest)
            ->with('success', 'Transfer approved. The mother shop can complete dispatch when ready.');
    }

    public function cancel(ShopPurchaseRequest $shopPurchaseRequest, InterShopTransferService $interShopTransferService): RedirectResponse
    {
        $shopId = auth()->user()->shop_id;
        if (!$shopId || (int) $shopId !== (int) $shopPurchaseRequest->child_shop_id) {
            abort(403, 'Only the receiving child shop can cancel this request.');
        }

        try {
            $interShopTransferService->cancelShopPurchaseRequestAsChild(
                (int) $shopPurchaseRequest->id,
                (int) $shopId
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('shop-purchase-requests.show', $shopPurchaseRequest)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('shop-purchase-requests.index')
            ->with('success', 'Purchase request cancelled. Reserved stock at the mother shop has been released.');
    }
}
