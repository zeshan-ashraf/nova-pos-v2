<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use App\Support\ActiveShop;

class ActivityController extends Controller
{
    /**
     * Display a listing of the activities.
     */
    public function index()
    {
        // Get the number of rows per page, default to 10
        $row = (int) request('row', 10);

        // Validate that the 'row' is between 1 and 100
        if ($row < 1 || $row > 100) {
            abort(400, 'The per-page parameter must be an integer between 1 and 100.');
        }

        // Paginate activities with the specified number of rows per page
        $activities = Activity::paginate($row);

        return view('activities.index', [
            'activities' => $activities,
        ]);
    }


    /**
     * Show the form for creating a new activity.
     */
    public function create()
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $customersQuery = Customer::query();
        if ($authUser->shop_id) {
            $customersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $customersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        $customers = $customersQuery->orderBy('name')->get();
        return view('activities.create', compact('customers'));
    }

    /**
     * Store a newly created activity in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
            'activity_cost' => 'required|numeric',
            'customer_id' => 'required|exists:customers,id',
            'image_1' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image_2' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $images = [];

        if ($request->hasFile('image_1')) {
            $image1Path = $request->file('image_1')->store('activities', 'public');
            $images[] = $image1Path;
        }

        if ($request->hasFile('image_2')) {
            $image2Path = $request->file('image_2')->store('activities', 'public');
            $images[] = $image2Path;
        }

        Activity::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'date' => $request->input('date'),
            'activity_cost' => $request->input('activity_cost'),
            'customer_id' => $request->input('customer_id'),
            'images' => json_encode($images), // Storing images as JSON array
        ]);

        return Redirect::route('activities.index')->with('success', 'Activity has been created!');
    }

    /**
     * Display the specified activity.
     */
    public function show(Activity $activity)
    {
        return view('activities.show', compact('activity'));
    }

    /**
     * Show the form for editing the specified activity.
     */
    public function edit(Activity $activity)
    {
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $customersQuery = Customer::query();
        if ($authUser->shop_id) {
            $customersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $customersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        $customers = $customersQuery->orderBy('name')->get();
        return view('activities.edit', compact('activity','customers'));
    }

    /**
     * Update the specified activity in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
            'activity_cost' => 'required|numeric',
            'customer_id' => 'required|exists:customers,id',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $images = is_array($activity->images) ? $activity->images : [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('activities', 'public');
            }
        }

        $activity->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'date' => $request->input('date'),
            'activity_cost' => $request->input('activity_cost'),
            'customer_id' => $request->input('customer_id'),
            'images' => $images, // Store as an array, not JSON
        ]);

        return Redirect::route('activities.index')->with('success', 'Activity has been updated!');
    }


    /**
     * Remove the specified activity from storage.
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();
        return Redirect::route('activities.index')->with('success', 'Activity has been deleted!');
    }
    public function activitySearch(Request $request)
    {
        $searchTerm = $request->get('search');

        $activities = Activity::with('customer')
            ->where('title', 'like', "%{$searchTerm}%")
            ->orWhere('description', 'like', "%{$searchTerm}%")
            ->orWhere('date', 'like', "%{$searchTerm}%")
            ->orWhere('activity_cost', 'like', "%{$searchTerm}%")
            ->orWhereHas('customer', function ($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%");
            })
            ->paginate(10);

        if ($request->ajax()) {
            return response()->json(['activities' => $activities]);
        }

        return view('activities.index', compact('activities'));
    }

}
