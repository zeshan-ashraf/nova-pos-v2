@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Sale Return Details</h4>
                    </div>
                </div>

                <div class="card-body">
                    <!-- begin: Show Data -->
                    <div class="row align-items-center">
                        <div class="form-group col-md-12">
                            <label>Customer Name</label>
                            <input type="text" class="form-control bg-white" value="{{ $saleReturn->customer->shopname ?? $saleReturn->customer->name }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Return Number</label>
                            <input type="text" class="form-control bg-white" value="{{ $saleReturn->return_no }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Original Invoice</label>
                            <input class="form-control bg-white" value="{{ $saleReturn->order->invoice_no ?? 'N/A' }}" readonly/>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Return Date</label>
                            <input type="text" class="form-control bg-white" value="{{ \Carbon\Carbon::parse($saleReturn->return_date)->format('Y-m-d H:i:s') }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Return Status</label>
                            <input class="form-control bg-white" value="{{ $saleReturn->return_status }}" readonly />
                        </div>
                        <div class="form-group col-md-6">
                            <label>Subtotal</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($saleReturn->sub_total, 2) }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>VAT</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($saleReturn->vat ?? 0, 2) }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Discount on Return</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($saleReturn->invoice_discount ?? 0, 2) }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Return Total</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($saleReturn->total, 2) }}" readonly>
                        </div>
                        @if($saleReturn->reason)
                        <div class="form-group col-md-12">
                            <label>Return Reason</label>
                            <textarea class="form-control bg-white" readonly>{{ $saleReturn->reason }}</textarea>
                        </div>
                        @endif
                    </div>
                    <!-- end: Show Data -->
                </div>
            </div>
        </div>

        <!-- Return Details Table -->
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Returned Items</h5>
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
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody class="ligth-body">
                                @foreach ($saleReturn->returnDetails as $detail)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $detail->product->product_name ?? 'N/A' }}</td>
                                    <td>{{ $detail->product->product_code ?? 'N/A' }}</td>
                                    <td>{{ $detail->quantity }}</td>
                                    <td>{{ number_format($detail->unitcost, 2) }}</td>
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
    <!-- Page end  -->
</div>

@endsection
