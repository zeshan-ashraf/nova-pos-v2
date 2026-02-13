<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Collection;

class ActiveShop
{
    protected static ?Shop $cachedShop = null;

    public static function current(): ?Shop
    {
        if (self::$cachedShop) {
            return self::$cachedShop;
        }

        $id = session('active_shop_id');

        if (!$id) {
            return null;
        }

        self::$cachedShop = Shop::with('parent')->find($id);

        return self::$cachedShop;
    }

    public static function id(): ?int
    {
        return self::current()?->id;
    }

    public static function set(?int $shopId): void
    {
        if ($shopId === null) {
            session()->forget('active_shop_id');
            self::$cachedShop = null;

            return;
        }

        session(['active_shop_id' => $shopId]);
        self::$cachedShop = null;
        self::current();
    }

    public static function ensureFor(User $user): void
    {
        $user->loadMissing('shop');

        $currentId = session('active_shop_id');

        // If user has a shop_id, they may have switched to an allowed child shop — only reset when session is missing or invalid
        if ($user->shop_id) {
            $allowedIds = self::allowedShopIds($user);
            if ($currentId && $allowedIds->contains((int) $currentId)) {
                return; // keep current selection (e.g. viewing a child shop)
            }
            self::set($user->shop_id);
            return;
        }

        // Super admin (no shop_id) - can switch between shops
        // If already has an active shop set and it's valid, keep it
        $allowedIds = self::allowedShopIds($user);
        if ($currentId && $allowedIds->contains($currentId)) {
            return;
        }

        // Set first available shop as default for super admin
        $defaultId = $allowedIds->first();
        if ($defaultId) {
            self::set($defaultId);
        }
    }

    public static function allowedShopIds(User $user): Collection
    {
        if (!$user->shop_id) {
            return Shop::pluck('id');
        }

        $shop = $user->shop;

        if (!$shop) {
            return collect();
        }

        if ($shop->is_parent) {
            return Shop::where('parent_shop_id', $shop->id)
                ->orWhere('id', $shop->id)
                ->pluck('id')
                ->unique()
                ->values();
        }

        return collect([$shop->id]);
    }

    public static function switchable(User $user): Collection
    {
        $ids = self::allowedShopIds($user);

        if ($ids->isEmpty()) {
            return collect();
        }

        return Shop::with('parent')
            ->whereIn('id', $ids->all())
            ->orderByRaw('CASE WHEN parent_shop_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();
    }

    /**
     * Super admin = no shop_id; only they get the shop switcher dropdown.
     */
    public static function isSuperAdmin(User $user): bool
    {
        return $user->shop_id === null;
    }

    public static function canSwitch(User $user): bool
    {
        if (!$user->shop_id) {
            return Shop::exists();
        }

        if (!$user->shop?->is_parent) {
            return false;
        }

        return Shop::where('parent_shop_id', $user->shop_id)->exists();
    }

    /**
     * Shop IDs used for data filtering. When user has switched to a specific shop (session set),
     * return only that shop so lists reflect the selected shop. Otherwise return all allowed shops.
     */
    public static function visibleShopIds(User $user): Collection
    {
        $allowedIds = self::allowedShopIds($user);
        $activeId = self::id();
        if ($activeId !== null && $allowedIds->contains($activeId)) {
            return collect([$activeId]);
        }
        return $allowedIds;
    }

    public static function canManageUser(User $actor, User $target): bool
    {
        if (!$actor->shop_id) {
            return true;
        }

        $allowed = self::allowedShopIds($actor);

        if ($target->shop_id === null) {
            return false;
        }

        return $allowed->contains($target->shop_id);
    }
}

