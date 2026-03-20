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
                    <h4 class="mb-3">Sale Returns List</h4>
                </div>
                <div>
                    <a href="{{ route('sale-returns.create') }}" class="btn btn-primary add-list"><i class="fas fa-plus mr-3"></i>Create Return</a>
                    <a href="{{ route('sale-returns.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('sale-returns.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10') selected="selected" @endif>10</option>
                                <option value="25" @if(request('row') == '25') selected="selected" @endif>25</option>
                                <option value="50" @if(request('row', '50') == '50') selected="selected" @endif>50</option>
                                <option value="100" @if(request('row') == '100') selected="selected" @endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group flex-nowrap">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search return" value="{{ request('search') }}" style="min-width: 200px;">
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
                            <th>@sortablelink('return_no', 'Return No')</th>
                            <th>@sortablelink('order.invoice_no', 'Invoice No')</th>
                            <th>@sortablelink('customer.shopname', 'Customer')</th>
                            <th>@sortablelink('return_date', 'Return Date')</th>
                            <th>@sortablelink('total')</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($returns as $return)
                        <tr>
                            <td>{{ (($returns->currentPage() * $returns->perPage()) - $returns->perPage()) + $loop->iteration }}</td>
                            <td>{{ $return->return_no }}</td>
                            <td>
                                <a href="{{ route('order.orderDetails', $return->order_id) }}">
                                    {{ $return->order->invoice_no ?? 'N/A' }}
                                </a>
                            </td>
                            <td>{{ $return->customer->shopname ?? $return->customer->name ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($return->return_date)->format('Y-m-d H:i') }}</td>
                            <td>{{ number_format($return->total, 2) }}</td>
                            <td>
                                <span class="badge {{ $return->return_status == 'completed' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $return->return_status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('sale-returns.show', $return->id) }}">
                                        Details
                                    </a>
                                    <button type="button"
                                            class="btn btn-danger btn-sm border-none"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title=""
                                            data-original-title="Delete"
                                            onclick="showDeleteReturnModal({{ $return->id }})">
                                        <i class="ri-delete-bin-line mr-0"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $returns->appends(request()->query())->links() }}
        </div>

    </div>
    <!-- Page end  -->
</div>

<!-- Delete Sale Return Confirmation Modal -->
<div class="modal fade" id="deleteReturnModal" tabindex="-1" role="dialog" aria-labelledby="deleteReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteReturnModalLabel">Confirm Sale Return Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <h4 class="mb-2" style="font-weight: 700;">
                        <i class="ri-alert-line"></i> Warning
                    </h4>
                    <p class="mb-0" style="font-size: 1.05rem;">
                        <strong>Deleting this Sale Return will reverse all its effects.</strong>
                    </p>
                </div>
                <div class="alert alert-warning" role="alert" style="font-size: 0.9rem;">
                    <p class="mb-2">The system will:</p>
                    <ul class="mb-2" style="font-size: 0.9rem;">
                        <li><strong>Remove returned items from inventory</strong> &mdash; stock quantities will be reduced by the returned amounts</li>
                        <li><strong>Reverse stock log entries</strong> &mdash; all stock history entries created for this return will be removed</li>
                        <li><strong>Restore customer balance (if applicable)</strong> &mdash; the customer&rsquo;s running balance will be restored</li>
                        <li><strong>Remove accounting ledger entries</strong> &mdash; adjustment entries for this return will be deleted</li>
                        <li><strong>Remove refund logs</strong> &mdash; any refund payment logs created for this return will be deleted</li>
                        <li><strong>Mark this sale return as deleted</strong> &mdash; the return and its line items will be soft deleted</li>
                    </ul>
                    <p class="mb-0">
                        <strong>This operation cannot be undone.</strong> Are you sure you want to delete this Sale Return?
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteReturnForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="ri-delete-bin-line mr-1"></i> Delete Return
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function showDeleteReturnModal(returnId) {
        // Match the delete confirmation flow used on the orders page:
        // - open Bootstrap modal
        // - wire DELETE form action dynamically for the selected resource
        $('#deleteReturnModal').modal('show');
        $('#deleteReturnForm').attr('action', '/sale-returns/' + returnId);
    }
</script>

@endsection
