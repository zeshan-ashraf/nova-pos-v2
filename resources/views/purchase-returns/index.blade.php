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
                    <h4 class="mb-3">Purchase Return Requests</h4>
                </div>
                <div>
                    @can('purchase_returns.create')
                    <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary add-list">
                        <i class="fas fa-plus mr-3"></i>Create Return Request
                    </a>
                    @endcan
                    <a href="{{ route('purchase-returns.index') }}" class="btn btn-danger add-list">
                        <i class="las la-trash mr-3"></i>Clear Search
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('purchase-returns.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10') selected @endif>10</option>
                                <option value="25" @if(request('row') == '25') selected @endif>25</option>
                                <option value="50" @if(request('row', '50') == '50') selected @endif>50</option>
                                <option value="100" @if(request('row') == '100') selected @endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="status">Status:</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="status" id="status" onchange="this.form.submit()">
                                <option value="">All</option>
                                <option value="pending" @if(request('status')==='pending') selected @endif>Pending</option>
                                <option value="approved" @if(request('status')==='approved') selected @endif>Approved</option>
                                <option value="rejected" @if(request('status')==='rejected') selected @endif>Rejected</option>
                            </select>
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
                            <th>Return No</th>
                            <th>Purchase Invoice</th>
                            <th>Linked Mother Sale</th>
                            <th>Child Shop</th>
                            <th>Return Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($returns as $return)
                        <tr>
                            <td>{{ (($returns->currentPage() * $returns->perPage()) - $returns->perPage()) + $loop->iteration }}</td>
                            <td>{{ $return->return_no }}</td>
                            <td>{{ $return->purchase->purchase_no ?? 'N/A' }}</td>
                            <td>{{ $return->purchase?->order?->invoice_no ?? 'N/A' }}</td>
                            <td>{{ $return->shop->name ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($return->return_date)->format('Y-m-d H:i') }}</td>
                            <td>{{ number_format($return->total, 2) }}</td>
                            <td>
                                <span class="badge
                                    @if($return->status === 'pending') badge-warning
                                    @elseif($return->status === 'approved') badge-success
                                    @else badge-danger @endif">
                                    {{ ucfirst($return->status) }}
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-info btn-sm" href="{{ route('purchase-returns.show', $return->id) }}">
                                    Details
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No purchase return requests found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $returns->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

