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
            @if (request('adjusted') === '1')
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">Stock adjusted successfully.</div>
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
                    <h4 class="mb-3">Product List</h4>
                    <p class="mb-0">A product dashboard lets you easily gather and visualize product data from optimizing <br>
                        the product experience, ensuring product retention. </p>
                </div>
                <div>
                <a href="{{ route('products.importView') }}" class="btn btn-success add-list">Import</a>
                <a href="{{ route('products.exportData') }}" class="btn btn-warning add-list">Export</a>
                <a href="{{ route('products.create') }}" class="btn btn-primary add-list">Add Product</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('products.index') }}" method="get">
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
                                <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                <a href="{{ route('products.index') }}" class="input-group-text bg-danger"><i class="las la-trash"></i></a>
                            </div>
                        </div>
                    </div>
                    @if(isset($isSuperAdmin) && $isSuperAdmin && isset($shops) && $shops->isNotEmpty())
                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="shop_id">Filter by Shop:</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="shop_id" onchange="this.form.submit()">
                                <option value="">All Shops</option>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->id }}" {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                        {{ $shop->name }}
                                        @if($shop->is_parent)
                                            (Parent)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
            </form>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>@sortablelink('product_name', 'name')</th>
                            <th>@sortablelink('product_code', 'Code')</th>
                            <th>@sortablelink('category.name', 'category')</th>
                            <th>@sortablelink('supplier.name', 'supplier')</th>
                            <th>Shop</th>
                            <th>Cost</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($products as $product)
                        <tr>
                            <td>{{ (($products->currentPage() * $products->perPage()) - $products->perPage()) + $loop->iteration  }}</td>
                            <td>{{ $product->product_name }}</td>
                            <td>{{ $product->product_code ?? '—' }}</td>
                            <td>{{ $product->same_shop_category?->name ?? $product->category?->name ?? '–' }}</td>
                            <td>{{ $product->supplier ? $product->supplier->name : 'N/A' }}</td>
                            <td>
                                @if($product->shop)
                                    <span class="badge bg-primary">{{ $product->shop->name }}</span>
                                @else
                                    <span class="badge bg-secondary">Unassigned</span>
                                @endif
                            </td>
                            <td>{{ $product->buying_price }}</td>
                            <td>
                                @php
                                    $stock = $product->product_store ?? 0;
                                    $lowStockThreshold = $product->low_stock_warning ?? 10;
                                    
                                    if ($stock == 0) {
                                        $badgeClass = 'bg-danger';
                                        $badgeText = 'Out of Stock';
                                    } elseif ($stock < $lowStockThreshold) {
                                        $badgeClass = 'bg-warning';
                                        $badgeText = 'Low Stock';
                                    } else {
                                        $badgeClass = 'bg-success';
                                        $badgeText = 'In Stock';
                                    }
                                @endphp
                                <span class="badge rounded-pill {{ $badgeClass }}">{{ $stock }}</span>
                                @if ($stock < $lowStockThreshold)
                                    <small class="text-muted d-block">{{ $badgeText }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($product->status === 'active')
                                    <span class="badge rounded-pill bg-success">Active</span>
                                @elseif ($product->status === 'ordered')
                                    <span class="badge rounded-pill bg-warning">Ordered</span>
                                @else
                                    <span class="badge rounded-pill bg-secondary">{{ ucfirst($product->status ?? 'N/A') }}</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('products.destroy', $product->id) }}" method="POST" style="margin-bottom: 5px">
                                    @method('delete')
                                    @csrf
                                    <div class="d-flex align-items-center list-action">
                                        <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="View"
                                            href="{{ route('products.show', $product->id) }}"><i class="ri-eye-line mr-0"></i>
                                        </a>
                                        <a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit"
                                            href="{{ route('products.edit', $product->id) }}"><i class="ri-pencil-line mr-0"></i>
                                        </a>
                                        <a class="btn btn-secondary mr-2" data-toggle="tooltip" data-placement="top" title="View Stock Log" data-original-title="View Stock Log"
                                            href="{{ route('order.stockLog', $product->id) }}">
                                        <i class="ri-archive-line mr-0"></i>
                                     </a>
                                        <button type="button" class="btn btn-primary mr-2 stock-adjust-btn" data-toggle="tooltip" data-placement="top" title="Stock Adjustment" data-original-title="Stock Adjustment"
                                            data-product-id="{{ $product->id }}"
                                            data-product-name="{{ e($product->product_name) }}"
                                            data-current-stock="{{ (int)($product->product_store ?? 0) }}">
                                            <i class="ri-stack-line mr-0"></i>
                                        </button>
                                            <button type="submit" class="btn btn-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                    </div>
                                </form>
                            </td>
                        </tr>

                        @empty
                        <div class="alert text-white bg-danger" role="alert">
                            <div class="iq-alert-text">Data not Found.</div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <i class="ri-close-line"></i>
                            </button>
                        </div>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $products->appends(request()->query())->links() }}
        </div>
    </div>
    <!-- Page end  -->
</div>

{{-- Stock Adjustment modal: no page reload; populated from clicked row --}}
<div class="modal fade" id="stockAdjustModal" tabindex="-1" role="dialog" aria-labelledby="stockAdjustModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stockAdjustModalLabel">Stock Adjustment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="stock-adjust-error" class="alert alert-danger d-none" role="alert"></div>
                <form id="stock-adjust-form">
                    @csrf
                    <input type="hidden" name="product_id" id="adjust-product-id" value="">
                    <div class="form-group">
                        <label for="adjust-product-name">Product Name</label>
                        <input type="text" class="form-control" id="adjust-product-name" readonly>
                    </div>
                    <div class="form-group">
                        <label for="adjust-current-stock">Current Stock</label>
                        <input type="number" class="form-control" id="adjust-current-stock" readonly>
                    </div>
                    <div class="form-group">
                        <label for="adjustment_type">Adjustment Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="adjustment_type" id="adjustment_type" required>
                            <option value="">Select type</option>
                            <option value="damaged">Damaged</option>
                            <option value="expired">Expired</option>
                            <option value="lost_theft">Lost / Theft</option>
                            <option value="stock_found">Stock Found</option>
                            <option value="manual_add">Manual Correction (Add)</option>
                            <option value="manual_remove">Manual Correction (Remove)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="adjust-qty">Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="qty" id="adjust-qty" min="1" step="1" required placeholder="0">
                        <small class="form-text text-muted">For Damaged / Expired / Lost: must be &le; current stock.</small>
                    </div>
                    <div class="form-group">
                        <label for="adjust-date">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date" id="adjust-date" required>
                    </div>
                    <div class="form-group">
                        <label for="adjust-time">Time <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" name="time" id="adjust-time" required>
                    </div>
                    <div class="form-group">
                        <label for="adjust-reason">Reason / Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason" id="adjust-reason" rows="3" required placeholder="Required"></textarea>
                    </div>
                    <div class="form-group mb-2">
                        <strong>Projected stock after adjustment:</strong>
                        <span id="adjust-projected-stock" class="ml-2">—</span>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="adjust-confirm" name="confirm">
                        <label class="form-check-label" for="adjust-confirm">I understand this will permanently affect stock</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="stock-adjust-submit">Submit</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('specificpagescripts')
<script>
(function() {
    var OUT_TYPES = ['damaged', 'expired', 'lost_theft', 'manual_remove'];
    var IN_TYPES = ['stock_found', 'manual_add'];

    function showError(msg) {
        var el = document.getElementById('stock-adjust-error');
        el.textContent = msg || '';
        el.classList.toggle('d-none', !msg);
    }

    function clearError() {
        showError('');
    }

    function getCurrentStock() {
        return parseInt(document.getElementById('adjust-current-stock').value, 10) || 0;
    }

    function getQty() {
        return parseInt(document.getElementById('adjust-qty').value, 10) || 0;
    }

    function getType() {
        return document.getElementById('adjustment_type').value;
    }

    function updateProjectedStock() {
        var current = getCurrentStock();
        var qty = getQty();
        var type = getType();
        var projected = current;
        if (type && qty > 0) {
            if (OUT_TYPES.indexOf(type) !== -1) {
                projected = Math.max(0, current - qty);
            } else if (IN_TYPES.indexOf(type) !== -1) {
                projected = current + qty;
            }
        }
        document.getElementById('adjust-projected-stock').textContent = projected;
    }

    function validateForm() {
        var type = getType();
        var qty = getQty();
        var reason = (document.getElementById('adjust-reason').value || '').trim();
        var confirmed = document.getElementById('adjust-confirm').checked;

        if (!type) return { ok: false, msg: 'Please select an adjustment type.' };
        if (!qty || qty < 1) return { ok: false, msg: 'Quantity must be greater than 0.' };
        if (!reason) return { ok: false, msg: 'Reason / notes are required.' };
        if (!confirmed) return { ok: false, msg: 'Please confirm that you understand this will permanently affect stock.' };

        var current = getCurrentStock();
        if (OUT_TYPES.indexOf(type) !== -1 && qty > current) {
            return { ok: false, msg: 'Quantity cannot exceed current stock (' + current + ').' };
        }
        return { ok: true };
    }

    function setSubmitEnabled(enabled) {
        document.getElementById('stock-adjust-submit').disabled = !enabled;
    }

    function refreshSubmitState() {
        var v = validateForm();
        setSubmitEnabled(v.ok);
    }

    document.querySelectorAll('.stock-adjust-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-product-id');
            var name = this.getAttribute('data-product-name');
            var stock = this.getAttribute('data-current-stock') || '0';
            document.getElementById('adjust-product-id').value = id;
            document.getElementById('adjust-product-name').value = name;
            document.getElementById('adjust-current-stock').value = stock;
            document.getElementById('adjustment_type').value = '';
            document.getElementById('adjust-qty').value = '';
            var now = new Date();
            document.getElementById('adjust-date').value = now.toLocaleDateString('en-CA');
            document.getElementById('adjust-time').value = now.toTimeString().slice(0, 5);
            document.getElementById('adjust-reason').value = '';
            document.getElementById('adjust-confirm').checked = false;
            clearError();
            updateProjectedStock();
            setSubmitEnabled(false);
            $('#stockAdjustModal').modal('show');
        });
    });

    ['adjustment_type', 'adjust-qty', 'adjust-reason'].forEach(function(id) {
        var el = document.getElementById(id === 'adjust-qty' ? 'adjust-qty' : (id === 'adjust-reason' ? 'adjust-reason' : 'adjustment_type'));
        if (!el) return;
        el.addEventListener('input', function() { clearError(); updateProjectedStock(); refreshSubmitState(); });
        el.addEventListener('change', function() { clearError(); updateProjectedStock(); refreshSubmitState(); });
    });
    document.getElementById('adjust-confirm').addEventListener('change', refreshSubmitState);

    document.getElementById('stock-adjust-submit').addEventListener('click', function() {
        var v = validateForm();
        if (!v.ok) {
            showError(v.msg);
            return;
        }
        clearError();
        var form = document.getElementById('stock-adjust-form');
        var payload = {
            product_id: document.getElementById('adjust-product-id').value,
            adjustment_type: document.getElementById('adjustment_type').value,
            qty: getQty(),
            date: document.getElementById('adjust-date').value,
            time: document.getElementById('adjust-time').value,
            reason: document.getElementById('adjust-reason').value.trim(),
            _token: document.querySelector('#stock-adjust-form input[name="_token"]').value
        };
        this.disabled = true;
        var token = document.querySelector('#stock-adjust-form input[name="_token"]').value;
        fetch('{{ route("stock.adjust") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(payload)
        })
        .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, status: res.status, data: data }; }); })
        .then(function(r) {
            if (r.ok) {
                $('#stockAdjustModal').modal('hide');
                var baseUrl = '{{ route("products.index") }}';
                var params = new URLSearchParams(window.location.search);
                params.set('adjusted', '1');
                window.location.href = baseUrl + (params.toString() ? '?' + params.toString() : '');
            } else {
                showError(r.data.message || (r.data.errors && Object.values(r.data.errors).flat().join(' ')) || 'Request failed.');
            }
        })
        .catch(function() {
            showError('Network error. Please try again.');
        })
        .finally(function() {
            document.getElementById('stock-adjust-submit').disabled = false;
        });
    });
})();
</script>
@endsection
