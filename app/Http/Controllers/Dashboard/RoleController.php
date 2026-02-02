<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    // Permission Controller
    public function permissionIndex()
    {
        $permissions = QueryBuilder::for(Permission::class)->paginate();

        return view('roles.permission-index', [
            'permissions' => $permissions,
        ]);
    }

    public function permissionCreate()
    {
        return view('roles.permission-create');
    }

    public function permissionStore(Request $request)
    {
        $rules = [
            'name' => 'required|string',
            'group_name' => 'required|string',
        ];

        $validatedData = $request->validate($rules);

        Permission::create($validatedData);

        return Redirect::route('permission.index')->with('success', 'Permission has been created!');
    }

    public function permissionEdit(Int $id)
    {
        $permission = Permission::findOrFail($id);

        return view('roles.permission-edit', [
            'permission' => $permission,
        ]);
    }

    public function permissionUpdate(Request $request, Int $id)
    {
        $rules = [
            'name' => 'required|string',
            'group_name' => 'required|string',
        ];

        $validatedData = $request->validate($rules);

        Permission::findOrFail($id)->update($validatedData);

        return Redirect::route('permission.index')->with('success', 'Permission has been updated!');
    }

    public function permissionDestroy(Int $id)
    {
        Permission::destroy($id);

        return Redirect::route('permission.index')->with('success', 'Permission has been deleted!');
    }

    // Role Controller
    public function roleIndex()
    {
        $roles = QueryBuilder::for(Role::class)->paginate();

        return view('roles.role-index', [
            'roles' => $roles,
        ]);
    }

    public function roleCreate()
    {
        return view('roles.role-create');
    }

    public function roleStore(Request $request)
    {
        $rules = [
            'name' => 'required|string',
        ];

        $validatedData = $request->validate($rules);

        Role::create($validatedData);

        return Redirect::route('role.index')->with('success', 'Role has been created!');
    }

    public function roleEdit(Int $id)
    {
        $role = Role::findById($id);

        return view('roles.role-edit', [
            'role' => $role,
        ]);
    }

    public function roleUpdate(Request $request, Int $id)
    {
        $rules = [
            'name' => 'required|string',
        ];

        $validatedData = $request->validate($rules);

        Role::findOrFail($id)->update($validatedData);

        return Redirect::route('role.index')->with('success', 'Role has been updated!');
    }

    public function roleDestroy(Int $id)
    {
        Role::destroy($id);

        return Redirect::route('role.index')->with('success', 'Role has been deleted!');
    }

    public function rolePermissionIndex()
    {
        $roles = QueryBuilder::for(Role::class)->paginate();

        return view('roles.role-permission-index', [
            'roles' => $roles,
        ]);
    }


    // Role has Permissions
    public function rolePermissionCreate()
    {
        $roles = Role::all();
        $permissions = Permission::all();
        $permission_groups = User::getPermissionGroups();

        return view('roles.role-permission-create', [
            'roles' => $roles,
            'permissions' => $permissions,
            'permission_groups' => $permission_groups
        ]);
    }

    public function rolePermissionStore(Request $request)
    {
        $data = [];

        $permissions = $request->permission_id;

        foreach ($permissions as $permission) {
            $data['role_id'] = $request->role_id;
            $data['permission_id'] = $permission;

            DB::table('role_has_permissions')->insert($data);
        }

        return Redirect::route('rolePermission.index')->with('success', 'Role Permission has been created!');
    }

    public function rolePermissionEdit(Int $id)
    {
        $role = Role::findOrFail($id);
        $permissions = Permission::all();
        $permission_groups = User::getPermissionGroups();

        return view('roles.role-permission-edit', [
            'role' => $role,
            'permissions' => $permissions,
            'permission_groups' => $permission_groups
        ]);
    }

    public function rolePermissionUpdate(Request $request, Int $id)
    {
        try {
            $role = Role::findOrFail($id);
            
            // Get permissions array, default to empty array if not provided (when no checkboxes are checked)
            $permissionIds = $request->input('permission_id', []);
            
            // Ensure it's an array (in case it's null or single value)
            if (!is_array($permissionIds)) {
                $permissionIds = $permissionIds ? [$permissionIds] : [];
            }
            
            // Convert to integers to ensure proper type and filter out empty values
            $permissionIds = array_map('intval', array_filter($permissionIds));
            
            // Log for debugging
            Log::info('Updating role permissions', [
                'role_id' => $id,
                'role_name' => $role->name,
                'permission_ids' => $permissionIds,
                'permission_count' => count($permissionIds),
                'raw_input' => $request->all()
            ]);
            
            // Use DB transaction to ensure data consistency
            DB::beginTransaction();
            
            try {
                // Always sync permissions, even if empty array (this will remove all permissions if none are selected)
                // syncPermissions accepts IDs directly
                $role->syncPermissions($permissionIds);
                
                // Refresh the role to get updated permissions
                $role->refresh();
                
                // Clear global permission cache using Spatie's method
                $permissionRegistrar = app()[\Spatie\Permission\PermissionRegistrar::class];
                $permissionRegistrar->forgetCachedPermissions();
                
                // Clear permission cache for all users with this role
                $usersWithRole = User::role($role->name)->get();
                foreach ($usersWithRole as $user) {
                    // Clear user-specific permission cache
                    $cacheKey = "spatie.permission.cache.user.{$user->id}";
                    Cache::forget($cacheKey);
                    
                    // Also try alternative cache key formats
                    Cache::forget("spatie.permission.cache.{$user->id}");
                    Cache::forget("spatie.permission.cache.user_{$user->id}");
                    
                    // Reload user's permissions to refresh cache
                    $user->load('roles', 'permissions');
                    // Force refresh by getting permissions
                    $user->getAllPermissions();
                }
                
                // Clear all cache entries that might contain permission data
                // This is a more aggressive approach to ensure everything is cleared
                if (Cache::getStore() instanceof \Illuminate\Cache\TaggedCache) {
                    try {
                        Cache::tags(['spatie.permission.cache'])->flush();
                    } catch (\Exception $e) {
                        // Tags might not be supported, continue
                    }
                }
                
                // Clear the main permission cache key
                $cacheStore = Cache::getStore();
                $cacheKey = config('permission.cache.key', 'spatie.permission.cache');
                Cache::forget($cacheKey);
                
                DB::commit();
                
                // Verify the sync worked
                $syncedPermissions = $role->permissions->pluck('id')->toArray();
                Log::info('Role permissions after sync', [
                    'role_id' => $id,
                    'synced_permission_ids' => $syncedPermissions,
                    'synced_count' => count($syncedPermissions)
                ]);

                return Redirect::route('rolePermission.index')
                    ->with('success', 'Role Permission has been updated! Please refresh your browser (F5 or Ctrl+R) to see the changes in the sidebar.');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Error updating role permissions', [
                'role_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Redirect::back()->with('error', 'Failed to update role permissions: ' . $e->getMessage())->withInput();
        }
    }

    public function rolePermissionDestroy(Int $id)
    {
        $role = Role::findOrFail($id);

        if(!is_null($role)) {
            $role->delete();
        }

        return Redirect::route('rolePermission.index')->with('success', 'Role Permission has been deleted!');
    }
}
