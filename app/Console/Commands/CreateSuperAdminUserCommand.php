<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateSuperAdminUserCommand extends Command
{
    protected $signature = 'user:create-super-admin
                            {--username= : Login username (unique)}
                            {--name= : Display name}
                            {--email= : Email address (unique)}
                            {--password= : Password (min 6 characters; random if omitted)}
                            {--shop-id= : Shop ID to assign. Omit for platform super admin (shop_id null, all shops)}
                            {--role=SuperAdmin : Role to assign (default SuperAdmin)}';

    protected $description = 'Create a SuperAdmin user. Omit --shop-id for platform-level access to all shops.';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Username');
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (leave empty to generate)');
        $roleName = (string) ($this->option('role') ?: 'SuperAdmin');

        if ($this->option('shop-id') !== null && $this->option('shop-id') !== '') {
            $shopId = (int) $this->option('shop-id');
        } elseif ($this->input->isInteractive()) {
            if ($this->confirm('Create platform super admin (no shop — access ALL shops)?', true)) {
                $shopId = null;
            } else {
                $shopIdInput = $this->ask('Shop ID');
                $shopId = ($shopIdInput === null || $shopIdInput === '') ? null : (int) $shopIdInput;
            }
        } else {
            $shopId = null;
        }

        if ($password === null || $password === '') {
            $password = bin2hex(random_bytes(4));
            $this->warn("Generated password: {$password}");
        }

        $validator = Validator::make([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'shop_id' => $shopId,
        ], [
            'username' => 'required|min:4|max:25|alpha_dash|unique:users,username',
            'name' => 'required|max:50',
            'email' => 'required|email|max:50|unique:users,email',
            'password' => 'required|min:6',
            'shop_id' => 'nullable|integer|exists:shops,id',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $shop = $shopId ? Shop::find($shopId) : null;

        if ($shopId && !$shop) {
            $this->error("Shop with ID {$shopId} not found.");

            return self::FAILURE;
        }

        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
        if (!$role) {
            $this->error("Role \"{$roleName}\" not found. Create it first or pass --role=.");

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'actual_password' => $password,
            'shop_id' => $shopId,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        $this->info('Super admin user created successfully.');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', (string) $user->id],
                ['Username', $user->username],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Role', $roleName],
                ['Shop', $shop ? "{$shop->name} (ID {$shop->id})" : 'None — platform super admin (all shops)'],
                ['Password', $password],
            ]
        );

        if ($shopId === null) {
            $this->line('This user can switch between all shops and will see every shop on Users → Create.');
        } elseif ($shop?->is_parent) {
            $this->line('This user can manage their mother shop and its child shops only.');
        } else {
            $this->line('This user is scoped to the selected shop only.');
        }

        return self::SUCCESS;
    }
}
