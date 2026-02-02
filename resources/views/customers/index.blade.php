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
                    <h4 class="mb-3">Customer List</h4>
                    <p class="mb-0">A customer dashboard lets you easily gather and visualize customer data from optimizing <br>
                        the customer experience, ensuring customer retention. </p>
                </div>
                <div>
                    <a href="{{ route('customers.create') }}" class="btn btn-primary add-list"><i class="fa-solid fa-plus mr-3"></i>Add Customer</a>
                    <a href="{{ route('customers.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('customers.index') }}" method="get">
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
                            <div class="input-group">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search customer" value="{{ request('search') }}">
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
                            {{-- <th>@sortablelink('name')</th> --}} {{-- Name column removed from UI - may be needed in future --}}
                            <th>@sortablelink('shopname', 'Customer Name')</th>
                            <th>@sortablelink('phone')</th>
                            <th>Credit Limit</th>
                            <th>Credit Amount</th>
                            <th>Credit Days</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($customers as $customer)
                        <tr>
                            <td>{{ (($customers->currentPage() * $customers->perPage()) - $customers->perPage()) + $loop->iteration  }}</td>
                            {{-- <td>{{ $customer->name }}</td> --}} {{-- Name column removed from UI - may be needed in future --}}
                            <td>{{ $customer->shopname }}</td>
                            <td>{{ $customer->phone }}</td>
                            <td>{{ number_format($customer->credit_limit ?? 0, 2) }}</td>
                            <td>{{ number_format($customer->credit_amount ?? 0, 2) }}</td>
                            <td>{{ $customer->credit_days ?? 0 }}</td>
                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="badge badge-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="View"
                                        href="{{ route('customers.show', $customer->id) }}"><i class="ri-eye-line mr-0"></i>
                                    </a>
                                    <a class="badge bg-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit"
                                        href="{{ route('customers.edit', $customer->id) }}""><i class="ri-pencil-line mr-0"></i>
                                    </a>
                                    <a class="badge badge-warning mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Credit Log"
                                        href="{{ route('customers.creditLog', $customer->id) }}"><i class="ri-file-list-line mr-0"></i>
                                    </a>
                                    <form action="{{ route('customers.destroy', $customer->id) }}" method="POST" style="margin-bottom: 5px">
                                        @method('delete')
                                        @csrf
                                        <button type="submit" class="badge bg-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->appends(request()->query())->links() }}
        </div>
    </div>
    <!-- Page end  -->
</div>

@endsection
