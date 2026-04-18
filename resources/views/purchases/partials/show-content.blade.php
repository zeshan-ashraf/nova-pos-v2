{{-- Shared purchase details content: used by purchases.show page and by ledger modal (purchases/{id}/content). --}}
@php
    $in_modal = $in_modal ?? false;

    // Optional vars for the extended landed-cost flow (only populated on non-modal `purchases.show`).
    $purchaseExpenses = $purchaseExpenses ?? collect();
    $expenseCategories = $expenseCategories ?? collect();
    $allocationLocked = $allocationLocked ?? false;
    $isInternalPurchase = $isInternalPurchase ?? false;
@endphp
<div class="container-fluid purchase-detail-content">
    <style>
        .purchase-detail-content .themed-card {
            border: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .purchase-detail-content .themed-card .card-header {
            border-bottom: 0;
            padding: 0.9rem 1.25rem;
        }
        .purchase-detail-content .purchase-expenses-card .card-header,
        .purchase-detail-content .landed-cost-card .card-header {
            color: #fff;
        }
        .purchase-detail-content .landed-cost-card .card-header {
            background-color: #E08DB4;
        }
        .purchase-detail-content .section-subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 2px;
        }
        .purchase-detail-content .themed-table thead {
            background-color: #f8fafc;
        }
        .purchase-detail-content .themed-table thead th {
            color: #334155;
            letter-spacing: 0.03em;
            font-weight: 600;
            border-top: 0;
        }
        .purchase-detail-content .themed-table tbody tr:hover {
            background-color: #f8fbff;
        }
    </style>
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="header-title">
                        <h4 class="card-title">Purchase Details</h4>
                    </div>
                    @if($in_modal)
                    <div>
                        <a href="{{ route('purchases.show', $purchase->id) }}" target="_blank" class="btn btn-outline-primary btn-sm mr-2" id="purchasePrintInvoiceBtn">Print purchase invoice</a>
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    </div>
                    @endif
                </div>

                <div class="card-body">
                    <!-- begin: Show Data -->
                    <div class="row align-items-center">
                        <div class="form-group col-md-12">
                            <label>Supplier Name</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->supplier->shopname ?? $purchase->supplier->name }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Supplier Email</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->supplier->email ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Supplier Phone</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->supplier->phone ?? 'N/A' }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Purchase Date</label>
                            <input type="text" class="form-control bg-white" value="{{ $purchase->purchase_date }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Purchase Number</label>
                            <input class="form-control bg-white" value="{{ $purchase->purchase_no }}" readonly/>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Payment Status</label>
                            <input class="form-control bg-white" value="{{ $purchase->payment_status }}" readonly />
                        </div>
                        <div class="form-group col-md-6">
                            <label>Paid Amount</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($purchase->pay ?? 0, 2) }}" readonly>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Due Amount</label>
                            <input type="text" class="form-control bg-white" value="{{ number_format($purchase->due ?? 0, 2) }}" readonly>
                        </div>
                        @if ($purchase->payment_status === 'bank')
                            <div class="form-group col-md-12">
                                <label>Bank Information</label>
                                @if ($purchase->shop && $purchase->shop->banks->isNotEmpty())
                                    <div class="d-flex flex-wrap">
                                        @foreach ($purchase->shop->banks as $bank)
                                            <span class="badge badge-primary mr-2 mb-2">{{ $bank->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No bank information available for this shop.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                    <!-- end: Show Data -->

                    @if (!$in_modal && $purchase->purchase_status == 'pending')
                        @php
                            $canReceive = $isInternalPurchase || (($purchase->landed_cost_status ?? 'pending') === 'approved');
                        @endphp
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="d-flex align-items-center list-action">
                                    <form id="completePurchaseForm" action="{{ route('purchases.updateStatus') }}" method="POST" style="margin-bottom: 5px">
                                        @method('put')
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $purchase->id }}">
                                        <button
                                            type="submit"
                                            id="completePurchaseBtn"
                                            class="btn btn-success mr-2 border-none d-inline-flex align-items-center {{ $canReceive ? '' : 'opacity-50' }}"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            title=""
                                            data-original-title="{{ $canReceive ? 'Complete' : 'Approve landed cost first' }}"
                                            {{ $canReceive ? '' : 'disabled' }}
                                        >
                                            <span class="js-complete-purchase-label">Complete Purchase</span>
                                            <span class="js-complete-purchase-spinner spinner-border spinner-border-sm ml-2 d-none" role="status" aria-hidden="true"></span>
                                        </button>

                                        <a class="btn btn-danger mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Cancel" href="{{ route('purchases.pending') }}">Cancel</a>
                                    </form>
                                </div>
                                @if (!$isInternalPurchase && !$canReceive)
                                    <div class="text-warning mt-2" style="font-size: 14px;">
                                        Approve landed cost first before receiving this purchase.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>Product Name</th>
                            <th>Product Code</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($purchaseDetails as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->product->product_name ?? 'N/A' }}</td>
                            <td>{{ $item->product->product_code ?? 'N/A' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unitcost, 2) }}</td>
                            <td>{{ number_format($item->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if (!$in_modal && !$isInternalPurchase)
            <div class="col-lg-12 mt-3">
                <div class="card themed-card purchase-expenses-card">
                    <div class="card-header bg-primary">
                        <h4 class="card-title mb-0 text-white">Purchase Expenses <small>(Track and adjust additional costs for this purchase)</small></h4>
                    </div>
                    <div class="card-body">
                        @php
                            $canEditExpenses = !$allocationLocked && ($purchase->purchase_status ?? '') === 'pending' && (($purchase->landed_cost_status ?? 'pending') !== 'approved');
                            $purchaseExpensesTotal = $purchaseExpenses->sum(fn ($a) => (float) ($a->activity_cost ?? 0));
                        @endphp

                        @if ($purchaseExpenses->isEmpty())
                            <div class="text-muted mb-3">No purchase expenses added yet.</div>
                        @endif

                        @if ($canEditExpenses)
                            <form
                                id="addPurchaseExpenseForm"
                                method="POST"
                                action="{{ route('purchases.expenses.store', $purchase->id) }}"
                                class="mt-2"
                            >
                                @csrf
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Expense</label>
                                            <select class="form-control" name="expense_id" required>
                                                <option value="">Select expense</option>
                                                @foreach ($expenseCategories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->expense_title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Amount</label>
                                            <input type="number" step="0.01" min="0" class="form-control" name="activity_cost" required value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Date</label>
                                            <input type="date" class="form-control" name="date" required value="{{ $purchase->purchase_date }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Description (optional)</label>
                                            <input type="text" class="form-control" name="description" value="">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success mt-2 d-inline-flex align-items-center" id="addPurchaseExpenseBtn">
                                    <span class="js-add-expense-label">Add Expense</span>
                                    <span class="js-add-expense-spinner spinner-border spinner-border-sm ml-2 d-none" role="status" aria-hidden="true"></span>
                                </button>
                            </form>
                        @endif

                        <div class="table-responsive rounded mb-3">
                            <table class="table mb-0 themed-table">
                                <thead class="text-uppercase">
                                    <tr class="ligth ligth-data">
                                        <th>No.</th>
                                        <th>Expense</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="ligth-body">
                                    @foreach ($purchaseExpenses as $expenseActivity)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                {{ $expenseActivity->expense->expense_title ?? 'N/A' }}
                                            </td>
                                            <td>{{ number_format($expenseActivity->activity_cost ?? 0, 2) }}</td>
                                            <td>{{ $expenseActivity->date }}</td>
                                            <td>{{ $expenseActivity->description ?? '-' }}</td>
                                            <td class="text-center align-middle">
                                                @if ($canEditExpenses)
                                                    <div class="d-flex flex-wrap align-items-center justify-content-center" style="gap: 8px;">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-primary js-edit-expense-btn"
                                                            data-toggle="modal"
                                                            data-target="#editPurchaseExpenseModal"
                                                            data-action="{{ route('purchases.expenses.update', [$purchase->id, $expenseActivity->id]) }}"
                                                            data-expense-id="{{ $expenseActivity->expense_id }}"
                                                            data-amount="{{ number_format((float) ($expenseActivity->activity_cost ?? 0), 2, '.', '') }}"
                                                            data-date="{{ $expenseActivity->date }}"
                                                            data-description="{{ e($expenseActivity->description ?? '') }}"
                                                        >
                                                            Update
                                                        </button>

                                                        <form method="POST" action="{{ route('purchases.expenses.delete', [$purchase->id, $expenseActivity->id]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button
                                                                type="button"
                                                                class="btn btn-sm btn-danger"
                                                                data-toggle="modal"
                                                                data-target="#deletePurchaseExpenseModal"
                                                                data-action="{{ route('purchases.expenses.delete', [$purchase->id, $expenseActivity->id]) }}"
                                                            >
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <span class="badge badge-secondary">Locked</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="ligth-body">
                                        <td colspan="2" class="text-right font-weight-bold text-uppercase">Total</td>
                                        <td class="font-weight-bold">{{ number_format($purchaseExpensesTotal, 2) }}</td>
                                        <td colspan="3"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        @if ($canEditExpenses)
                            <div class="modal fade" id="editPurchaseExpenseModal" tabindex="-1" role="dialog" aria-labelledby="editPurchaseExpenseModalLabel" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <form method="POST" id="editPurchaseExpenseForm">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header bg-primary">
                                                <h5 class="modal-title text-white" id="editPurchaseExpenseModalLabel">Update Expense</h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <label for="edit_expense_id">Expense</label>
                                                    <select class="form-control" id="edit_expense_id" name="expense_id" required>
                                                        @foreach ($expenseCategories as $category)
                                                            <option value="{{ $category->id }}">{{ $category->expense_title }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label for="edit_activity_cost">Amount</label>
                                                    <input type="number" step="0.01" min="0" class="form-control" id="edit_activity_cost" name="activity_cost" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="edit_date">Date</label>
                                                    <input type="date" class="form-control" id="edit_date" name="date" required>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label for="edit_description">Description (optional)</label>
                                                    <input type="text" class="form-control" id="edit_description" name="description">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Update Expense</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="deletePurchaseExpenseModal" tabindex="-1" role="dialog" aria-labelledby="deletePurchaseExpenseModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <form method="POST" id="deletePurchaseExpenseForm">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-header bg-danger">
                                                <h5 class="modal-title text-white" id="deletePurchaseExpenseModalLabel">Confirm Delete</h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                Delete this expense?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-12 mt-3" style="margin-bottom: 30px;">
                <div class="card themed-card landed-cost-card">
                    <div class="card-header">
                        <h4 class="card-title mb-0 text-white">Landed Cost Review <small>(Review allocation before approving landed unit cost)</small></h4>
                    </div>
                    <div class="card-body">
                        @php
                            $purchaseDetailsForTable = $purchaseDetails ?? collect();
                            $canEditLandedCost = !$allocationLocked
                                && ($purchase->purchase_status ?? '') === 'pending'
                                && (($purchase->landed_cost_status ?? 'pending') !== 'approved');

                            $totalExpense = $purchaseExpenses->sum(fn ($a) => (float) ($a->activity_cost ?? 0));
                            $canApprove = $canEditLandedCost;
                        @endphp

                        @if ($totalExpense <= 0)
                            <div class="text-muted mb-2">
                                No expenses added. You can approve without expense.
                            </div>
                        @endif

                        <form id="approveLandedCostForm" method="POST" action="{{ route('purchases.landed-cost.approve', $purchase->id) }}">
                            @csrf

                            <div class="table-responsive rounded mb-3">
                                <table class="table mb-0 themed-table" id="landedCostTable">
                                    <thead class="text-uppercase">
                                        <tr class="ligth ligth-data">
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Unit Cost</th>
                                            <th>Allocated Expense</th>
                                            <th>Landed Unit Cost</th>
                                            <th>Landed Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="ligth-body">
                                        @foreach ($purchaseDetailsForTable as $detail)
                                            @php
                                                $qty = (int) ($detail->quantity ?? 0);
                                                $unitCost = (float) ($detail->unitcost ?? 0);
                                                $storedLandedUnitCost = $detail->landed_unit_cost;
                                                $landedUnitCost = ($storedLandedUnitCost !== null && (float) $storedLandedUnitCost > 0)
                                                    ? (float) $storedLandedUnitCost
                                                    : $unitCost;
                                                $storedAllocatedExpense = (float) ($detail->allocated_expense ?? 0);
                                                $storedLandedTotal = (float) ($detail->landed_total ?? 0);
                                                $landedTotal = $storedLandedTotal > 0 ? $storedLandedTotal : ($landedUnitCost * $qty);
                                                $allocatedExpense = $storedAllocatedExpense != 0
                                                    ? $storedAllocatedExpense
                                                    : ($landedTotal - ($qty * $unitCost));
                                            @endphp

                                            <tr>
                                                <td>
                                                    <div>{{ $detail->product->product_name ?? 'N/A' }}</div>
                                                    <div class="text-muted small">
                                                        {{ $detail->product->product_code ?? '' }}
                                                    </div>
                                                    <input type="hidden" name="purchase_detail_ids[]" value="{{ $detail->id }}">
                                                </td>
                                                <td>{{ $qty }}</td>
                                                <td>{{ number_format($unitCost, 4) }}</td>
                                                <td>
                                                    <span
                                                        class="allocated-expense"
                                                        data-detail-id="{{ $detail->id }}"
                                                    >{{ number_format($allocatedExpense, 4) }}</span>
                                                </td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="0.0001"
                                                        min="0"
                                                        class="form-control landed-unit-cost-input"
                                                        name="landed_unit_costs[{{ $detail->id }}]"
                                                        value="{{ number_format($landedUnitCost, 4, '.', '') }}"
                                                        data-qty="{{ $qty }}"
                                                        data-unitcost="{{ number_format($unitCost, 6, '.', '') }}"
                                                        data-lctarget="#row-{{ $detail->id }}"
                                                        {{ $canEditLandedCost ? '' : 'readonly' }}
                                                    />
                                                </td>
                                                <td>
                                                    <span class="landed-total">
                                                        {{ number_format($landedTotal, 4) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($canApprove)
                                <button type="submit" id="approveLandedCostBtn" class="btn btn-primary d-inline-flex align-items-center">
                                    <span class="js-approve-landed-label">{{ $totalExpense <= 0 ? 'Approve without expense' : 'Approve Landed Cost' }}</span>
                                    <span class="js-approve-landed-spinner spinner-border spinner-border-sm ml-2 d-none" role="status" aria-hidden="true"></span>
                                </button>
                            @else
                                <div class="text-muted">
                                    {{
                                        'Landed cost editing disabled or already approved.'
                                    }}
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <script>
                (function() {
                    var addExpenseForm = document.getElementById('addPurchaseExpenseForm');
                    if (addExpenseForm) {
                        addExpenseForm.addEventListener('submit', function() {
                            var btn = document.getElementById('addPurchaseExpenseBtn');
                            if (!btn || btn.disabled) return;
                            btn.disabled = true;
                            var label = btn.querySelector('.js-add-expense-label');
                            var spin = btn.querySelector('.js-add-expense-spinner');
                            if (label) label.textContent = 'Adding…';
                            if (spin) spin.classList.remove('d-none');
                        });
                    }

                    var completePurchaseForm = document.getElementById('completePurchaseForm');
                    var completePurchaseBtn = document.getElementById('completePurchaseBtn');
                    if (completePurchaseForm && completePurchaseBtn) {
                        completePurchaseForm.addEventListener('submit', function(e) {
                            if (completePurchaseForm.getAttribute('data-submitting') === '1') {
                                e.preventDefault();
                                return;
                            }
                            completePurchaseForm.setAttribute('data-submitting', '1');
                            completePurchaseBtn.disabled = true;
                            var label = completePurchaseBtn.querySelector('.js-complete-purchase-label');
                            var spin = completePurchaseBtn.querySelector('.js-complete-purchase-spinner');
                            if (label) label.textContent = 'Completing…';
                            if (spin) spin.classList.remove('d-none');
                        });
                    }

                    var approveLandedCostForm = document.getElementById('approveLandedCostForm');
                    var approveLandedCostBtn = document.getElementById('approveLandedCostBtn');
                    if (approveLandedCostForm && approveLandedCostBtn) {
                        approveLandedCostForm.addEventListener('submit', function(e) {
                            if (approveLandedCostForm.getAttribute('data-submitting') === '1') {
                                e.preventDefault();
                                return;
                            }
                            approveLandedCostForm.setAttribute('data-submitting', '1');
                            approveLandedCostBtn.disabled = true;
                            var label = approveLandedCostBtn.querySelector('.js-approve-landed-label');
                            var spin = approveLandedCostBtn.querySelector('.js-approve-landed-spinner');
                            if (label) label.textContent = 'Approving…';
                            if (spin) spin.classList.remove('d-none');
                        });
                    }

                    document.addEventListener('click', function(e) {
                        const trigger = e.target.closest('.js-edit-expense-btn');
                        if (!trigger) return;

                        const editForm = document.getElementById('editPurchaseExpenseForm');
                        if (!editForm) return;

                        editForm.setAttribute('action', trigger.getAttribute('data-action') || '');
                        document.getElementById('edit_expense_id').value = trigger.getAttribute('data-expense-id') || '';
                        document.getElementById('edit_activity_cost').value = trigger.getAttribute('data-amount') || '0';
                        document.getElementById('edit_date').value = trigger.getAttribute('data-date') || '';
                        document.getElementById('edit_description').value = trigger.getAttribute('data-description') || '';
                    });

                    document.addEventListener('click', function(e) {
                        const deleteTrigger = e.target.closest('[data-target="#deletePurchaseExpenseModal"]');
                        if (!deleteTrigger) return;

                        const deleteForm = document.getElementById('deletePurchaseExpenseForm');
                        if (!deleteForm) return;

                        deleteForm.setAttribute('action', deleteTrigger.getAttribute('data-action') || '');
                    });

                    function updateRow(input) {
                        const qty = parseFloat(input.dataset.qty) || 0;
                        const unitCost = parseFloat(input.dataset.unitcost) || 0;
                        const landedUnitCost = parseFloat(input.value) || 0;

                        const landedTotal = landedUnitCost * qty;
                        const allocatedExpense = landedTotal - (qty * unitCost);

                        const row = input.closest('tr');
                        const landedTotalEl = row.querySelector('.landed-total');
                        const allocatedEl = row.querySelector('.allocated-expense');

                        if (landedTotalEl) landedTotalEl.textContent = landedTotal.toFixed(4);
                        if (allocatedEl) allocatedEl.textContent = allocatedExpense.toFixed(4);
                    }

                    document.addEventListener('input', function(e) {
                        if (e.target && e.target.classList && e.target.classList.contains('landed-unit-cost-input')) {
                            updateRow(e.target);
                        }
                    });
                })();
            </script>
        @endif
    </div>
</div>
