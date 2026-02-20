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
                    <h4 class="mb-3">{{ $product->product_name }}</h4>
                    <input type="hidden" id="product_id" value="{{ $product->id }}">

                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('stock.search',$product->id) }}" method="get">
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
                                <a href="{{ route('order.stockLog', $product->id) }}" class="input-group-text bg-danger"><i class="las la-trash"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-12" id="product-list">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>@sortablelink('supplier.name', 'Supplier')</th> <!-- Supplier Name -->
                            <th>@sortablelink('created_at', 'Purchased On')</th> <!-- Purchased On (created_at) -->
                            <th>@sortablelink('stock_qty', 'Stock Purchased')</th> <!-- Stock Purchased -->
                            <th>@sortablelink('price', 'Price')</th> <!-- Price -->
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="stock-log-table-body" class="ligth-body">
                        @foreach ($stockLogs as $stockLog)
                        <tr>
                            <td>{{ (($stockLogs->currentPage() * $stockLogs->perPage()) - $stockLogs->perPage()) + $loop->iteration }}</td> <!-- Serial Number -->
                            <td>{{ $stockLog->supplier ? $stockLog->supplier->name : 'N/A' }}</td>
                            <td>{{ $stockLog->created_at->format('Y-m-d') }}</td>
                            <td>{{ $stockLog->stock_qty }}</td>
                            <td>{{ $stockLog->price }}</td>
                            <td>
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadInvoiceModal{{ $stockLog->id }}">
                                    Upload Invoice
                                </button>

                                <!-- Modal for uploading invoice -->
                                <div class="modal fade" id="uploadInvoiceModal{{ $stockLog->id }}" tabindex="-1" role="dialog" aria-labelledby="uploadInvoiceModalLabel{{ $stockLog->id }}" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="uploadInvoiceModalLabel{{ $stockLog->id }}">Upload Invoice for Stock Log #{{ $stockLog->id }}</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <form action="{{ route('order.uploadInvoice', $stockLog->id) }}" method="POST" enctype="multipart/form-data">
                                                    @csrf
                                                    <div class="form-group">
                                                        <label for="invoice_image">Select Invoice Image</label>
                                                        <input type="file" name="invoice_image" id="invoice_image{{ $stockLog->id }}" class="form-control-file" accept="image/*">
                                                    </div>
                                                    <button type="submit" class="btn btn-success">Upload</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if ($stockLog->invoice_image)
                                <button type="button" class="btn btn-info" data-toggle="modal" data-target="#viewInvoiceModal{{ $stockLog->id }}">View Invoice</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
            {{ $stockLogs->appends(request()->query())->links() }}
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
            var productId = $("#product_id").val();

            // Send an AJAX request to search for stock logs
            $.ajax({
                url: "{{ route('stock.search', ['productId' => '__productId__']) }}".replace('__productId__', productId),
                method: 'GET',
                data: { search: searchQuery }, // Pass the search term
                success: function(response) {
                    // If the request is successful, update the stock logs table
                    var stockLogList = $('#stock-log-table-body');
                    stockLogList.empty(); // Clear previous search results

                    if (response.stockLogs.length > 0) {
                        // Loop through stock logs and append them to the table
                        $.each(response.stockLogs, function(index, stockLog) {
                            var row = `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${stockLog.supplier.name}</td>
                                    <td>${stockLog.created_at}</td>
                                    <td>${stockLog.stock_qty}</td>
                                    <td>${stockLog.price}</td>
                                    <td>
                                        <!-- Upload Invoice Button -->
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadInvoiceModal${stockLog.id}">
                                            Upload Invoice
                                        </button>

                                        <!-- Modal for uploading invoice -->
                                        <div class="modal fade" id="uploadInvoiceModal${stockLog.id}" tabindex="-1" role="dialog" aria-labelledby="uploadInvoiceModalLabel${stockLog.id}" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="uploadInvoiceModalLabel${stockLog.id}">Upload Invoice for Stock Log #${stockLog.id}</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form action="{{ url('order') }}/${stockLog.id}/upload-invoice" method="POST" enctype="multipart/form-data" id="invoiceForm${stockLog.id}">
                                                            @csrf
                                                            <div class="form-group">
                                                                <label for="invoice_image">Select Invoice Image</label>
                                                                <input type="file" name="invoice_image" id="invoice_image${stockLog.id}" class="form-control-file" accept="image/*">
                                                            </div>
                                                            <button type="submit" class="btn btn-success" id="uploadBtn${stockLog.id}">Upload</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- View Invoice Button (only if invoice image exists) -->
                                        ${stockLog.invoice_image ? `
                                            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#viewInvoiceModal${stockLog.id}">View Invoice</button>
                                        ` : ''}

                                    </td>
                                </tr>
                            `;
                            stockLogList.append(row);
                        });
                    } else {
                        // Display a message if no stock logs found
                        stockLogList.append('<tr><td colspan="6" class="text-center">No Stock Logs Found.</td></tr>');
                    }
                },
                error: function() {
                    alert("Error occurred while searching. Please try again.");
                }
            });
        });

        // Optionally, you can trigger the search automatically when the user types
        $('#search').on('input', function() {
            $('#search-btn').click(); // Trigger the search button click
        });
    });
</script>


