@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <h4 class="mb-3">Transfer Return Reviews</h4>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>Child Shop</th>
                            <th>Purchase Invoice</th>
                            <th>Original Sale Invoice</th>
                            <th>Return Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($returns as $return)
                        <tr>
                            <td>{{ (($returns->currentPage() * $returns->perPage()) - $returns->perPage()) + $loop->iteration }}</td>
                            <td>{{ $return->shop->name ?? 'N/A' }}</td>
                            <td>{{ $return->purchase->purchase_no ?? 'N/A' }}</td>
                            <td>{{ $return->purchase?->order?->invoice_no ?? 'N/A' }}</td>
                            <td>{{ number_format($return->total, 2) }}</td>
                            <td>{{ \Carbon\Carbon::parse($return->return_date)->format('Y-m-d H:i') }}</td>
                            <td><span class="badge badge-warning">{{ ucfirst($return->status) }}</span></td>
                            <td>
                                <a class="btn btn-info btn-sm" href="{{ route('transfer-returns.show', $return->id) }}">Review</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No pending transfer returns.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $returns->links() }}
        </div>
    </div>
</div>
@endsection

