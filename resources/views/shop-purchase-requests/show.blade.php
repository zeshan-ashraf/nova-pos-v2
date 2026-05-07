@extends('dashboard.body.main')

@section('container')
@php
    $p = $req->payload ?? [];
    $lines = $p['lines'] ?? [];
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Inter-shop purchase request #{{ $req->id }}</h5>
                    <span class="badge badge-info" style="color: #000 !important;">{{ \App\Support\InterShopTransferStatus::uiPurchaseStatusLabel($req->status) }}</span>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">From <strong>{{ $p['mother_shop_name'] ?? 'Mother shop' }}</strong></p>
                    <p class="mb-1"><strong>Reference invoice:</strong> {{ $p['invoice_no'] ?? '—' }}</p>
                    <p class="mb-1"><strong>Supplier:</strong> {{ $p['supplier_name'] ?? '—' }}</p>
                    @if(!empty($p['comment']))
                        <p class="mb-3"><strong>Note:</strong> {{ $p['comment'] }}</p>
                    @endif

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Unit</th>
                                    <th class="text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lines as $line)
                                    <tr>
                                        <td>{{ $line['product_name'] ?? '—' }}</td>
                                        <td>{{ $line['product_code'] ?? '—' }}</td>
                                        <td class="text-right">{{ $line['quantity'] ?? '' }}</td>
                                        <td class="text-right">{{ number_format((float)($line['unit_price'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float)($line['total'] ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted text-center py-3">Line details were not stored for this record. Use the linked purchase for full detail if available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div><strong>Subtotal:</strong> {{ number_format((float)($p['sub_total'] ?? 0), 2) }}</div>
                            <div><strong>Total:</strong> {{ number_format((float)($p['total'] ?? 0), 2) }}</div>
                        </div>
                        <div class="col-md-6">
                            <div><strong>Pay:</strong> {{ number_format((float)($p['pay'] ?? 0), 2) }}</div>
                            <div><strong>Due:</strong> {{ number_format((float)($p['due'] ?? 0), 2) }}</div>
                        </div>
                    </div>

                    @if(auth()->user()->shop_id && (int) auth()->user()->shop_id === (int) $req->child_shop_id)
                        @if($req->status === \App\Support\InterShopTransferStatus::PENDING)
                            <form action="{{ route('shop-purchase-requests.approve', $req) }}" method="POST" class="d-inline mr-2">
                                @csrf
                                <button type="submit" class="btn btn-success">Approve transfer</button>
                            </form>
                            <form action="{{ route('shop-purchase-requests.cancel', $req) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this request? Mother shop reserved stock will be released.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">Cancel request</button>
                            </form>
                            <p class="text-muted small mt-2 mb-0">Stock and accounting at your shop apply when the mother shop completes dispatch after you approve.</p>
                        @elseif($req->status === \App\Support\InterShopTransferStatus::APPROVED)
                            <div class="alert alert-info mb-0">Approved. Waiting for the mother shop to complete dispatch.</div>
                            @if($req->mapped_purchase_id)
                                <a class="btn btn-outline-primary mt-2" href="{{ route('purchases.show', $req->mapped_purchase_id) }}">View linked purchase</a>
                            @endif
                        @elseif($req->status === \App\Support\InterShopTransferStatus::COMPLETED)
                            <div class="alert alert-success mb-0">Completed.</div>
                            @if($req->mapped_purchase_id)
                                <a class="btn btn-outline-primary mt-2" href="{{ route('purchases.show', $req->mapped_purchase_id) }}">View purchase</a>
                            @endif
                        @else
                            <div class="alert alert-secondary mb-0">This request is {{ \App\Support\InterShopTransferStatus::uiPurchaseStatusLabel($req->status) }}.</div>
                        @endif
                    @endif

                    <div class="mt-3">
                        <a href="{{ route('shop-purchase-requests.index') }}" class="btn btn-secondary">Back to list</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

