<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Activity;
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

        $authUser = auth()->user();
        $activitiesQuery = Activity::query();

        // Apply shop filtering - super admin can see all activities, others only their shop
        if ($authUser && $authUser->shop_id) {
            $activitiesQuery->where('shop_id', $authUser->shop_id);
        }
        // Super admin (no shop_id) can see all activities, no filtering needed

        // Paginate activities with the specified number of rows per page
        $activities = $activitiesQuery->paginate($row);

        return view('activities.index', [
            'activities' => $activities,
        ]);
    }


    /**
     * Show the form for creating a new activity.
     */
    public function create()
    {
        return view('activities.create');
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

        $authUser = auth()->user();
        Activity::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'date' => $request->input('date'),
            'activity_cost' => $request->input('activity_cost'),
            'customer_id' => null,
            'shop_id' => $authUser ? $authUser->shop_id : null, // Set shop_id from logged in user
            'images' => json_encode($images), // Storing images as JSON array
        ]);

        return Redirect::route('activities.index')->with('success', 'Activity has been created!');
    }

    /**
     * Display the specified activity.
     */
    public function show(Activity $activity)
    {
        $this->ensureShopAccess($activity);
        return view('activities.show', compact('activity'));
    }

    /**
     * Show the form for editing the specified activity.
     */
    public function edit(Activity $activity)
    {
        $this->ensureShopAccess($activity);
        return view('activities.edit', compact('activity'));
    }

    /**
     * Update the specified activity in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $this->ensureShopAccess($activity);
        
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
            'activity_cost' => 'required|numeric',
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
            'customer_id' => null,
            'images' => $images, // Store as an array, not JSON
        ]);

        return Redirect::route('activities.index')->with('success', 'Activity has been updated!');
    }


    /**
     * Remove the specified activity from storage.
     */
    public function destroy(Activity $activity)
    {
        $this->ensureShopAccess($activity);
        $activity->delete();
        return Redirect::route('activities.index')->with('success', 'Activity has been deleted!');
    }
    public function activitySearch(Request $request)
    {
        $searchTerm = $request->get('search');
        $authUser = auth()->user();

        $activitiesQuery = Activity::where(function ($query) use ($searchTerm) {
            $query->where('title', 'like', "%{$searchTerm}%")
                ->orWhere('description', 'like', "%{$searchTerm}%")
                ->orWhere('date', 'like', "%{$searchTerm}%")
                ->orWhere('activity_cost', 'like', "%{$searchTerm}%");
        });

        // Apply shop filtering - super admin can see all activities, others only their shop
        if ($authUser && $authUser->shop_id) {
            $activitiesQuery->where('shop_id', $authUser->shop_id);
        }
        // Super admin (no shop_id) can see all activities, no filtering needed

        $activities = $activitiesQuery->paginate(10);

        if ($request->ajax()) {
            return response()->json(['activities' => $activities]);
        }

        return view('activities.index', compact('activities'));
    }

    /**
     * Ensure the current user has access to the activity based on shop.
     */
    protected function ensureShopAccess(Activity $activity): void
    {
        $authUser = auth()->user();

        // If user is not authenticated, deny access
        if (!$authUser) {
            abort(403, 'You must be authenticated to access this activity.');
        }

        // Super admin can access all activities
        if (!$authUser->shop_id) {
            return;
        }

        // Users with shop_id can only access activities from their shop
        if ($activity->shop_id !== $authUser->shop_id) {
            abort(403, 'You do not have access to this activity.');
        }
    }

}
