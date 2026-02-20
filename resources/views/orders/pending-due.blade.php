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
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Pending Order List</h4>
                </div>
                <div>
                    <a href="{{ route('order.pendingDue') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('order.pendingDue') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10')selected="selected"@endif>10</option>
                                <option value="25" @if(request('row') == '25')selected="selected"@endif>25</option>
                                <option value="50" @if(request('row', '50') == '50')selected="selected"@endif>50</option>
                                <option value="100" @if(request('row') == '100')selected="selected"@endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group flex-nowrap">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search order" value="{{ request('search') }}" style="min-width: 200px;">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>Invoice No</th>
                            <th>@sortablelink('customer.name', 'name')</th>
                            <th>@sortablelink('order_date', 'order date')</th>
                            <th>Payment</th>
                            <th>@sortablelink('pay')</th>
                            <th>@sortablelink('due')</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($orders as $order)
                        <tr>
                            <td>{{ (($orders->currentPage() * $orders->perPage()) - $orders->perPage()) + $loop->iteration  }}</td>
                            <td>{{ $order->invoice_no }}</td>
                            <td>{{ $order->customer->name }}</td>
                            <td>{{ Carbon\Carbon::parse($order->order_date)->format('Y m, d') }}</td>
                            <td>{{ $order->payment_status }}</td>
                            <td>
                                <span class="btn btn-warning text-white">
                                    {{ $order->pay }}
                                </span>
                            </td>
                            <td>
                                <span class="btn btn-danger text-white">
                                    {{ $order->due }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('order.orderDetails', $order->id) }}">
                                        Details
                                    </a>
                                    @if($order->due == 0)
                                        <button type="button" class="btn btn-success mr-2" data-toggle="modal" data-target="#completeOrderModal" onclick="showCompleteOrderModal({{ $order->id }}, '{{ $order->invoice_no }}')">
                                            Complete Order
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-primary-dark mr-2" data-toggle="modal" data-target=".bd-example-modal-lg" id="{{ $order->id }}" onclick="payDue(this.id)">Pay Due</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->appends(request()->query())->links() }}
        </div>
    </div>
    <!-- Page end  -->
</div>

<!-- Pay Due Modal -->
<div class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('order.updateDue') }}" method="post" id="payDueForm">
                @csrf
                <input type="hidden" name="order_id" id="order_id">
                <div class="modal-body">
                    <h3 class="modal-title text-center mx-auto">Pay Due</h3>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="due_payment_method">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-control bg-white @error('payment_method') is-invalid @enderror" id="due_payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="cheque">Cheque</option>
                            </select>
                            @error('payment_method')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group due-bank-row" id="due_bank_row" style="display: none;">
                            <label for="due_shop_bank_id">Select Bank <span class="text-danger">*</span></label>
                            <select class="form-control bg-white @error('shop_bank_id') is-invalid @enderror" id="due_shop_bank_id" name="shop_bank_id">
                                <option value="">Select Bank</option>
                                @foreach($shopBanks ?? [] as $sb)
                                <option value="{{ $sb->id }}">{{ $sb->name }}</option>
                                @endforeach
                            </select>
                            @error('shop_bank_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="due">Pay Now <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-white @error('due') is-invalid @enderror" id="due" name="due">
                            @error('due')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Pay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Order Confirmation Modal -->
<div class="modal fade" id="completeOrderModal" tabindex="-1" role="dialog" aria-labelledby="completeOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="completeOrderModalLabel">
                    <i class="ri-checkbox-circle-line mr-2"></i>Complete Order
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to complete this order?</p>
                <div class="alert alert-info">
                    <strong>Invoice No:</strong> <span id="completeOrderInvoiceNo"></span><br>
                    <strong>Status:</strong> This order will be marked as completed.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form action="{{ route('order.updateStatus') }}" method="POST" id="completeOrderForm" style="display: inline;">
                    @method('put')
                    @csrf
                    <input type="hidden" name="id" id="completeOrderId">
                    <button type="submit" class="btn btn-success">
                        <i class="ri-check-line mr-1"></i> Complete Order
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    var orderDueUrl = "{{ route('order.orderDueAjax', ':id') }}"; 
    function payDue(id){
        $.ajax({
            type: 'GET',
            url: orderDueUrl.replace(':id', id), 
            dataType: 'json',
            success: function(data) {
                $('#due').val(data.due);
                $('#order_id').val(data.id);
                $('#due_payment_method').val('');
                $('#due_shop_bank_id').val('').prop('required', false);
                $('#due_bank_row').hide();
            }
        });
    }

    $(document).on('change', '#due_payment_method', function() {
        var method = $(this).val();
        if (method === 'bank' || method === 'cheque') {
            $('#due_bank_row').show();
            $('#due_shop_bank_id').prop('required', true);
        } else {
            $('#due_bank_row').hide();
            $('#due_shop_bank_id').prop('required', false).val('');
        }
    });

    $('#payDueForm').on('submit', function(e) {
        var method = $('#due_payment_method').val();
        if (method === 'bank' || method === 'cheque') {
            if (!$('#due_shop_bank_id').val()) {
                e.preventDefault();
                alert('Please select a bank.');
                $('#due_shop_bank_id').focus();
                return false;
            }
        }
    });
    
    function showCompleteOrderModal(orderId, invoiceNo) {
        $('#completeOrderId').val(orderId);
        $('#completeOrderInvoiceNo').text(invoiceNo);
    }
</script>

@endsection
