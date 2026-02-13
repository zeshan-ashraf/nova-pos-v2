<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;
use App\Models\Customer;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ShopSetupService
{
    /**
     * Setup child shop by creating admin user and WalkIn customer
     *
     * @param Shop $shop
     * @return array ['success' => bool, 'user' => User|null, 'customer' => Customer|null, 'messages' => array]
     */
    public function setupChildShop(Shop $shop): array
    {
        $result = [
            'success' => true,
            'user' => null,
            'customer' => null,
            'messages' => [],
            'errors' => []
        ];

        // Only setup child shops
        if ($shop->is_parent) {
            return $result;
        }

        // Create admin user
        $userResult = $this->createAdminUser($shop);
        if ($userResult['success']) {
            $result['user'] = $userResult['user'];
        } else {
            $result['success'] = false;
            $result['errors'][] = $userResult['error'];
        }

        // Create WalkIn customer
        $customerResult = $this->createWalkInCustomer($shop);
        if ($customerResult['success']) {
            $result['customer'] = $customerResult['customer'];
        } else {
            $result['success'] = false;
            $result['errors'][] = $customerResult['error'];
        }

        // Store messages in session for controller to read
        if (!empty($result['errors'])) {
            Session::flash('shop_setup_errors', $result['errors']);
        }
        if ($result['user']) {
            Session::flash('shop_setup_user', $result['user']);
        }

        return $result;
    }

    /**
     * Create admin user for child shop
     *
     * @param Shop $shop
     * @return array
     */
    protected function createAdminUser(Shop $shop): array
    {
        try {
            // Ensure "Child Store Admin" role exists
            $role = Role::firstOrCreate(
                ['name' => 'Child Store Admin']
            );

            // Sanitize shop name for username/email
            $sanitizedShopName = str_replace(' ', '_', strtolower($shop->name));
            
            // Generate unique username and email
            $username = $sanitizedShopName . '_admin';
            $email = $sanitizedShopName . '_admin@system.local';

            // Check if username/email already exists and append shop_id if needed
            $baseUsername = $username;
            $baseEmail = $email;
            $counter = 1;
            while (User::where('username', $username)->exists() || User::where('email', $email)->exists()) {
                $username = $baseUsername . '_' . $shop->id;
                $email = $baseEmail;
                if ($counter > 1) {
                    $username = $baseUsername . '_' . $shop->id . '_' . $counter;
                }
                $counter++;
            }

            // Create admin user (actual_password so admin can view it in user list/edit)
            $plainPassword = 'password';
            $user = User::create([
                'name' => $shop->owner_name,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'actual_password' => $plainPassword,
                'email_verified_at' => now(),
                'shop_id' => $shop->id,
            ]);

            // Assign role
            $user->assignRole($role);

            Log::info("Admin user created for shop {$shop->id}: {$username}");

            return [
                'success' => true,
                'user' => $user
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create admin user for shop {$shop->id}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create admin user: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create WalkIn customer for shop
     *
     * @param Shop $shop
     * @return array
     */
    public function createWalkInCustomer(Shop $shop): array
    {
        try {
            // Generate unique email and phone
            $email = 'walkin@shop' . $shop->id . '.local';
            // Phone format: 999 + zeros + shop_id = 10 digits total
            $shopIdLength = strlen((string)$shop->id);
            $zerosNeeded = 10 - 3 - $shopIdLength; // 3 for '999' prefix
            $zeros = str_repeat('0', max(0, $zerosNeeded));
            $phone = '999' . $zeros . $shop->id;

            // Check if email/phone already exists (shouldn't, but just in case)
            $counter = 1;
            $baseEmail = $email;
            $basePhone = $phone;
            while (Customer::where('email', $email)->exists() || Customer::where('phone', $phone)->exists()) {
                $email = 'walkin_' . $counter . '@shop' . $shop->id . '.local';
                $phone = '99900000' . str_pad($shop->id, 2, '0', STR_PAD_LEFT) . $counter;
                $counter++;
            }

            // Create WalkIn customer
            $customer = Customer::create([
                'name' => 'Walk-In Customer',
                'email' => $email,
                'phone' => $phone,
                'shopname' => 'Walk-In Customer',
                'shop_id' => $shop->id,
                'is_system' => 0,
                'is_walkin' => 1,
                'address' => null,
                'credit_limit' => 0,
                'credit_amount' => 0,
                'credit_days' => 0,
            ]);

            Log::info("WalkIn customer created for shop {$shop->id}: {$email}");

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (\Exception $e) {
            Log::error("Failed to create WalkIn customer for shop {$shop->id}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create WalkIn customer: ' . $e->getMessage()
            ];
        }
    }
}

