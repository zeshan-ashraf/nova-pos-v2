<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\ActiveShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopSwitchController extends Controller
{
    /**
     * Switch active shop (session only). Allowed for Super Admin or users whose shop can switch (e.g. mother shop).
     */
    public function switch(Request $request, int $shop): RedirectResponse
    {
        $user = $request->user();

        $allowed = ActiveShop::allowedShopIds($user);
        if (!$allowed->contains($shop)) {
            abort(403, 'Shop not in your allowed list.');
        }

        $shopModel = Shop::find($shop);
        if (!$shopModel) {
            abort(404);
        }

        ActiveShop::set($shop);

        return redirect()->back()->with('success', 'You are now viewing: ' . $shopModel->name);
    }

    /**
     * Reset to user's default shop (session only).
     */
    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->shop_id !== null) {
            ActiveShop::set($user->shop_id);
        } else {
            session()->forget('active_shop_id');
        }

        return redirect()->back()->with('success', 'Returned to your default shop');
    }
}
