<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccountTransaction;
use App\Models\Bank;
use App\Models\Shop;
use App\Services\ShopBankLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $row = (int) request('row', 50);

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
        // Default banks: Meezan (9), Faysal (10), UBL (3), HBL (2), Bank AL Habib (8)
        $defaultBankIds = [2, 3, 8, 9, 10];

        return view('shops.create', [
            'rootShops' => Shop::where('is_parent', true)->orderBy('name')->get(),
            'banks' => Bank::orderBy('name')->get(),
            'defaultBankIds' => $defaultBankIds,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * Creates shop, attaches banks, and creates ledger opening entries for each bank with opening_balance > 0.
     * Balance is never stored; zero amount => no ledger entry.
     */
    public function store(Request $request)
    {
        $bankIds = $request->input('bank_ids', []);
        $bankIds = is_array($bankIds) ? array_filter($bankIds) : [];

        $rules = [
            'name' => 'required|string|max:100|unique:shops,name',
            'logo' => 'nullable|image|file|max:2048',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:25|unique:shops,phone',
            'owner_name' => 'required|string|max:100',
            'is_parent' => 'required|boolean',
            'parent_shop_id' => 'nullable|exists:shops,id|required_unless:is_parent,1',
            'status' => 'required|boolean',
            'invoice_policy' => 'nullable|string',
            'bank_ids' => 'array',
            'bank_ids.*' => 'exists:banks,id',
        ];

        // Require opening_balance for each selected bank; must be >= 0
        foreach ($bankIds as $bid) {
            $rules["opening_balance.{$bid}"] = 'required|numeric|min:0';
        }

        $validatedData = $request->validate($rules, [
            'opening_balance.*.min' => 'Opening balance cannot be negative.',
            'opening_balance.*.required' => 'Opening balance is required for each selected bank.',
        ]);

        $validatedData['is_parent'] = $request->boolean('is_parent');
        $validatedData['status'] = $request->boolean('status');
        $validatedData['parent_shop_id'] = $validatedData['is_parent'] ? null : $request->input('parent_shop_id');

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

        $openingBalances = $request->input('opening_balance', []);
        $ledgerService = app(ShopBankLedgerService::class);
        $shop = null;

        DB::transaction(function () use ($validatedData, $bankIds, $openingBalances, $ledgerService, &$shop) {
            $shop = Shop::create($validatedData);
            $shop->banks()->sync($bankIds);

            // Create one ledger opening entry per attached bank with opening_balance > 0 (balance never stored)
            $pivots = DB::table('bank_shop')
                ->where('shop_id', $shop->id)
                ->whereIn('bank_id', $bankIds)
                ->get();

            foreach ($pivots as $pivot) {
                $amount = isset($openingBalances[$pivot->bank_id]) ? (float) $openingBalances[$pivot->bank_id] : 0;
                $ledgerService->createBankOpeningEntry(
                    (int) $shop->id,
                    (int) $pivot->id,
                    $amount,
                    $shop->created_at
                );
            }
        });
        $message = 'Shop has been created!';
        $errors = session('shop_setup_errors', []);
        $setupUser = session('shop_setup_user');

        if (!empty($errors)) {
            $errorMessage = 'Shop created but ' . implode('. ', $errors);
            return Redirect::route('shops.index')
                ->with('success', $message)
                ->with('error', $errorMessage);
        }
        if ($shop && $setupUser && !$shop->is_parent) {
            $message .= "<br><br>Admin User Credentials:<br>";
            $message .= "Username: " . e($setupUser->username) . "<br>";
            $message .= "Password: password";
        }

        return Redirect::route('shops.index')->with('success', $message);
    }

    /**
     * Show the form for editing the specified resource.
     * Pass existing banks with derived opening balance (from ledger) for display.
     */
    public function edit(Shop $shop)
    {
        $shop->load('banks');
        $ledgerService = app(ShopBankLedgerService::class);
        $banksWithOpening = [];
        foreach ($shop->banks as $bank) {
            $pivotId = $bank->pivot->id ?? null;
            if ($pivotId === null) {
                continue;
            }
            $banksWithOpening[] = [
                'bank_id' => $bank->id,
                'bank_name' => $bank->name,
                'pivot_id' => $pivotId,
                'opening_balance' => $ledgerService->getBankOpeningBalance($shop->id, $pivotId),
            ];
        }

        return view('shops.edit', [
            'shop' => $shop,
            'rootShops' => Shop::where('is_parent', true)
                ->where('id', '!=', $shop->id)
                ->orderBy('name')
                ->get(),
            'banks' => Bank::orderBy('name')->get(),
            'banksWithOpening' => $banksWithOpening,
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Handles bank add/remove/change with ledger-safe adjustments (never delete ledger rows).
     */
    public function update(Request $request, Shop $shop)
    {
        $newBankIds = $request->input('bank_ids', []);
        $newBankIds = is_array($newBankIds) ? array_values(array_filter($newBankIds)) : [];

        $rules = [
            'name' => 'required|string|max:100|unique:shops,name,' . $shop->id,
            'logo' => 'nullable|image|file|max:2048',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:25|unique:shops,phone,' . $shop->id,
            'owner_name' => 'required|string|max:100',
            'is_parent' => 'required|boolean',
            'parent_shop_id' => 'nullable|exists:shops,id|required_unless:is_parent,1',
            'status' => 'required|boolean',
            'invoice_policy' => 'nullable|string',
            'bank_ids' => 'array',
            'bank_ids.*' => 'exists:banks,id',
        ];
        foreach ($newBankIds as $bid) {
            $rules["opening_balance.{$bid}"] = 'required|numeric|min:0';
        }

        $validatedData = $request->validate($rules, [
            'opening_balance.*.min' => 'Opening balance cannot be negative.',
            'opening_balance.*.required' => 'Opening balance is required for each selected bank.',
        ]);

        $validatedData['is_parent'] = $request->boolean('is_parent');
        $validatedData['status'] = $request->boolean('status');
        $parentShopId = $request->input('parent_shop_id');

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

        if ($request->boolean('remove_logo') && $shop->logo) {
            Storage::delete('public/shops/' . $shop->logo);
            $validatedData['logo'] = null;
        } elseif ($file = $request->file('logo')) {
            $fileName = hexdec(uniqid()) . '.' . $file->getClientOriginalExtension();
            $path = 'public/shops/';
            if ($shop->logo) {
                Storage::delete($path . $shop->logo);
            }
            $file->storeAs($path, $fileName);
            $validatedData['logo'] = $fileName;
        }

        $shop->update($validatedData);

        $ledgerService = app(ShopBankLedgerService::class);
        $openingBalances = $request->input('opening_balance', []);
        $shop->load('banks');
        $oldBankIds = $shop->banks->pluck('id')->all();

        $removedBankIds = array_diff($oldBankIds, $newBankIds);
        $addedBankIds = array_diff($newBankIds, $oldBankIds);
        $keptBankIds = array_intersect($oldBankIds, $newBankIds);

        DB::transaction(function () use ($shop, $removedBankIds, $addedBankIds, $keptBankIds, $newBankIds, $openingBalances, $ledgerService) {
            // CASE 1: Bank removed — derive current balance, zero with adjustment if needed, then detach
            $shop->load('banks');
            foreach ($shop->banks as $bank) {
                if (!in_array($bank->id, $removedBankIds)) {
                    continue;
                }
                $pivotId = (int) $bank->pivot->id;
                $currentBalance = $ledgerService->getBankCurrentBalance($shop->id, $pivotId);
                $ledgerService->insertZeroingAdjustment(
                    $shop->id,
                    $pivotId,
                    $currentBalance,
                    'Bank removed from shop'
                );
            }
            $shop->banks()->sync($newBankIds);

            // CASE 2: Bank added — attach done by sync; create opening entry if opening_balance > 0
            $pivots = DB::table('bank_shop')
                ->where('shop_id', $shop->id)
                ->whereIn('bank_id', $newBankIds)
                ->get();

            foreach ($pivots as $pivot) {
                if (!in_array($pivot->bank_id, $addedBankIds)) {
                    continue;
                }
                $amount = isset($openingBalances[$pivot->bank_id]) ? (float) $openingBalances[$pivot->bank_id] : 0;
                $ledgerService->createBankOpeningEntry(
                    (int) $shop->id,
                    (int) $pivot->id,
                    $amount,
                    $shop->updated_at
                );
            }

            // CASE 3: Opening balance changed (kept banks) — adjustment entry for difference
            foreach ($pivots as $pivot) {
                if (!in_array($pivot->bank_id, $keptBankIds)) {
                    continue;
                }
                $oldOpening = $ledgerService->getBankOpeningBalance($shop->id, (int) $pivot->id);
                $newOpening = isset($openingBalances[$pivot->bank_id]) ? (float) $openingBalances[$pivot->bank_id] : 0;
                $difference = $newOpening - $oldOpening;
                $ledgerService->insertOpeningBalanceCorrection($shop->id, (int) $pivot->id, $difference);
            }
        });

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

