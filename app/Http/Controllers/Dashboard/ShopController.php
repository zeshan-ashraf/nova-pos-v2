<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;

class ShopController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:shop.menu')->only(['index', 'show']);
        $this->middleware('permission:shop.create')->only(['create', 'store']);
        $this->middleware('permission:shop.update')->only(['edit', 'update']);
        $this->middleware('permission:shop.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $row = (int) request('row', 10);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        return view('shops.index', [
            'shops' => Shop::with('parent')
                ->filter([
                    'search' => request('search'),
                    'status' => request('status'),
                ])
                ->sortable()
                ->paginate($row)
                ->appends(request()->query()),
            'statusFilter' => request('status'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('shops.create', [
            'rootShops' => Shop::where('is_parent', true)->orderBy('name')->get(),
            'banks' => Bank::orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:100|unique:shops,name',
            'logo' => 'nullable|image|file|max:2048',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:25|unique:shops,phone',
            'owner_name' => 'required|string|max:100',
            'is_parent' => 'required|boolean',
            'parent_shop_id' => 'nullable|exists:shops,id|required_unless:is_parent,1',
            'status' => 'required|boolean',
            'bank_ids' => 'array',
            'bank_ids.*' => 'exists:banks,id',
        ];

        $validatedData = $request->validate($rules);

        $validatedData['is_parent'] = $request->boolean('is_parent');
        $validatedData['status'] = $request->boolean('status');
        $validatedData['parent_shop_id'] = $validatedData['is_parent'] ? null : $request->input('parent_shop_id');
        $bankIds = $request->input('bank_ids', []);

        if ($validatedData['is_parent'] === false && $validatedData['parent_shop_id'] === null) {
            return Redirect::back()
                ->withErrors(['parent_shop_id' => 'Please select a parent shop.'])
                ->withInput();
        }

        if ($file = $request->file('logo')) {
            $fileName = hexdec(uniqid()) . '.' . $file->getClientOriginalExtension();
            $path = 'public/shops/';

            $file->storeAs($path, $fileName);
            $validatedData['logo'] = $fileName;
        }

        $shop = Shop::create($validatedData);
        $shop->banks()->sync($bankIds);

        return Redirect::route('shops.index')->with('success', 'Shop has been created!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Shop $shop)
    {
        return view('shops.edit', [
            'shop' => $shop->load('banks'),
            'rootShops' => Shop::where('is_parent', true)
                ->where('id', '!=', $shop->id)
                ->orderBy('name')
                ->get(),
            'banks' => Bank::orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shop $shop)
    {
        $rules = [
            'name' => 'required|string|max:100|unique:shops,name,' . $shop->id,
            'logo' => 'nullable|image|file|max:2048',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:25|unique:shops,phone,' . $shop->id,
            'owner_name' => 'required|string|max:100',
            'is_parent' => 'required|boolean',
            'parent_shop_id' => 'nullable|exists:shops,id|required_unless:is_parent,1',
            'status' => 'required|boolean',
            'bank_ids' => 'array',
            'bank_ids.*' => 'exists:banks,id',
        ];

        $validatedData = $request->validate($rules);

        $validatedData['is_parent'] = $request->boolean('is_parent');
        $validatedData['status'] = $request->boolean('status');
        $parentShopId = $request->input('parent_shop_id');
        $bankIds = $request->input('bank_ids', []);

        if ($validatedData['is_parent']) {
            $validatedData['parent_shop_id'] = null;
        } else {
            if ($parentShopId === null) {
                return Redirect::back()
                    ->withErrors(['parent_shop_id' => 'Please select a parent shop.'])
                    ->withInput();
            }

            if ((int) $parentShopId === (int) $shop->id) {
                return Redirect::back()
                    ->withErrors(['parent_shop_id' => 'A shop cannot be its own parent.'])
                    ->withInput();
            }

            $validatedData['parent_shop_id'] = $parentShopId;
        }

        if ($file = $request->file('logo')) {
            $fileName = hexdec(uniqid()) . '.' . $file->getClientOriginalExtension();
            $path = 'public/shops/';

            if ($shop->logo) {
                Storage::delete($path . $shop->logo);
            }

            $file->storeAs($path, $fileName);
            $validatedData['logo'] = $fileName;
        }

        $shop->update($validatedData);
        $shop->banks()->sync($bankIds);

        return Redirect::route('shops.index')->with('success', 'Shop has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shop $shop)
    {
        if ($shop->children()->exists()) {
            return Redirect::route('shops.index')->with('error', 'Shop cannot be deleted while it still has child shops.');
        }

        if ($shop->logo) {
            Storage::delete('public/shops/' . $shop->logo);
        }

        $shop->delete();

        return Redirect::route('shops.index')->with('success', 'Shop has been deleted!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Shop $shop)
    {
        return view('shops.show', [
            'shop' => $shop->load(['parent', 'banks', 'children']),
        ]);
    }
}

