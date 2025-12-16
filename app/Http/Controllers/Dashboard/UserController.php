<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\User;
use App\Models\Shop;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $usersQuery = User::with(['roles', 'shop.parent'])
            ->filter(request(['search']))
            ->sortable();

        if ($authUser->shop_id) {
            $usersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $usersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');

                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('users.index', [
            'users' => $usersQuery->paginate($row)->appends(request()->query()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = auth()->user();
        $activeShop = ActiveShop::current();
        
        // Get shops that can be assigned (super admin or parent shop users)
        $canSelectShop = !$user->shop_id || ($user->shop && $user->shop->is_parent);
        $availableShops = collect();
        
        if ($canSelectShop) {
            if (!$user->shop_id) {
                // Super admin - can assign to any shop
                $availableShops = Shop::with('parent')->orderBy('name')->get();
            } else {
                // Parent shop user - can assign to their shop and child shops
                $availableShops = Shop::where('parent_shop_id', $user->shop_id)
                    ->orWhere('id', $user->shop_id)
                    ->with('parent')
                    ->orderBy('name')
                    ->get();
            }
        }
        
        return view('users.create', [
            'roles' => Role::all(),
            'activeShop' => $activeShop,
            'canSelectShop' => $canSelectShop,
            'availableShops' => $availableShops,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|max:50',
            'photo' => 'image|file|max:1024',
            'email' => 'required|email|max:50|unique:users,email',
            'username' => 'required|min:4|max:25|alpha_dash:ascii|unique:users,username',
            'password' => 'min:6|required_with:password_confirmation',
            'password_confirmation' => 'min:6|same:password',
        ];

        $validatedData = $request->validate($rules);
        $validatedData['password'] = Hash::make($request->password);

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/profile/';

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        $authUser = auth()->user();
        $canSelectShop = !$authUser->shop_id || ($authUser->shop && $authUser->shop->is_parent);
        
        // Determine shop_id
        if ($canSelectShop && $request->has('shop_id') && $request->shop_id) {
            // Validate that the selected shop is allowed
            $allowedShopIds = ActiveShop::allowedShopIds($authUser);
            
            if (!$allowedShopIds->contains((int) $request->shop_id)) {
                return Redirect::back()
                    ->withErrors(['shop_id' => 'You cannot assign users to this shop.'])
                    ->withInput();
            }
            
            $validatedData['shop_id'] = (int) $request->shop_id;
        } else {
            // Use active shop as fallback
            $activeShop = ActiveShop::current();
            
            if (!$activeShop) {
                return Redirect::back()
                    ->withErrors(['shop_id' => 'Please select a shop for this user.'])
                    ->withInput();
            }
            
            $validatedData['shop_id'] = $activeShop->id;
        }

        $user = User::create($validatedData);

        if($request->role) {
            $user->assignRole($request->role);
        }

        return Redirect::route('users.index')->with('success', 'New User has been created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->ensureShopAccess($user);

        $user->loadMissing('shop.parent');
        $authUser = auth()->user();
        
        // Get shops that can be assigned (super admin or parent shop users)
        $canSelectShop = !$authUser->shop_id || ($authUser->shop && $authUser->shop->is_parent);
        $availableShops = collect();
        
        if ($canSelectShop) {
            if (!$authUser->shop_id) {
                // Super admin - can assign to any shop
                $availableShops = Shop::with('parent')->orderBy('name')->get();
            } else {
                // Parent shop user - can assign to their shop and child shops
                $availableShops = Shop::where('parent_shop_id', $authUser->shop_id)
                    ->orWhere('id', $authUser->shop_id)
                    ->with('parent')
                    ->orderBy('name')
                    ->get();
            }
        }

        return view('users.edit', [
            'userData' => $user,
            'roles' => Role::all(),
            'canSelectShop' => $canSelectShop,
            'availableShops' => $availableShops,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->ensureShopAccess($user);

        $rules = [
            'name' => 'required|max:50',
            'photo' => 'image|file|max:1024',
            'email' => 'required|email|max:50|unique:users,email,'.$user->id,
            'username' => 'required|min:4|max:25|alpha_dash:ascii|unique:users,username,'.$user->id,
        ];

        if($request->password || $request->confirm_password) {
            $rules['password'] = 'min:6|required_with:password_confirmation';
            $rules['password_confirmation'] = 'min:6|same:password';
        }

        $validatedData = $request->validate($rules);
        
        if ($request->password) {
            $validatedData['password'] = Hash::make($request->password);
        }

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/profile/';

            /**
             * Delete photo if exists.
             */
            if($user->photo){
                Storage::delete($path . $user->photo);
            }

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        // Handle shop assignment if user can select shops
        $authUser = auth()->user();
        $canSelectShop = !$authUser->shop_id || ($authUser->shop && $authUser->shop->is_parent);
        
        if ($canSelectShop && $request->has('shop_id')) {
            $shopId = $request->shop_id ? (int) $request->shop_id : null;
            
            if ($shopId) {
                // Validate that the selected shop is allowed
                $allowedShopIds = ActiveShop::allowedShopIds($authUser);
                
                if (!$allowedShopIds->contains($shopId)) {
                    return Redirect::back()
                        ->withErrors(['shop_id' => 'You cannot assign users to this shop.'])
                        ->withInput();
                }
            }
            
            $validatedData['shop_id'] = $shopId;
        }

        $userData = User::findOrFail($user->id);
        $userData->update($validatedData);

        if($request->role) {
            $userData->syncRoles($request->role);
        }

        return Redirect::route('users.index')->with('success', 'User has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->ensureShopAccess($user);

        /**
         * Delete photo if exists.
         */
        if($user->photo){
            Storage::delete('public/profile/' . $user->photo);
        }

        User::destroy($user->id);

        return Redirect::route('users.index')->with('success', 'User has been deleted!');
    }

    private function ensureShopAccess(User $user): void
    {
        $actor = auth()->user();

        if ($actor->id === $user->id) {
            return;
        }

        if (!ActiveShop::canManageUser($actor, $user)) {
            abort(403, 'You are not allowed to manage this user.');
        }
    }
}
