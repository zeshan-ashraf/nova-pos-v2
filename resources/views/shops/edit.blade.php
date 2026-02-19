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
                        <h4 class="card-title">Edit Shop</h4>
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('shops.update', $shop->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('put')
                        <div class="form-group row align-items-center">
                            <div class="col-md-12">
                                <div class="profile-img-edit">
                                    <div class="crm-profile-img-edit">
                                        <img class="crm-profile-pic rounded-circle avatar-100" id="image-preview" src="{{ $shop->logo_url }}" alt="shop-logo">
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
                            @if($shop->logo)
                            <div class="form-group col-lg-6 d-flex align-items-end">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="remove_logo" name="remove_logo" value="1" {{ old('remove_logo') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="remove_logo">Remove current logo</label>
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="row align-items-center">
                            <div class="form-group col-md-6">
                                <label for="name">Shop Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $shop->name) }}" required>
                                @error('name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="owner_name">Owner Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('owner_name') is-invalid @enderror" id="owner_name" name="owner_name" value="{{ old('owner_name', $shop->owner_name) }}" required>
                                @error('owner_name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="phone">Phone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $shop->phone) }}" required>
                                @error('phone')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="status">Status <span class="text-danger">*</span></label>
                                <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                                    <option value="1" @if(old('status', $shop->status ? '1' : '0') === '1')selected="selected"@endif>Enabled</option>
                                    <option value="0" @if(old('status', $shop->status ? '1' : '0') === '0')selected="selected"@endif>Disabled</option>
                                </select>
                                @error('status')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label for="address">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="3" required>{{ old('address', $shop->address) }}</textarea>
                                @error('address')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label for="invoice_policy">Invoice Policy</label>
                                <textarea class="form-control @error('invoice_policy') is-invalid @enderror" id="invoice_policy" name="invoice_policy" rows="4">{{ old('invoice_policy', $shop->invoice_policy) }}</textarea>
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
                                @php
                                    $selectedBankIds = old('bank_ids', $shop->banks->pluck('id')->toArray());
                                @endphp
                                <select name="bank_ids[]" id="bank_ids" class="form-control bank-select @error('bank_ids') is-invalid @enderror" multiple>
                                    @foreach ($banks as $bank)
                                        <option value="{{ $bank->id }}" data-name="{{ $bank->name }}" {{ in_array($bank->id, $selectedBankIds) ? 'selected' : '' }}>{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Selected banks appear as badges; each selected bank must have an opening balance (≥ 0).</small>
                            </div>
                            <div class="form-group col-md-12" id="bank_opening_balances_wrap" style="display: none;">
                                <label>Opening balance per bank <span class="text-danger">*</span></label>
                                @error('opening_balance')
                                <div class="alert alert-danger">
                                    {{ $message }}
                                </div>
                                @enderror
                                <div id="bank_opening_balances_list"></div>
                                <small class="form-text text-muted">Enter 0 or positive amount. Corrections are applied via adjustment entries (ledger-safe).</small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="is_parent">Is Mother Shop? <span class="text-danger">*</span></label>
                                <select class="form-control @error('is_parent') is-invalid @enderror" id="is_parent" name="is_parent" required>
                                    <option value="1" @if(old('is_parent', $shop->is_parent ? '1' : '0') === '1')selected="selected"@endif>Yes - Mother Shop</option>
                                    <option value="0" @if(old('is_parent', $shop->is_parent ? '1' : '0') === '0')selected="selected"@endif>No - Child Shop</option>
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
                                        <option value="{{ $rootShop->id }}" @if(old('parent_shop_id', $shop->parent_shop_id) == $rootShop->id)selected="selected"@endif>{{ $rootShop->name }}</option>
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
                            <button type="submit" class="btn btn-primary mr-2">Update</button>
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
    var banksWithOpening = @json($banksWithOpening ?? []);

    function getOpeningBalanceForBank(bankId) {
        var oldOpening = @json(old('opening_balance', []));
        if (oldOpening && oldOpening[bankId] !== undefined) return oldOpening[bankId];
        var existing = banksWithOpening.find(function(b) { return b.bank_id == bankId; });
        return existing ? (existing.opening_balance || '0') : '0';
    }

    function renderOpeningBalances() {
        var selected = $('#bank_ids').val() || [];
        var container = $('#bank_opening_balances_list');
        container.empty();
        if (selected.length === 0) {
            $('#bank_opening_balances_wrap').hide();
            return;
        }
        $('#bank_opening_balances_wrap').show();
        selected.forEach(function(bankId) {
            var bank = banksData[bankId];
            var name = bank ? bank.name : ('Bank #' + bankId);
            var val = getOpeningBalanceForBank(bankId);
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


