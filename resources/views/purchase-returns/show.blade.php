@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Purchase Return Request Details</h4>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="form-group col-md-6">
                            <label>Return Number</label>
                            <input class="form-control bg-white" value="{{ $purchaseReturn->return_no }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Status</label>
                            <input class="form-control bg-white" value="{{ ucfirst($purchaseReturn->status) }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Purchase Invoice</label>
                            <input class="form-control bg-white" value="{{ $purchaseReturn->purchase->purchase_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Mother Sale Invoice</label>
                            <input class="form-control bg-white" value="{{ $purchaseReturn->purchase?->order?->invoice_no ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Return Date</label>
                            <input class="form-control bg-white" value="{{ \Carbon\Carbon::parse($purchaseReturn->return_date)->format('Y-m-d H:i:s') }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Total</label>
                            <input class="form-control bg-white" value="{{ number_format($purchaseReturn->total, 2) }}" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Return Items</h5>
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
                                    <td>{{ $detail->quantity }}</td>
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
    </div>
</div>
@endsection

