{{-- Reusable modal for Add/Edit Expense Category. Include on expense-categories index and expenses create/edit. --}}
<div class="modal fade" id="expenseCategoryModal" tabindex="-1" role="dialog" aria-labelledby="expenseCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expenseCategoryModalLabel">Add Expense Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="expense-category-modal-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="expense-category-modal-success" class="alert alert-success d-none" role="alert"></div>
                <form id="expense-category-form">
                    @csrf
                    <input type="hidden" id="expense_category_id" name="id" value="">
                    <div class="form-group">
                        <label for="expense_category_title">Category Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="expense_category_title" name="expense_title" required placeholder="e.g. Rent, Electricity" maxlength="255">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="expense-category-modal-submit">Save</button>
            </div>
        </div>
    </div>
</div>
