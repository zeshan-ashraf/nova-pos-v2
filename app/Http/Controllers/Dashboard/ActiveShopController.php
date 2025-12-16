<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\ActiveShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveShopController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
        ]);

        $user = $request->user();

        $allowed = ActiveShop::allowedShopIds($user);

        if (!$allowed->contains((int) $validated['shop_id'])) {
            abort(403);
        }

        ActiveShop::set((int) $validated['shop_id']);

        return back()->with('success', 'Active shop updated.');
    }
}

