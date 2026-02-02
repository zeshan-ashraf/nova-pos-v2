<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Employee;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $employeesQuery = Employee::with('shop.parent')
            ->filter(request(['search']))
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            // User belongs to a shop - check if parent shop
            $userShop = $authUser->shop;
            if ($userShop && $userShop->is_parent) {
                // Parent shop user - can see all employees from parent and child shops
                $employeesQuery->whereIn('shop_id', $visibleShopIds);
            } else {
                // Child shop user - only see their shop's employees
                $employeesQuery->where('shop_id', $authUser->shop_id);
            }
        } else {
            // Super admin - can see all employees
            // No filtering needed
        }

        return view('employees.index', [
            'employees' => $employeesQuery->paginate($row)->appends(request()->query()),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('employees.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'photo' => 'image|file|max:1024',
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:50|unique:employees,email',
            'phone' => 'required|string|max:15|unique:employees,phone',
            'experience' => 'max:6|nullable',
            'salary' => 'required|numeric',
            'vacation' => 'max:50|nullable',
            'city' => 'required|max:50',
            'address' => 'required|max:100',
        ];

        $validatedData = $request->validate($rules);
        
        // Ensure user has a shop_id before creating employee
        $authUser = auth()->user();
        if (!$authUser->shop_id) {
            return Redirect::back()
                ->withErrors(['shop_id' => 'You must belong to a shop to create employees.'])
                ->withInput();
        }

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/employees/';

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        // Set shop_id from logged-in user
        $authUser = auth()->user();
        $validatedData['shop_id'] = $authUser->shop_id;

        Employee::create($validatedData);

        return Redirect::route('employees.index')->with('success', 'Employee has been created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        $this->ensureShopAccess($employee);
        return view('employees.show', [
            'employee' => $employee,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        $this->ensureShopAccess($employee);
        return view('employees.edit', [
            'employee' => $employee,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $this->ensureShopAccess($employee);
        
        $rules = [
            'photo' => 'image|file|max:1024',
            'name' => 'required|string|max:50',
            'email' => 'required|email|max:50|unique:employees,email,'.$employee->id,
            'phone' => 'required|string|max:20|unique:employees,phone,'.$employee->id,
            'experience' => 'string|max:6|nullable',
            'salary' => 'numeric',
            'vacation' => 'max:50|nullable',
            'city' => 'max:50',
            'address' => 'required|max:100',
        ];

        $validatedData = $request->validate($rules);
        
        // Ensure shop_id remains unchanged (from logged-in user's shop)
        $authUser = auth()->user();
        $validatedData['shop_id'] = $authUser->shop_id;

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('photo')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/employees/';

            /**
             * Delete photo if exists.
             */
            if($employee->photo){
                Storage::delete($path . $employee->photo);
            }

            $file->storeAs($path, $fileName);
            $validatedData['photo'] = $fileName;
        }

        Employee::where('id', $employee->id)->update($validatedData);

        return Redirect::route('employees.index')->with('success', 'Employee has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $this->ensureShopAccess($employee);
        
        /**
         * Delete photo if exists.
         */
        if($employee->photo){
            Storage::delete('public/employees/' . $employee->photo);
        }

        Employee::destroy($employee->id);

        return Redirect::route('employees.index')->with('success', 'Employee has been deleted!');
    }

    /**
     * Ensure the current user has access to the employee based on shop.
     */
    protected function ensureShopAccess(Employee $employee): void
    {
        $authUser = auth()->user();

        // If user is not authenticated, deny access
        if (!$authUser) {
            abort(403, 'You must be authenticated to access this employee.');
        }

        // Super admin can access all employees
        if (!$authUser->shop_id) {
            return;
        }

        // Check if user's shop is parent shop
        $userShop = $authUser->shop;
        if ($userShop && $userShop->is_parent) {
            // Parent shop user - can access employees from parent and child shops
            $visibleShopIds = ActiveShop::visibleShopIds($authUser);
            if ($employee->shop_id && $visibleShopIds->contains($employee->shop_id)) {
                return;
            }
        } else {
            // Child shop user - can only access employees from their shop
            if ($employee->shop_id === $authUser->shop_id) {
                return;
            }
        }

        // Access denied
        abort(403, 'You do not have access to this employee.');
    }
}
