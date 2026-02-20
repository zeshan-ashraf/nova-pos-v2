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
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Payment Logs for Order No.{{ $order->id }}</h4>
                    <input type="hidden" id="order_id" value="{{ $order->id }}">

                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('paymentlog.search', $order->id) }}" method="get">
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
                        <div class="input-group flex-nowrap col-sm-8">
                            <input type="text" id="search" class="form-control" name="search" placeholder="Search product" value="{{ request('search') }}" style="min-width: 200px;">
                            <div class="input-group-append">
                                <button type="button" id="search-btn" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                <a href="{{ route('order.paymentLog', $order->id) }}" class="input-group-text bg-danger"><i class="las la-trash"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-12" id="payment-log-list">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>Customer</th> <!-- Customer Name -->
                            <th>@sortablelink('created_at', 'Paid On')</th> <!-- Paid On -->
                            <th>@sortablelink('amount_paid', 'Amount Paid')</th> <!-- Amount Paid -->
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="payment-log-table-body" class="ligth-body">
                        @foreach ($paymentLogs as $paymentLog)
                        <tr>
                            <!-- Serial Number -->
                            <td>{{ (($paymentLogs->currentPage() * $paymentLogs->perPage()) - $paymentLogs->perPage()) + $loop->iteration }}</td>

                            <!-- Customer Name -->
                            <td>{{ $paymentLog->order->customer->name }}</td>

                            <!-- Date Paid -->
                            <td>{{ $paymentLog->created_at->format('Y-m-d') }}</td>

                            <!-- Amount Paid -->
                            <td>{{ number_format($paymentLog->amount_paid, 2) }}</td>

                            <!-- Action Column -->
                            <td>
                                <!-- Upload Invoice Button -->
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadInvoiceModal{{ $paymentLog->id }}">
                                    Upload Invoice
                                </button>

                                <!-- Modal for uploading invoice -->
                                <div class="modal fade" id="uploadInvoiceModal{{ $paymentLog->id }}" tabindex="-1" role="dialog" aria-labelledby="uploadInvoiceModalLabel{{ $paymentLog->id }}" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="uploadInvoiceModalLabel{{ $paymentLog->id }}">Upload Invoice for Payment Log #{{ $paymentLog->id }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="{{ route('paymentlog.uploadInvoice', $paymentLog->id) }}" method="POST" enctype="multipart/form-data">
                                                    @csrf
                                                    <div class="form-group">
                                                        <label for="invoice_image">Select Invoice Image</label>
                                                        <input type="file" name="invoice_image" id="invoice_image{{ $paymentLog->id }}" class="form-control-file" accept="image/*">
                                                    </div>
                                                    <button type="submit" class="btn btn-success">Upload</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- If invoice exists, show the 'View Invoice' button -->
                                @if ($paymentLog->invoice_image)
                                <button type="button" class="btn btn-info" data-toggle="modal" data-target="#viewInvoiceModal{{ $paymentLog->id }}">
                                    View Invoice
                                </button>

                                <!-- Modal for viewing invoice -->
                                <div class="modal fade" id="viewInvoiceModal{{ $paymentLog->id }}" tabindex="-1" role="dialog" aria-labelledby="viewInvoiceModalLabel{{ $paymentLog->id }}" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="viewInvoiceModalLabel{{ $paymentLog->id }}">Invoice for Payment Log #{{ $paymentLog->id }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <!-- Display the uploaded invoice image -->
                                                <img src="{{ asset('storage/' . $paymentLog->invoice_image) }}" alt="Invoice Image" class="img-fluid">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $paymentLogs->appends(request()->query())->links() }} <!-- Pagination links -->
        </div>

    </div>
    <!-- Page end  -->
</div>

@endsection
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Bind the search button click event
        $('#search-btn').on('click', function() {
            var searchQuery = $('#search').val(); // Get the search query from input
            var orderId = $("#order_id").val();
            // Send an AJAX request to search for payment logs
            $.ajax({
                url: "{{ route('paymentlog.search', ['orderId' => '__orderId__']) }}".replace('__orderId__', orderId), // Include order_id in the URL
                method: 'GET',
                data: { search: searchQuery }, // Pass the search term
                success: function(response) {
                    // If the request is successful, update the payment logs table
                    var paymentLogList = $('#payment-log-table-body');
                    paymentLogList.empty(); // Clear previous search results
                    console.log(response)

                    if (response.paymentLogs && Array.isArray(response.paymentLogs.data)) {
                        console.log('111111');
                        // Loop through payment logs and append them to the table
                        $.each(response.paymentLogs.data, function(index, paymentLog) {
                            var row = `
                                <tr>
                                    <td>${index + 1}</td>
                                    <!-- Accessing the customer name, ensure order and customer exist -->
                                    <td>${paymentLog.order?.customer?.name || 'No Customer Name'}</td>
                                    <!-- Format created_at date if needed (using toLocaleString for example) -->
                                    <td>${new Date(paymentLog.created_at).toLocaleString()}</td>
                                    <td>${paymentLog.amount_paid}</td>
                                    <td>
                                        <!-- Upload Invoice Button -->
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadInvoiceModal${paymentLog.id}">
                                            Upload Invoice
                                        </button>

                                        <!-- Modal for uploading invoice -->
                                        <div class="modal fade" id="uploadInvoiceModal${paymentLog.id}" tabindex="-1" role="dialog" aria-labelledby="uploadInvoiceModalLabel${paymentLog.id}" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="uploadInvoiceModalLabel${paymentLog.id}">Upload Invoice for Payment Log #${paymentLog.id}</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="/paymentlogs/${paymentLog.id}/upload-invoice" method="POST" enctype="multipart/form-data">
                                                            @csrf
                                                            <div class="form-group">
                                                                <label for="invoice_image">Select Invoice Image</label>
                                                                <input type="file" name="invoice_image" id="invoice_image${paymentLog.id}" class="form-control-file" accept="image/*">
                                                            </div>
                                                            <button type="submit" class="btn btn-success">Upload</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- View Invoice Button (only if invoice image exists) -->
                                        ${paymentLog.invoice_image ? `
                                            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#viewInvoiceModal${paymentLog.id}">View Invoice</button>
                                        ` : ''}

                                    </td>
                                </tr>
                            `;
                            $('#payment-log-table-body').append(row);
                        });
                    }
                     else {
                        // Display a message if no payment logs found
                        paymentLogList.append('<tr><td colspan="5" class="text-center">No Payment Logs Found.</td></tr>');
                    }
                },
                error: function() {
                    alert("Error occurred while searching. Please try again.");
                }
            });
        });

        // Optionally, trigger the search automatically when the user types
        $('#search').on('input', function() {
            $('#search-btn').click(); // Trigger the search button click
        });
    });
</script>





