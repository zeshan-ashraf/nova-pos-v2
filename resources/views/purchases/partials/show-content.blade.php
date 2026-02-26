{{-- Shared purchase details content: used by purchases.show page and by ledger modal (purchases/{id}/content). --}}
@php
    $in_modal = $in_modal ?? false;
@endphp
<div class="container-fluid purchase-detail-content">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="header-title">
                        <h4 class="card-title">Purchase Details</h4>
                    </div>
                    @if($in_modal)
                    <div>
                        <a href="{{ route('purchases.show', $purchase->id) }}" target="_blank" class="btn btn-outline-primary btn-sm mr-2" id="purchasePrintInvoiceBtn">Print purchase invoice</a>
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    </div>
                    @endif
                </div>

                <div class="card-body">
                    <!-- begin: Show Data -->
                    <div class="row align-items-center">
                        <div class="form-group col-md-12">
                            <label>Supplier Name</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->supplier->shopname ?? $purchase->supplier->name }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Supplier Email</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->supplier->email ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Supplier Phone</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->supplier->phone ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Purchase Date</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->purchase_date }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Purchase Number</label>
                            <input class="form-control bg-white" value="{{ $purchase->purchase_no }}" readonly/>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Payment Status</label>
                            <input class="form-control bg-white" value="{{ $purchase->payment_status }}" readonly />
                        </div>
                        <div class="form-group col-md-6">
                            <label>Paid Amount</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($purchase->pay ?? 0, 2) }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Due Amount</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($purchase->due ?? 0, 2) }}" readonly>
                        </div>
                        @if ($purchase->payment_status === 'bank')
                            <div class="form-group col-md-12">
                                <label>Bank Information</label>
                                @if ($purchase->shop && $purchase->shop->banks->isNotEmpty())
                                    <div class="d-flex flex-wrap">
                                        @foreach ($purchase->shop->banks as $bank)
                                            <span class="badge badge-primary mr-2 mb-2">{{ $bank->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No bank information available for this shop.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                    <!-- end: Show Data -->

                    @if (!$in_modal && $purchase->purchase_status == 'pending')
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="d-flex align-items-center list-action">
                                    <form action="{{ route('purchases.updateStatus') }}" method="POST" style="margin-bottom: 5px">
                                        @method('put')
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $purchase->id }}">
                                        <button type="submit" class="btn btn-success mr-2 border-none" data-toggle="tooltip" data-placement="top" title="" data-original-title="Complete">Complete Purchase</button>

                                        <a class="btn btn-danger mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Cancel" href="{{ route('purchases.pending') }}">Cancel</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-12">
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
                        @foreach ($purchaseDetails as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->product->product_name ?? 'N/A' }}</td>
                            <td>{{ $item->product->product_code ?? 'N/A' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unitcost, 2) }}</td>
                            <td>{{ number_format($item->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
