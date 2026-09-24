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
                                    <th>Unit</th>
                                    <th class="text-right">Available Stock</th>
                                    <th class="text-right">Total Purchased</th>
                                    <th class="text-right">Total Sold</th>
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
                                        $auditUnit = $row->unit ?? \App\Models\Product::UNIT_PIECE;
                                        $auditUnits = app(\App\Support\ProductUnitValidator::class);
                                        $hasDiff = $auditUnits->compare($row->expected_stock ?? 0, $row->ledger_stock ?? 0) !== 0
                                            || $auditUnits->compare($row->stock_difference ?? 0, 0) !== 0;
                                        $dangerCellStyle = $hasDiff ? 'background-color: #f8d7da !important;' : '';
                                        $fmtQty = fn ($value) => \App\Models\Product::formatQuantityForUnit($value, $auditUnit);
                                    @endphp
                                    <tr class="{{ $hasDiff ? 'stock-audit-mismatch' : '' }}">
                                        <td style="{{ $dangerCellStyle }}">{{ $row->product_name }}</td>
                                        <td style="{{ $dangerCellStyle }}">{{ $row->product_code ?? '–' }}</td>
                                        <td style="{{ $dangerCellStyle }}">{{ $auditUnit }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->available_stock) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->total_purchased) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->total_sold) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->total_sale_return) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->total_purchase_return) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->expected_stock) }}</td>
                                        <td class="text-right" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->ledger_stock) }}</td>
                                        <td class="text-right font-weight-bold" style="{{ $dangerCellStyle }}">{{ $fmtQty($row->stock_difference) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center">No stock audit data found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if (($rows->count() ?? 0) > 0)
                                <tfoot>
                                    @foreach ($totals as $total)
                                    <tr class="font-weight-bold bg-light">
                                        <td colspan="2">Total ({{ $total['unit'] }})</td>
                                        <td>{{ $total['unit'] }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['available_stock'], $total['unit']) }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['total_purchased'], $total['unit']) }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['total_sold'], $total['unit']) }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['total_sale_return'], $total['unit']) }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['total_purchase_return'], $total['unit']) }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['expected_stock'], $total['unit']) }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['ledger_stock'], $total['unit']) }}</td>
                                        <td class="text-right">{{ \App\Models\Product::formatQuantityForUnit($total['stock_difference'], $total['unit']) }}</td>
                                    </tr>
                                    @endforeach
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

