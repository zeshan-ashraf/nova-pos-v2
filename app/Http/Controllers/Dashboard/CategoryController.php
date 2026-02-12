<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource (only categories belonging to current shop; scope applied by BelongsToShop trait).
     */
    public function index()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $query = Category::with('shop')
            ->filter(request(['search']))
            ->sortable();

        return view('categories.index', [
            'categories' => $query->paginate($row)->appends(request()->query()),
            'open_modal' => request('open_modal') === '1',
        ]);
    }

    /**
     * Create is replaced by modal on index; redirect to index with modal open.
     */
    public function create()
    {
        return Redirect::route('categories.index', ['open_modal' => '1']);
    }

    /**
     * Store a newly created resource (AJAX: return JSON with id/name/slug; otherwise redirect).
     */
    public function store(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $request->validate(['name' => 'required|string|max:255']);

        $name = trim($request->input('name'));
        $slug = Str::slug($name);

        // If category with same name (or slug) exists for this shop, return existing id (scope applied by trait)
        $existing = Category::where(function ($q) use ($name, $slug) {
            $q->where('name', $name)->orWhere('slug', $slug);
        })->first();

        if ($existing) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Category already exists.',
                    'category' => ['id' => $existing->id, 'name' => $existing->name, 'slug' => $existing->slug],
                ]);
            }
            return Redirect::route('categories.index')->with('success', 'Category already exists.');
        }

        // Ensure slug is unique for this shop (append number if needed)
        $baseSlug = $slug;
        $counter = 0;
        while (true) {
            $exists = Category::where('slug', $slug)->exists();
            if (! $exists) {
                break;
            }
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        $category = Category::create([
            'name' => $name,
            'slug' => $slug,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Category has been created!',
                'category' => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug],
            ]);
        }

        return Redirect::route('categories.index')->with('success', 'Category has been created!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        $this->ensureShopAccess($category);
        return view('categories.edit', [
            'category' => $category,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $this->ensureShopAccess($category);
        $shopId = $category->shop_id;
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category->id)->where(fn ($q) => $shopId !== null ? $q->where('shop_id', $shopId) : $q->whereNull('shop_id')),
            ],
            'slug' => [
                'required',
                'alpha_dash',
                Rule::unique('categories', 'slug')->ignore($category->id)->where(fn ($q) => $shopId !== null ? $q->where('shop_id', $shopId) : $q->whereNull('shop_id')),
            ],
        ];
        $validated = $request->validate($rules);
        Category::where('id', $category->id)->update($validated);
        return Redirect::route('categories.index')->with('success', 'Category has been updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $this->ensureShopAccess($category);
        Category::where('id', $category->id)->delete();
        return Redirect::route('categories.index')->with('success', 'Category has been deleted!');
    }

    /**
     * Ensure the category belongs to the current user's shop.
     */
    protected function ensureShopAccess(Category $category): void
    {
        $userShopId = auth()->user()->shop_id;
        if ($userShopId === null && $category->shop_id !== null) {
            abort(403, 'You do not have access to this category.');
        }
        if ($userShopId !== null && (int) $category->shop_id !== (int) $userShopId) {
            abort(403, 'You do not have access to this category.');
        }
    }
}
