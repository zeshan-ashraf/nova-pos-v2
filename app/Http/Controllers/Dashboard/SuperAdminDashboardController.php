<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    /**
     * Display the Super Admin comparison dashboard (view-only, no logic).
     */
    public function index(Request $request)
    {
        // View-only: use placeholder data structure; no business logic or queries here.
        $shops = []; // Will be populated later when implementing business logic.

        return view('super_admin.dashboard', [
            'shops' => $shops,
        ]);
    }
}

