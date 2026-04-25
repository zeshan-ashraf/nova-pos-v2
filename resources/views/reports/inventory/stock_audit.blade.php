@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
    .stock-audit-mismatch td {
        background-color: #f8d7da !important;
    }
</style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Stock Audit</h4>
                    <p class="mb-0 text-muted">Audit expected stock versus ledger stock per product.</p>
                </div>
                <div>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                        <i class="ri-arrow-left-line mr-1"></i> Back to Reports
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.stock.audit') }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label for="mismatch_only" class="form-label">Stock Difference</label>
                                <select class="form-control" name="mismatch_only" id="mismatch_only">
                                    <option value="0" {{ !$mismatchOnly ? 'selected' : '' }}>Show All</option>
                                    <option value="1" {{ $mismatchOnly ? 'selected' : '' }}>Only Mismatch</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-search-line mr-1"></i> Filter
                                </button>
                                <a href="{{ route('reports.stock.audit') }}" class="btn btn-outline-secondary ml-2">Clear</a>
                                <a href="{{ route('reports.stock.audit', ['mismatch_only' => $mismatchOnly ? 1 : 0, 'export' => 'csv']) }}" class="btn btn-success ml-2">
                                    <i class="ri-file-excel-2-line mr-1"></i> Export CSV
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <h5 class="mb-0">Stock Audit Rows</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Code</th>
                                    <th class="text-right">Store</th>
                                    <th class="text-right">Total Purchased</th>
                                    <th class="text-right">Total Ordered</th>
                                    <th class="text-right">Sale Return</th>
                                    <th class="text-right">Purchase Return</th>
                                    <th class="text-right">Expected Stock</th>
                                    <th class="text-right">Ledger Stock</th>
                                    <th class="text-right">Difference</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $expectedStock = (float) ($row->expected_stock ?? 0);
                                        $ledgerStock = (float) ($row->ledger_stock ?? 0);
                                        $difference = (float) ($row->stock_difference ?? 0);
                                        $hasDiff = abs($expectedStock - $ledgerStock) > 0.000001 || abs($difference) > 0.000001;
                                        $dangerCellStyle = $hasDiff ? 'background-color: #f8d7da !important;' : '';
                                    @endphp
                                    <tr class="{{ $hasDiff ? 'stock-audit-mismatch' : '' }}">
                                        <td style="{{ $dangerCellStyle }}">{{ $row->product_name }}</td>
                                        <td style="{{ $dangerCellStyle }}">{{ $row->product_code ?? '–' }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->product_store, 2) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->total_purchased, 2) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->total_ordered, 2) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->total_sale_return, 2) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->total_purchase_return, 2) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->expected_stock, 2) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->ledger_stock, 2) }}</td>
                                        <td class="text-right font-weight-bold" style="{{ $dangerCellStyle }}">{{ number_format((float) $row->stock_difference, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center">No stock audit data found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (($rows->count() ?? 0) > 0)
                                <tfoot>
                                    <tr class="font-weight-bold bg-light">
                                        <td colspan="2">Totals</td>
                                        <td class="text-right">{{ number_format((float) ($totals['product_store'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($totals['total_purchased'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($totals['total_ordered'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($totals['total_sale_return'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($totals['total_purchase_return'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($totals['expected_stock'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($totals['ledger_stock'] ?? 0), 2) }}</td>
                                        <td class="text-right">{{ number_format((float) ($totals['stock_difference'] ?? 0), 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

