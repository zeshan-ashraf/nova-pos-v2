{{-- Add Product Button --}}
<button type="button" class="btn btn-primary btn-add-row ml-2" id="addProductBtn" data-toggle="modal" data-target="#addProductModal">
    <i class="ri-add-line"></i> Add Product
</button>

{{-- Add Product Modal --}}
<div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addProductForm">
                <div class="modal-body">
                    <div id="productFormErrors" class="alert alert-danger" style="display: none;"></div>
                    <div id="productFormSuccess" class="alert alert-success" style="display: none;"></div>
                    
                    <div class="form-group">
                        <label for="modal_product_name">Product Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_product_name" name="product_name" required>
                        <div class="invalid-feedback" id="error_product_name"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_product_code">Product Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_product_code" name="product_code" required>
                        <div class="invalid-feedback" id="error_product_code"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_category_id">Category <span class="text-danger">*</span></label>
                        <select class="form-control" id="modal_category_id" name="category_id" required>
                            <option value="">-- Loading Categories --</option>
                        </select>
                        <div class="invalid-feedback" id="error_category_id"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="modal_buying_price">Buying Price <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="modal_buying_price" name="buying_price" value="0" required>
                            <div class="invalid-feedback" id="error_buying_price"></div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="modal_selling_price">Selling Price <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="modal_selling_price" name="selling_price" value="0" required>
                            <div class="invalid-feedback" id="error_selling_price"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_product_store">Stock <span class="text-danger">*</span></label>
                        <input type="number" step="1" min="0" class="form-control" id="modal_product_store" name="product_store" value="0" required>
                        <div class="invalid-feedback" id="error_product_store"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveProductBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="saveProductSpinner" role="status" aria-hidden="true"></span>
                        <span id="saveProductBtnText">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- JavaScript for Add Product Modal --}}
<script>
(function($) {
    'use strict';
    
    // Function to load categories
    function loadCategories() {
        const $categorySelect = $('#modal_category_id');
        
        // Check if already loaded (has more than just the default option)
        if ($categorySelect.length === 0) {
            console.error('Category select element not found!');
            return;
        }
        
        if ($categorySelect.find('option').length > 1 && !$categorySelect.find('option:first').text().includes('Loading')) {
            // Already loaded
            return;
        }
        
        $categorySelect.html('<option value="">-- Loading Categories --</option>');
        
        try {
            var categoriesUrl = '{{ route("api.categories") }}';
            console.log('Loading categories from:', categoriesUrl);
        } catch(e) {
            // Fallback if route helper fails
            var categoriesUrl = '/api/categories';
            console.warn('Route helper failed, using fallback URL:', categoriesUrl);
        }
        
        $.ajax({
            url: categoriesUrl,
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(response) {
                console.log('Categories loaded:', response);
                if (response && response.success && response.categories && response.categories.length > 0) {
                    let options = '<option value="">-- Select Category --</option>';
                    response.categories.forEach(function(category) {
                        options += '<option value="' + category.id + '">' + category.name + '</option>';
                    });
                    $categorySelect.html(options);
                } else {
                    console.warn('No categories in response:', response);
                    $categorySelect.html('<option value="">-- No categories available --</option>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading categories:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    response: xhr.responseText,
                    url: categoriesUrl
                });
                var errorMsg = '-- Error loading categories --';
                if (xhr.status === 404) {
                    errorMsg = '-- Route not found (404) --';
                } else if (xhr.status === 403 || xhr.status === 401) {
                    errorMsg = '-- Access denied --';
                } else if (xhr.status === 500) {
                    errorMsg = '-- Server error --';
                }
                $categorySelect.html('<option value="">' + errorMsg + '</option>');
            }
        });
    }

    // Wait for document ready
    $(document).ready(function() {
        // Load categories when modal is shown - using shown.bs.modal to ensure modal is fully visible
        $(document).on('shown.bs.modal', '#addProductModal', function() {
            console.log('Modal shown, loading categories...');
            loadCategories();
        });
        
        // Also try loading when modal starts to show (backup)
        $(document).on('show.bs.modal', '#addProductModal', function() {
            console.log('Modal showing, will load categories...');
        });
        
        // Manual trigger button click handler (if needed)
        $('#addProductBtn').on('click', function() {
            // Small delay to ensure modal is in DOM
            setTimeout(function() {
                loadCategories();
            }, 100);
        });

        // Handle Save button click - simple AJAX submission
        $(document).on('click', '#saveProductBtn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Get form data
            const formData = {
                product_name: $('#modal_product_name').val(),
                product_code: $('#modal_product_code').val(),
                category_id: $('#modal_category_id').val(),
                buying_price: $('#modal_buying_price').val() || 0,
                selling_price: $('#modal_selling_price').val() || 0,
                product_store: $('#modal_product_store').val() || 0,
            };
            
            // Hide previous errors and success messages
            $('#productFormErrors').hide().html('');
            $('#productFormSuccess').hide();
            $('.invalid-feedback').hide();
            $('.form-control').removeClass('is-invalid');
            
            // Disable save button and show loading
            const $saveBtn = $('#saveProductBtn');
            const $saveBtnText = $('#saveProductBtnText');
            const $spinner = $('#saveProductSpinner');
            
            $saveBtn.prop('disabled', true);
            $saveBtnText.text('Saving...');
            $spinner.removeClass('d-none');
            
            // Submit via AJAX
            $.ajax({
            url: '{{ route("products.store") }}',
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#productFormSuccess').text(response.message).show();
                    
                    // Add new product to all Select2 product dropdowns
                    // Format: "product_code - product_name" or just "product_name" if no code
                    const productCode = response.product.product_code || '';
                    const displayText = productCode ? productCode + ' - ' + response.product.product_name : response.product.product_name;
                    const productId = response.product.id;
                    
                    // Store product data globally for use in select2:select event
                    if (typeof window.newProductData === 'undefined') {
                        window.newProductData = {};
                    }
                    // Use buying_price if available (for purchase), otherwise use selling_price (for invoice)
                    const productPrice = response.product.buying_price !== undefined ? response.product.buying_price : response.product.selling_price;
                    window.newProductData[productId] = {
                        id: productId,
                        text: displayText,
                        name: response.product.product_name,
                        price: productPrice,
                        buying_price: response.product.buying_price,
                        selling_price: response.product.selling_price,
                        stock: response.product.product_store,
                        code: productCode
                    };
                    
                    // Add to all product selects (both Select2 and regular selects)
                    $('.product-select').each(function() {
                        const $select = $(this);
                        
                        // Check if option already exists
                        if ($select.find('option[value="' + productId + '"]').length === 0) {
                            // Create new option
                            const newOption = new Option(displayText, productId, false, false);
                            $select.append(newOption);
                            
                            // If it's a Select2, trigger change
                            if ($select.data('select2')) {
                                $select.trigger('change');
                            }
                        }
                    });
                    
                    // Reset form fields
                    $('#modal_product_name').val('');
                    $('#modal_product_code').val('');
                    $('#modal_category_id').val('');
                    $('#modal_buying_price').val(0);
                    $('#modal_selling_price').val(0);
                    $('#modal_product_store').val(0);
                    
                    // Auto-close modal after 3 seconds
                    let countdown = 3;
                    const countdownInterval = setInterval(function() {
                        countdown--;
                        if (countdown > 0) {
                            $('#productFormSuccess').text(response.message + ' Closing in ' + countdown + ' seconds...');
                        } else {
                            clearInterval(countdownInterval);
                            // Close modal manually
                            var $modal = $('#addProductModal');
                            $modal.removeClass('show');
                            $modal.css('display', 'none');
                            $modal.attr('aria-hidden', 'true');
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                            $('body').css({'overflow': '', 'padding-right': ''});
                            // Reset form and messages after modal closes
                            setTimeout(function() {
                                $('#modal_product_name').val('');
                                $('#modal_product_code').val('');
                                $('#modal_category_id').val('');
                                $('#modal_buying_price').val(0);
                                $('#modal_selling_price').val(0);
                                $('#modal_product_store').val(0);
                                $('#productFormSuccess').hide();
                                $saveBtn.prop('disabled', false);
                                $saveBtnText.text('Save');
                                $spinner.addClass('d-none');
                            }, 300);
                        }
                    }, 1000);
                }
                
                return false;
            },
            error: function(xhr) {
                // Re-enable save button
                $saveBtn.prop('disabled', false);
                $saveBtnText.text('Save');
                $spinner.addClass('d-none');
                
                if (xhr.status === 422) {
                    // Validation errors
                    const errors = xhr.responseJSON.errors;
                    let errorHtml = '<ul class="mb-0">';
                    
                    $.each(errors, function(field, messages) {
                        const fieldId = 'modal_' + field;
                        const errorId = 'error_' + field;
                        
                        // Show field error
                        $('#' + fieldId).addClass('is-invalid');
                        $('#' + errorId).text(messages[0]).show();
                        
                        // Add to error list
                        $.each(messages, function(index, message) {
                            errorHtml += '<li>' + message + '</li>';
                        });
                    });
                    
                    errorHtml += '</ul>';
                    $('#productFormErrors').html(errorHtml).show();
                } else {
                    // Other errors
                    $('#productFormErrors').html('<p>An error occurred. Please try again.</p>').show();
                }
                
                return false;
            }
        });
        
            return false;
        });
        
        // Reset modal when closed
        $('#addProductModal').on('hidden.bs.modal', function() {
            // Reset form fields manually
            $('#modal_product_name').val('');
            $('#modal_product_code').val('');
            $('#modal_category_id').val('');
            $('#modal_buying_price').val(0);
            $('#modal_selling_price').val(0);
            $('#modal_product_store').val(0);
            $('#productFormErrors').hide().html('');
            $('#productFormSuccess').hide();
            $('.invalid-feedback').hide();
            $('.form-control').removeClass('is-invalid');
            $('#saveProductBtn').prop('disabled', false);
            $('#saveProductBtnText').text('Save');
            $('#saveProductSpinner').addClass('d-none');
        });
    }); // End document ready
})(jQuery);
</script>

