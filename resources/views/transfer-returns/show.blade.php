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
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h4 class="card-title">Transfer Return Review</h4>
                    <a href="{{ route('transfer-returns.pending') }}" class="btn btn-secondary btn-sm">Back</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Child Shop Name</label>
                            <input class="form-control bg-white" value="{{ $purchaseReturn->shop->name ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Status</label>
                            <input class="form-control bg-white" value="{{ ucfirst($purchaseReturn->status) }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Original Sale Invoice</label>
                            <input class="form-control bg-white" value="{{ $purchaseReturn->purchase?->order?->invoice_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Purchase Invoice</label>
                            <input class="form-control bg-white" value="{{ $purchaseReturn->purchase->purchase_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Return Date</label>
                            <input class="form-control bg-white" value="{{ \Carbon\Carbon::parse($purchaseReturn->return_date)->format('Y-m-d H:i:s') }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Total Return Value</label>
                            <input class="form-control bg-white" value="{{ number_format($purchaseReturn->total, 2) }}" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Returned Products</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive rounded mb-3">
                        <table class="table mb-0">
                            <thead class="bg-white text-uppercase">
                                <tr class="ligth ligth-data">
                                    <th>No.</th>
                                    <th>Product Name</th>
                                    <th>Product Code</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody class="ligth-body">
                        @foreach ($purchaseReturn->details as $detail)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                            <td>{{ $detail->product->resolved_name ?? 'N/A' }}</td>
                            <td>{{ $detail->product->resolved_code ?? 'N/A' }}</td>
                                    <td>{{ $detail->quantityWithUnit() }}</td>
                                    <td>{{ number_format($detail->price, 2) }}</td>
                                    <td>{{ number_format($detail->total, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if($purchaseReturn->status === 'pending')
        <div class="col-lg-12 mt-2">
            @can('transfer_returns.approve')
            <form method="POST" action="{{ route('transfer-returns.approve', $purchaseReturn->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success">Approve</button>
            </form>
            @endcan

            @can('transfer_returns.reject')
            <form method="POST" action="{{ route('transfer-returns.reject', $purchaseReturn->id) }}" class="d-inline ml-2">
                @csrf
                <button type="submit" class="btn btn-danger">Reject</button>
            </form>
            @endcan
        </div>
        @endif
    </div>
</div>
@endsection

