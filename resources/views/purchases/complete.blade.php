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
                    <h4 class="mb-3">Complete Purchases</h4>
                </div>
                <div>
                    <a href="{{ route('purchases.complete') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('purchases.complete') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10') selected="selected" @endif>10</option>
                                <option value="25" @if(request('row') == '25') selected="selected" @endif>25</option>
                                <option value="50" @if(request('row') == '50') selected="selected" @endif>50</option>
                                <option value="100" @if(request('row') == '100') selected="selected" @endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search purchase" value="{{ request('search') }}">
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
                            <th>Purchase No</th>
                            <th>Supplier</th>
                            <th>Purchase Date</th>
                            <th>Total</th>
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
                            <td>
                                <span class="badge badge-success">{{ $purchase->purchase_status }}</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('purchases.show', $purchase->id) }}">
                                        Details
                                    </a>
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

@endsection
