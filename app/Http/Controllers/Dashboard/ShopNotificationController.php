<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ShopNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ShopNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shopId = auth()->user()->shop_id;
        if (!$shopId) {
            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }

        $columns = ['id', 'type', 'data', 'is_read', 'created_at'];
        if (Schema::hasColumn('shop_notifications', 'shop_purchase_request_id')) {
            $columns[] = 'shop_purchase_request_id';
        }

        $notifications = ShopNotification::query()
            ->where('shop_id', $shopId)
            ->orderByDesc('id')
            ->limit(50)
            ->get($columns);

        $unreadCount = ShopNotification::query()
            ->where('shop_id', $shopId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $shopId = auth()->user()->shop_id;
        if (!$shopId) {
            abort(403);
        }

        $updated = ShopNotification::query()
            ->where('shop_id', $shopId)
            ->whereKey($id)
            ->update(['is_read' => true]);

        return response()->json(['ok' => (bool) $updated]);
    }

    public function markAllRead(): JsonResponse
    {
        $shopId = auth()->user()->shop_id;
        if (!$shopId) {
            abort(403);
        }

        ShopNotification::query()
            ->where('shop_id', $shopId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }
}
