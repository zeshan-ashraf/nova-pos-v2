@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif

            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3 text-danger">
                        <i class="ri-alert-fill"></i> System Reset (Protected)
                    </h4>
                    <p class="mb-0">
                        This will permanently delete all data from the system except the admin user.<br>
                        <strong class="text-danger">This action cannot be undone.</strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Reset Confirmation</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <h5 class="alert-heading"><i class="ri-information-fill"></i> Important Information</h5>
                        <ul class="mb-0">
                            <li>All shops, users (except admin), and related data will be deleted</li>
                            <li>All products, categories, and inventory will be cleared</li>
                            <li>All orders, purchases, and transactions will be removed</li>
                            <li>All employees, customers, and suppliers will be deleted</li>
                            <li>The admin user (admin@gmail.com) will be preserved</li>
                            <li>Basic roles and permissions will be recreated</li>
                            <li>The system will be in maintenance mode during the reset</li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('system-reset.execute') }}" onsubmit="return confirmReset()">
                        @csrf

                        <div class="form-group">
                            <label for="password">Your Password <span class="text-danger">*</span></label>
                            <input 
                                type="password" 
                                class="form-control @error('password') is-invalid @enderror" 
                                id="password" 
                                name="password" 
                                required
                                placeholder="Enter your password to confirm"
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Enter your current password to authorize this operation.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="confirmation_text">
                                Type <strong class="text-danger">RESET SYSTEM</strong> to confirm <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="text" 
                                class="form-control @error('confirmation_text') is-invalid @enderror" 
                                id="confirmation_text" 
                                name="confirmation_text" 
                                required
                                placeholder="Type: RESET SYSTEM"
                            >
                            @error('confirmation_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                You must type exactly "RESET SYSTEM" (without quotes) to proceed.
                            </small>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input 
                                    type="checkbox" 
                                    class="custom-control-input" 
                                    id="understand_checkbox" 
                                    required
                                >
                                <label class="custom-control-label" for="understand_checkbox">
                                    I understand that this action is irreversible and will delete all data except the admin user
                                </label>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-danger">
                                <i class="ri-delete-bin-line mr-2"></i>
                                Execute System Reset
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-secondary ml-2">
                                <i class="ri-close-line mr-2"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Recent Reset Logs</h5>
                </div>
                <div class="card-body">
                    @if(count($recentLogs) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Result</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentLogs as $log)
                                        <tr>
                                            <td>
                                                <small>{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y H:i') }}</small>
                                            </td>
                                            <td>
                                                @if($log->result === 'success')
                                                    <span class="badge badge-success">Success</span>
                                                @else
                                                    <span class="badge badge-danger">Failed</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ $log->triggered_by_email }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <a href="{{ route('system-reset.logs') }}" class="btn btn-sm btn-outline-primary btn-block">
                            View All Logs
                        </a>
                    @else
                        <p class="text-muted mb-0">No reset history available.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Safety Features</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="ri-shield-check-line text-success"></i>
                            Password verification required
                        </li>
                        <li class="mb-2">
                            <i class="ri-shield-check-line text-success"></i>
                            Confirmation text required
                        </li>
                        <li class="mb-2">
                            <i class="ri-shield-check-line text-success"></i>
                            Super Admin role required
                        </li>
                        <li class="mb-2">
                            <i class="ri-shield-check-line text-success"></i>
                            Transaction-safe execution
                        </li>
                        <li class="mb-2">
                            <i class="ri-shield-check-line text-success"></i>
                            Concurrent reset prevention
                        </li>
                        <li class="mb-0">
                            <i class="ri-shield-check-line text-success"></i>
                            Complete audit logging
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmReset() {
    const confirmationText = document.getElementById('confirmation_text').value;
    
    if (confirmationText !== 'RESET SYSTEM') {
        alert('You must type "RESET SYSTEM" exactly to proceed.');
        return false;
    }
    
    return confirm(
        'FINAL WARNING\n\n' +
        'This will permanently delete all data from your system except the admin user.\n\n' +
        'Are you absolutely sure you want to continue?'
    );
}
</script>

@endsection
