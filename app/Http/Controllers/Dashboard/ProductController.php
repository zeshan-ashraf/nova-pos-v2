<?php

namespace App\Http\Controllers\Dashboard;

use Exception;
use App\Models\StockLog;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Shop;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use App\Support\ActiveShop;
use App\Services\ProductCodeService;

use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Picqer\Barcode\BarcodeGeneratorHTML;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Haruncpi\LaravelIdGenerator\IdGenerator;

class ProductController extends Controller
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
        $isSuperAdmin = !$authUser->shop_id;

        $productsQuery = Product::with(['supplier', 'shop.parent'])
            ->filter(request(['search']))
            ->sortable();

        // Apply shop filtering
        if ($authUser->shop_id) {
            // Child shop or parent shop user - only see their allowed shops
            $productsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            // Super admin - can see all, but can filter by shop
            if (request()->has('shop_id') && request('shop_id')) {
                $productsQuery->where('shop_id', request('shop_id'));
            } else {
                // Show all products (including unassigned)
                $productsQuery->where(function ($query) use ($visibleShopIds) {
                    $query->whereNull('shop_id');
                    if ($visibleShopIds->isNotEmpty()) {
                        $query->orWhereIn('shop_id', $visibleShopIds);
                    }
                });
            }
        }

        $products = $productsQuery->paginate($row)->appends(request()->query());
        Product::eagerLoadSameShopCategory($products->getCollection());

        return view('products.index', [
            'products' => $products,
            'isSuperAdmin' => $isSuperAdmin,
            'shops' => $isSuperAdmin ? Shop::orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * Return the next available MHB-XXXX code (preview only; does not consume).
     * Same code is shown until a product is saved with it.
     */
    public function generateCode()
    {
        $code = app(ProductCodeService::class)->getNextAvailableCode();
        return response()->json(['code' => $code]);
    }

    /**
     * Show the form for creating a new resource.
     * Categories: only those belonging to current user's shop (or active shop for super admin).
     */
    public function create()
    {
        $shopId = auth()->user()->shop_id ?? (ActiveShop::current()?->id);
        $categories = $shopId
            ? Category::where('shop_id', $shopId)->orderBy('name')->get()
            : Category::whereNull('shop_id')->orderBy('name')->get();

        return view('products.create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Auto-assign shop_id based on user's shop (needed for validation)
        $authUser = auth()->user();
        $shopId = null;
        if ($authUser->shop_id) {
            $shopId = $authUser->shop_id;
        } else {
            // Super admin - use active shop if available
            $activeShop = ActiveShop::current();
            if ($activeShop) {
                $shopId = $activeShop->id;
            }
        }

        $rules = [
            'product_image' => 'image|file|max:1024',
            'product_name' => 'required|string',
            'product_code' => [
                'nullable',
                'string',
                'max:255',
                Rule::when($request->filled('product_code'), [
                    'regex:/^MHB-\d+$/',
                    $shopId
                        ? Rule::unique('products', 'product_code')->where('shop_id', $shopId)
                        : Rule::unique('products', 'product_code')->whereNull('shop_id'),
                ]),
            ],
            'category_id' => 'required|integer',
            'supplier_id' => 'nullable|integer',
            'product_garage' => 'string|nullable',
            'product_store' => 'nullable|integer|min:0',
            'low_stock_warning' => 'nullable|integer|min:0',
            'buying_date' => 'date_format:Y-m-d|max:10|nullable',
            'buying_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ];
        $messages = ['product_code.regex' => 'Product code must start with MHB- followed by digits (e.g. MHB-1001).'];
        $validatedData = $request->validate($rules, $messages);

        // Auto-generate product code if empty (with lock so no conflict with import/other users)
        $codeService = app(ProductCodeService::class);
        $validatedData['product_code'] = $codeService->ensureCode($validatedData['product_code'] ?? null, null, $shopId);
        
        // Set default product_store to 0 if not provided
        if (!isset($validatedData['product_store']) || $validatedData['product_store'] === null) {
            $validatedData['product_store'] = 0;
        }
        
        // Determine status based on prices
        // If both prices are present → status = 'active' (product is sellable)
        // If either price is missing → status = 'ordered' (product is not sellable)
        $hasBuyingPrice = !empty($validatedData['buying_price']);
        $hasSellingPrice = !empty($validatedData['selling_price']);
        
        if ($hasBuyingPrice && $hasSellingPrice) {
            $validatedData['status'] = 'active';
        } else {
            $validatedData['status'] = 'ordered';
        }
        
        // Set default low_stock_warning if not provided
        if (!isset($validatedData['low_stock_warning']) || $validatedData['low_stock_warning'] === null) {
            $validatedData['low_stock_warning'] = 10;
        }

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('product_image')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/products/';

            $file->storeAs($path, $fileName);
            $validatedData['product_image'] = $fileName;
        }

        // Set shop_id
        if ($shopId) {
            $validatedData['shop_id'] = $shopId;
        }

        // Set default values for buying_price and selling_price if not provided
        if (!isset($validatedData['buying_price']) || $validatedData['buying_price'] === null) {
            $validatedData['buying_price'] = 0;
        }
        if (!isset($validatedData['selling_price']) || $validatedData['selling_price'] === null) {
            $validatedData['selling_price'] = 0;
        }
        
        // Set default product_store if not provided
        if (!isset($validatedData['product_store']) || $validatedData['product_store'] === null) {
            $validatedData['product_store'] = 0;
        }

        $product = Product::create($validatedData);

        // Sync sequence so this code is not shown as "next available" again
        $codeService->syncSequenceAfterAssign($codeService->parseNumericPart($product->product_code) ?? 0);

        // If AJAX request, return JSON response
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Product has been created successfully!',
                'product' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                    'buying_price' => $product->buying_price,
                    'selling_price' => $product->selling_price,
                    'product_store' => $product->product_store ?? 0,
                ]
            ]);
        }

        return Redirect::route('products.index')->with('success', 'Product has been created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        // Barcode Generator
        $generator = new BarcodeGeneratorHTML();

        $barcode = $generator->getBarcode($product->product_code, $generator::TYPE_CODE_128);

        return view('products.show', [
            'product' => $product,
            'barcode' => $barcode,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        $this->ensureShopAccess($product);

        $shopId = auth()->user()->shop_id ?? (ActiveShop::current()?->id);
        $categories = $shopId
            ? Category::where('shop_id', $shopId)->orderBy('name')->get()
            : Category::whereNull('shop_id')->orderBy('name')->get();

        return view('products.edit', [
            'categories' => $categories,
            'product'   => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $this->ensureShopAccess($product);
        
        // Get shop_id for validation
        $shopId = $product->shop_id;

        $rules = [
            'product_image' => 'image|file|max:1024',
            'product_name' => 'required|string',
            'product_code' => [
                'required',
                'string',
                'max:255',
                'regex:/^MHB-\d+$/',
                $shopId !== null
                ? Rule::unique('products', 'product_code')->ignore($product->id)->where('shop_id', $shopId)
                : Rule::unique('products', 'product_code')->ignore($product->id)->whereNull('shop_id'),
            ],
            'category_id' => 'required|integer',
            'supplier_id' => 'nullable|integer',
            'product_garage' => 'string|nullable',
            'product_store' => 'nullable|integer|min:0',
            'low_stock_warning' => 'nullable|integer|min:0',
            'buying_date' => 'date_format:Y-m-d|max:10|nullable',
            'buying_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ];
        $messages = ['product_code.regex' => 'Product code must start with MHB- followed by digits (e.g. MHB-1001).'];
        $validatedData = $request->validate($rules, $messages);

        /**
         * Handle upload image with Storage.
         */
        if ($file = $request->file('product_image')) {
            $fileName = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();
            $path = 'public/products/';

            /**
             * Delete photo if exists.
             */
            if($product->product_image){
                Storage::delete($path . $product->product_image);
            }

            $file->storeAs($path, $fileName);
            $validatedData['product_image'] = $fileName;
        }
        
        $oldStockQty = $product->product_store;
        
        // Set default product_store to current value if not provided, or 0 if null
        if (!isset($validatedData['product_store']) || $validatedData['product_store'] === null) {
            $validatedData['product_store'] = $product->product_store ?? 0;
        }
        
        // Determine status based on prices
        // If both prices are present → status = 'active' (product is sellable)
        // If either price is missing → status = 'ordered' (product is not sellable)
        $hasBuyingPrice = !empty($validatedData['buying_price']);
        $hasSellingPrice = !empty($validatedData['selling_price']);
        
        if ($hasBuyingPrice && $hasSellingPrice) {
            $validatedData['status'] = 'active';
        } else {
            $validatedData['status'] = 'ordered';
        }
            Product::where('id', $product->id)->update($validatedData);
            $newStockQty = $validatedData['product_store'] ?? $product->product_store;
            $stockChange = $newStockQty - $oldStockQty;
            // Only log stock update if supplier_id exists
            if (isset($validatedData['supplier_id']) && $validatedData['supplier_id']) {
                $this->logStockUpdate($product, $validatedData, $stockChange);
            }
            
        return Redirect::route('products.index')->with('success', 'Product has been updated!');
    }
    public function logStockUpdate(Product $product, $validatedData, $stockChange)
{
    // Only create log if supplier_id exists
    if (isset($validatedData['supplier_id']) && $validatedData['supplier_id']) {
        StockLog::create([
            'product_id' => $product->id,
            'supplier_id' => $validatedData['supplier_id'],
            'stock_qty' => $stockChange, 
            'price' => $validatedData['buying_price'],
        ]);
    }
}
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $this->ensureShopAccess($product);
        /**
         * Delete photo if exists.
         */
        if($product->product_image){
            Storage::delete('public/products/' . $product->product_image);
        }

        Product::destroy($product->id);

        return Redirect::route('products.index')->with('success', 'Product has been deleted!');
    }

    /**
     * Show the form for importing a new resource.
     */
    public function importView()
    {
        return view('products.import');
    }

    public function importStore(Request $request)
    {
        $request->validate([
            'upload_file' => 'required|file|mimes:xls,xlsx',
        ]);

        $the_file = $request->file('upload_file');

        try{
            $spreadsheet = IOFactory::load($the_file->getRealPath());
            $sheet        = $spreadsheet->getActiveSheet();
            $row_limit    = $sheet->getHighestDataRow();
            
            if ($row_limit < 2) {
                return Redirect::route('products.importView')->with('error', 'The Excel file is empty or has no data rows!');
            }
            
            $row_range    = range( 2, $row_limit );
            $data = array();
            $authUser = auth()->user();
            
            // Get shop_id for assignment
            $shopId = null;
            if ($authUser->shop_id) {
                $shopId = $authUser->shop_id;
            } else {
                // Super admin - use active shop if available
                $activeShop = ActiveShop::current();
                if ($activeShop) {
                    $shopId = $activeShop->id;
                }
            }
            
            $now = now();
            $codeService = app(ProductCodeService::class);

            foreach ( $row_range as $row ) {
                $productName = $sheet->getCell( 'A' . $row )->getValue();
                $categoryValue = $sheet->getCell( 'B' . $row )->getValue();
                $categoryValue = trim((string) $categoryValue);

                // Skip empty rows
                if (empty($productName) || $categoryValue === '') {
                    continue;
                }

                // Resolve category_id: if numeric use as id; else lookup by name (case-insensitive) or create
                if (filter_var($categoryValue, FILTER_VALIDATE_INT) !== false) {
                    $categoryId = (int) $categoryValue;
                } else {
                    $name = $categoryValue;
                    $query = Category::query();
                    if ($shopId !== null) {
                        $query->where('shop_id', $shopId);
                    } else {
                        $query->whereNull('shop_id');
                    }
                    $category = $query->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])->first();
                    if ($category) {
                        $categoryId = $category->id;
                    } else {
                        $category = Category::create([
                            'name'   => $name,
                            'shop_id' => $shopId,
                            'slug'   => Str::slug($name),
                        ]);
                        $categoryId = $category->id;
                    }
                }

                $rawCode = $sheet->getCell( 'D' . $row )->getValue();
                $code = is_scalar($rawCode) ? trim((string) $rawCode) : '';

                // Generate code when missing or invalid/duplicate (same MHB-1001 rule; lock so no conflict with manual add)
                $code = $codeService->ensureCode($code === '' ? null : $code, null, $shopId);

                $rowData = [
                    'product_name' => $productName,
                    'category_id' => $categoryId,
                    'product_code' => $code,
                    'product_garage' => $sheet->getCell( 'E' . $row )->getValue(),
                    'product_image' => $sheet->getCell( 'F' . $row )->getValue(),
                    'product_store' => $sheet->getCell( 'G' . $row )->getValue(),
                    'buying_date' => $sheet->getCell( 'H' . $row )->getValue(),
                    'buying_price' => $sheet->getCell( 'J' . $row )->getValue(),
                    'selling_price' => $sheet->getCell( 'K' . $row )->getValue(),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Assign shop_id if available
                if ($shopId) {
                    $rowData['shop_id'] = $shopId;
                }

                // Optional fields - only add if they have values
                $supplierId = $sheet->getCell( 'C' . $row )->getValue();
                if ($supplierId) {
                    $rowData['supplier_id'] = $supplierId;
                }

                $expireDate = $sheet->getCell( 'I' . $row )->getValue();
                if ($expireDate) {
                    $rowData['expire_date'] = $expireDate;
                }

                $data[] = $rowData;
            }
            
            if (empty($data)) {
                return Redirect::route('products.importView')->with('error', 'No valid data found in the Excel file. Please check that product name and category are provided.');
            }

            // Sync sequence so assigned codes are not shown as "next available" again
            $maxNum = 0;
            foreach ($data as $row) {
                $n = $codeService->parseNumericPart($row['product_code'] ?? null);
                if ($n !== null && $n > $maxNum) {
                    $maxNum = $n;
                }
            }
            if ($maxNum > 0) {
                $codeService->syncSequenceAfterAssign($maxNum);
            }

            Product::insert($data);

        } catch (Exception $e) {
            return Redirect::route('products.importView')->with('error', 'There was a problem uploading the data: ' . $e->getMessage());
        }
        return Redirect::route('products.importView')->with('success', 'Data has been successfully imported! ' . count($data) . ' product(s) added.');
    }

    public function exportExcel($products){
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '4000M');

        try {
            $spreadSheet = new Spreadsheet();
            $spreadSheet->getActiveSheet()->getDefaultColumnDimension()->setWidth(20);
            $spreadSheet->getActiveSheet()->fromArray($products);
            $Excel_writer = new Xls($spreadSheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="Products_ExportedData.xls"');
            header('Cache-Control: max-age=0');
            ob_end_clean();
            $Excel_writer->save('php://output');
            exit();
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Export products to Excel. Scoped by shop: user's shop or (super admin) active shop.
     * Only exports products that have a shop_id.
     */
    function exportData(){
        $authUser = auth()->user();
        $shopId = $authUser->shop_id ?? ActiveShop::current()?->id;

        if (!$shopId) {
            return Redirect::route('products.index')
                ->with('error', 'Please select a shop to export products.');
        }

        $products = Product::where('shop_id', $shopId)
            ->orderByDesc('id')
            ->get();

        $product_array [] = array(
            'Product Name',
            'Category Id',
            'Supplier Id',
            'Product Code',
            'Product Garage',
            'Product Image',
            'Stock',
            'Buying Date',
            'Expire Date',
            'Buying Price',
            'Selling Price',
        );

        foreach($products as $product)
        {
            $product_array[] = array(
                'Product Name' => $product->product_name,
                'Category Id' => $product->category_id,
                'Supplier Id' => $product->supplier_id,
                'Product Code' => $product->product_code,
                'Product Garage' => $product->product_garage,
                'Product Image' => $product->product_image,
                'Stock' =>$product->product_store,
                'Buying Date' =>$product->buying_date,
                'Expire Date' =>$product->expire_date,
                'Buying Price' =>$product->buying_price,
                'Selling Price' =>$product->selling_price,
            );
        }

        $this->ExportExcel($product_array);
    }

    /**
     * Ensure the current user has access to the product based on shop.
     */
    protected function ensureShopAccess(Product $product): void
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        if ($authUser->shop_id) {
            // Child shop or parent shop user - must belong to allowed shops
            if ($product->shop_id && !$visibleShopIds->contains($product->shop_id)) {
                abort(403, 'You do not have access to this product.');
            }
        }
        // Super admin can access all products
    }
}
