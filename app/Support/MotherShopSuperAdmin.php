<?php

namespace App\Support;

use App\Models\User;

/**
 * Access to the multi-shop "Super Dashboard" is limited to accounts tied to a
 * parent (mother) shop, plus either the SuperAdmin role or an allowlisted email.
 */
class MotherShopSuperAdmin
{
    public static function allows(?User $user): bool
    {
        if ($user === null || ! $user->shop_id) {
            return false;
        }

        $user->loadMissing('shop');
        $shop = $user->shop;

        if (! $shop || ! $shop->is_parent) {
            return false;
        }

        $emails = self::allowedEmails();
        if ($emails !== [] && in_array(strtolower((string) $user->email), $emails, true)) {
            return true;
        }

        return $user->hasRole('SuperAdmin');
    }

    /**
     * @return list<string> lowercased
     */
    public static function allowedEmails(): array
    {
        $raw = config('app.mother_shop_super_admin_emails', []);

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($e): string => strtolower(trim((string) $e)),
            $raw
        ))));
    }
}
