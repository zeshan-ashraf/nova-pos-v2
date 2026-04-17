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
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Purchases List</h4>
                </div>
                <div>
                    <a href="{{ route('purchases.create') }}" class="btn btn-primary add-list"><i class="fas fa-plus mr-3"></i>Create Purchase</a>
                    <a href="{{ route('purchases.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('purchases.index') }}" method="get">
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
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search purchase" value="{{ request('search') }}" style="min-width: 200px;">
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
                            <th>@sortablelink('purchase_no', 'Purchase No')</th>
                            <th>@sortablelink('supplier.shopname', 'Supplier')</th>
                            <th>@sortablelink('purchase_date', 'Purchase Date')</th>
                            <th>@sortablelink('total', 'Total')</th>
                            <th>@sortablelink('pay')</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($purchases as $purchase)
                        <tr>
                            <td>{{ (($purchases->currentPage() * $purchases->perPage()) - $purchases->perPage()) + $loop->iteration }}</td>
                            <td>{{ $purchase->purchase_no }}</td>
                            <td>{{ $purchase->supplier->shopname ?? $purchase->supplier->name ?? 'N/A' }}</td>
                            <td>{{ $purchase->purchase_date }}</td>
                            <td>{{ number_format($purchase->total ?? 0, 2) }}</td>
                            <td>{{ number_format($purchase->pay ?? 0, 2) }}</td>
                            <td>{{ $purchase->payment_status }}</td>
                            <td>
                                <span class="badge
                                    @if($purchase->purchase_status == 'complete')
                                        badge-success
                                    @elseif($purchase->purchase_status == 'pending')
                                        badge-danger
                                    @else
                                        badge-secondary
                                    @endif">
                                    {{ $purchase->purchase_status }}
                                </span>
                            </td>

                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('purchases.show', $purchase->id) }}">
                                        Details
                                    </a>
                                    @if(($purchase->landed_cost_status ?? 'pending') !== 'approved')
                                    <a class="btn btn-warning mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit" href="{{ route('purchases.edit', $purchase->id) }}">
                                        Edit
                                    </a>
                                    @endif
                                    @if(auth()->user()->can('purchases.delete'))
                                    <button type="button" class="btn btn-danger mr-2 border-none" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete" onclick="showDeleteModal({{ $purchase->id }})">
                                        <i class="ri-delete-bin-line mr-0"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $purchases->appends(request()->query())->links() }}
        </div>

    </div>
    <!-- Page end  -->
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePurchaseModal" tabindex="-1" role="dialog" aria-labelledby="deletePurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deletePurchaseModalLabel">Delete Purchase</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this purchase? This will:</p>
                <ul>
                    <li>Reverse stock increases (decrease stock)</li>
                    <li>Reverse supplier credit adjustments</li>
                    <li>Delete payment logs</li>
                </ul>
                <p class="text-danger"><strong>This action cannot be undone!</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deletePurchaseForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Purchase</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showDeleteModal(purchaseId) {
    $('#deletePurchaseForm').attr('action', `/purchases/${purchaseId}`);
    $('#deletePurchaseModal').modal('show');
}
</script>

@endsection
