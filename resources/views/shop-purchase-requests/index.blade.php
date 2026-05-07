@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                <h4 class="mb-0">Shop purchase requests</h4>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="{{ route('shop-purchase-requests.index', ['status' => 'pending']) }}" class="btn btn-outline-primary {{ ($statusFilter ?? '') === 'pending' ? 'active' : '' }}">Pending</a>
                    <a href="{{ route('shop-purchase-requests.index', ['status' => 'all']) }}" class="btn btn-outline-secondary {{ ($statusFilter ?? '') === 'all' ? 'active' : '' }}">All</a>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Invoice (mother)</th>
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $r)
                                    <tr>
                                        <td>{{ $r->id }}</td>
                                        <td>{{ $r->type }}</td>
                                        <td><span class="badge badge-secondary">{{ \App\Support\InterShopTransferStatus::uiPurchaseStatusLabel($r->status) }}</span></td>
                                        <td>{{ $r->payload['invoice_no'] ?? '—' }}</td>
                                        <td>{{ $r->updated_at?->format('Y-m-d H:i') }}</td>
                                        <td class="text-right">
                                            <a class="btn btn-sm btn-primary" href="{{ route('shop-purchase-requests.show', $r) }}">Open</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No purchase requests.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($requests->hasPages())
                    <div class="card-footer">{{ $requests->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

