@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">System Reset History</h4>
                    <p class="mb-0">Complete audit log of all system reset operations.</p>
                </div>
                <div>
                    <a href="{{ route('system-reset.show') }}" class="btn btn-outline-primary">
                        <i class="ri-arrow-left-line mr-2"></i>
                        Back to Reset Page
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    @if(count($logs) > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Timestamp</th>
                                        <th>Triggered By</th>
                                        <th>Email</th>
                                        <th>IP Address</th>
                                        <th>Result</th>
                                        <th>Duration</th>
                                        <th>Error Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                        <tr class="{{ $log->result === 'success' ? '' : 'table-danger' }}">
                                            <td>{{ $log->id }}</td>
                                            <td>
                                                {{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}
                                                <br>
                                                <small class="text-muted">
                                                    {{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}
                                                </small>
                                            </td>
                                            <td>{{ $log->triggered_by_user_id }}</td>
                                            <td>{{ $log->triggered_by_email }}</td>
                                            <td>{{ $log->ip_address ?? 'N/A' }}</td>
                                            <td>
                                                @if($log->result === 'success')
                                                    <span class="badge badge-success">
                                                        <i class="ri-check-line"></i> Success
                                                    </span>
                                                @else
                                                    <span class="badge badge-danger">
                                                        <i class="ri-close-line"></i> Failed
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($log->duration_seconds)
                                                    {{ $log->duration_seconds }}s
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($log->error_message)
                                                    <small class="text-danger">{{ $log->error_message }}</small>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="ri-file-list-line" style="font-size: 48px; color: #ccc;"></i>
                            <p class="text-muted mt-3 mb-0">No reset logs found.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
