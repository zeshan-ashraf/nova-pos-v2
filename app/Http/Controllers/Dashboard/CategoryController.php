<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Category;
use App\Support\ActiveShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Resolve current shop for category scope (logged-in user's shop or active shop for super admin).
     */
    private function currentShopId(): ?int
    {
        $user = auth()->user();
        if ($user->shop_id) {
            return (int) $user->shop_id;
        }
        $active = ActiveShop::current();
        return $active ? (int) $active->id : null;
    }

    /**
     * Display a listing of the resource (only categories belonging to current shop).
     */
    public function index()
    {
        $row = (int) request('row', 50);

        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        $shopId = $this->currentShopId();
        $query = Category::with('shop')
            ->filter(request(['search']))
            ->sortable();

        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        } else {
            $query->whereNull('shop_id');
        }

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

        $shopId = $this->currentShopId();
        $name = trim($request->input('name'));
        $slug = Str::slug($name);

        // If category with same name (or slug) exists for this shop, return existing id (5.2: if slug already exist use that category id)
        $existing = Category::when($shopId !== null, fn ($q) => $q->where('shop_id', $shopId), fn ($q) => $q->whereNull('shop_id'))
            ->where(function ($q) use ($name, $slug) {
                $q->where('name', $name)->orWhere('slug', $slug);
            })
            ->first();

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
            $exists = Category::when($shopId !== null, fn ($q) => $q->where('shop_id', $shopId), fn ($q) => $q->whereNull('shop_id'))
                ->where('slug', $slug)->exists();
            if (!$exists) {
                break;
            }
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        $category = Category::create([
            'shop_id' => $shopId,
            'name'    => $name,
            'slug'    => $slug,
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
        $shopId = $this->currentShopId();
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
     * Ensure the category belongs to the current user's shop (or active shop for super admin).
     */
    protected function ensureShopAccess(Category $category): void
    {
        $shopId = $this->currentShopId();
        if ($shopId === null) {
            if ($category->shop_id !== null) {
                abort(403, 'You do not have access to this category.');
            }
            return;
        }
        if ((int) $category->shop_id !== $shopId) {
            abort(403, 'You do not have access to this category.');
        }
    }
}
