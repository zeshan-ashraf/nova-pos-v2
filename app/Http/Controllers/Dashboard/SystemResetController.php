<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\SystemReset\ResetLogger;
use App\Services\SystemReset\SystemResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * System Reset Controller
 * 
 * Handles HTTP layer for system reset operations.
 * All business logic is delegated to SystemResetService.
 * 
 * IMPORTANT: This controller must NOT contain any database logic.
 */
class SystemResetController extends Controller
{
    private SystemResetService $resetService;
    private ResetLogger $logger;

    public function __construct(
        SystemResetService $resetService,
        ResetLogger $logger
    ) {
        $this->resetService = $resetService;
        $this->logger = $logger;

        // Require Super Admin role for all methods
        $this->middleware(['auth', 'role:Super Admin']);
    }

    /**
     * Show the system reset confirmation page
     * 
     * @return \Illuminate\View\View
     */
    public function show()
    {
        // Get recent reset logs for display
        $recentLogs = $this->logger->getRecentLogs(5);

        return view('system-reset.confirm', [
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * Execute the system reset
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'password' => 'required|string',
            'confirmation_text' => 'required|string',
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Get the IP address
        $ipAddress = $request->ip();

        // Log the attempt
        Log::warning('System reset attempt', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $ipAddress,
        ]);

        // Execute the reset via the service
        $result = $this->resetService->execute(
            $user,
            $validated['password'],
            $validated['confirmation_text'],
            $ipAddress
        );

        // Return appropriate response
        if ($result['success']) {
            return redirect()
                ->route('system-reset.show')
                ->with('success', $result['message']);
        } else {
            return redirect()
                ->route('system-reset.show')
                ->with('error', $result['message'])
                ->withInput();
        }
    }

    /**
     * Show the reset logs history
     * 
     * @return \Illuminate\View\View
     */
    public function logs()
    {
        $logs = $this->logger->getRecentLogs(50);

        return view('system-reset.logs', [
            'logs' => $logs,
        ]);
    }
}
