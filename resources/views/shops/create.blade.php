@extends('dashboard.body.main')

@section('specificpagestyles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Add Shop</h4>
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('shops.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group row align-items-center">
                            <div class="col-md-12">
                                <div class="profile-img-edit">
                                    <div class="crm-profile-img-edit">
                                        <img class="crm-profile-pic rounded-circle avatar-100" id="image-preview" src="{{ asset('assets/images/user/1.png') }}" alt="shop-logo">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="input-group mb-4 col-lg-6">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input @error('logo') is-invalid @enderror" id="image" name="logo" accept="image/*" onchange="previewImage();">
                                    <label class="custom-file-label" for="logo">Choose file</label>
                                </div>
                                @error('logo')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>

                        <div class="row align-items-center">
                            <div class="form-group col-md-6">
                                <label for="name">Shop Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="owner_name">Owner Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required>
                                @error('owner_name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="phone">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" required>
                                @error('phone')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="status">Status <span class="text-danger">*</span></label>
                                <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                                    <option value="1" @if(old('status', '1') === '1')selected="selected"@endif>Enabled</option>
                                    <option value="0" @if(old('status') === '0')selected="selected"@endif>Disabled</option>
                                </select>
                                @error('status')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label for="address">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3" required>{{ old('address') }}</textarea>
                                @error('address')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label for="invoice_policy">Invoice Policy</label>
                                <textarea class="form-control @error('invoice_policy') is-invalid @enderror" id="invoice_policy" name="invoice_policy" rows="4">{{ old('invoice_policy') }}</textarea>
                                @error('invoice_policy')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label for="bank_ids">Banks <small class="text-muted">(optional)</small></label>
                                @error('bank_ids')
                                <div class="alert alert-danger">
                                    {{ $message }}
                                </div>
                                @enderror
                                @error('bank_ids.*')
                                <div class="alert alert-danger">
                                    {{ $message }}
                                </div>
                                @enderror
                                <select name="bank_ids[]" id="bank_ids" class="form-control bank-select @error('bank_ids') is-invalid @enderror" multiple>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank->id }}" data-name="{{ $bank->name }}" {{ in_array($bank->id, old('bank_ids', [])) ? 'selected' : '' }}>{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Selected banks appear as badges; click × on a badge to remove. Each selected bank must have an opening balance (≥ 0).</small>
                            </div>
                            <div class="form-group col-md-12" id="bank_opening_balances_wrap" style="display: none;">
                                <label>Opening balance per bank <span class="text-danger">*</span></label>
                                @error('opening_balance')
                                <div class="alert alert-danger">
                                    {{ $message }}
                                </div>
                                @enderror
                                <div id="bank_opening_balances_list"></div>
                                <small class="form-text text-muted">Enter 0 or positive amount for each selected bank. Zero = no ledger entry.</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="is_parent">Is Mother Shop? <span class="text-danger">*</span></label>
                                <select class="form-control @error('is_parent') is-invalid @enderror" id="is_parent" name="is_parent" required>
                                    <option value="1" @if(old('is_parent', '1') === '1')selected="selected"@endif>Yes - Mother Shop</option>
                                    <option value="0" @if(old('is_parent') === '0')selected="selected"@endif>No - Child Shop</option>
                                </select>
                                @error('is_parent')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6" id="parent-shop-wrapper">
                                <label for="parent_shop_id">Parent Shop <span class="text-danger">*</span></label>
                                <select class="form-control @error('parent_shop_id') is-invalid @enderror" id="parent_shop_id" name="parent_shop_id">
                                    <option value="">-- Select Parent Shop --</option>
                                    @foreach ($rootShops as $rootShop)
                                        <option value="{{ $rootShop->id }}" @if(old('parent_shop_id') == $rootShop->id)selected="selected"@endif>{{ $rootShop->name }}</option>
                                    @endforeach
                                </select>
                                @error('parent_shop_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary mr-2">Save</button>
                            <a class="btn bg-danger" href="{{ route('shops.index') }}">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('components.preview-img-form')
@include('shops.partials.parent-toggle-script')
@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    var banksData = @json($banks->keyBy('id'));

    function renderOpeningBalances() {
        var selected = $('#bank_ids').val() || [];
        var container = $('#bank_opening_balances_list');
        container.empty();
        if (selected.length === 0) {
            $('#bank_opening_balances_wrap').hide();
            return;
        }
        $('#bank_opening_balances_wrap').show();
        var oldOpening = @json(old('opening_balance', []));
        selected.forEach(function(bankId) {
            var bank = banksData[bankId];
            var name = bank ? bank.name : ('Bank #' + bankId);
            var val = (oldOpening && oldOpening[bankId] !== undefined) ? oldOpening[bankId] : '0';
            var row = $('<div class="row align-items-center mb-2"></div>');
            row.append('<div class="col-md-5"><label class="col-form-label">' + name + '</label></div>');
            row.append('<div class="col-md-4"><input type="number" step="0.01" min="0" class="form-control" name="opening_balance[' + bankId + ']" value="' + val + '" required></div>');
            container.append(row);
        });
    }

    $('#bank_ids').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select Banks',
        allowClear: true,
        width: '100%'
    }).on('change', function() {
        renderOpeningBalances();
    });

    renderOpeningBalances();
});
</script>
@endsection


