@extends('dashboard.body.main')

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
                                        <img class="crm-profile-pic rounded-circle avatar-100" id="image-preview" src="{{ $shop->logo ? asset('storage/shops/'.$shop->logo) : asset('assets/images/user/1.png') }}" alt="shop-logo">
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
                                <label class="mb-3">Banks <small class="text-muted">(optional, click to select multiple)</small></label>
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
                                <div class="row" id="bank-selection">
                                    @php
                                        $selectedBanks = collect(old('bank_ids', $shop->banks->pluck('id')->all()));
                                    @endphp
                                    @foreach ($banks as $bank)
                                        <div class="col-md-3 col-sm-4 col-6 mb-3">
                                            <div class="bank-card card h-100 cursor-pointer @if($selectedBanks->contains($bank->id))border-primary bg-light @endif" 
                                                 onclick="toggleBank({{ $bank->id }})" 
                                                 style="transition: all 0.3s ease; cursor: pointer;">
                                                <div class="card-body text-center p-3">
                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="bank_ids[]" 
                                                               value="{{ $bank->id }}" 
                                                               id="bank_{{ $bank->id }}"
                                                               @if($selectedBanks->contains($bank->id))checked @endif
                                                               onchange="updateBankCard({{ $bank->id }})">
                                                    </div>
                                                    <div class="bank-icon mb-2" style="font-size: 2rem;">🏦</div>
                                                    <h6 class="card-title mb-0" style="font-size: 0.85rem; line-height: 1.2;">{{ $bank->name }}</h6>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="is_parent">Is Root Shop? <span class="text-danger">*</span></label>
                                <select class="form-control @error('is_parent') is-invalid @enderror" id="is_parent" name="is_parent" required>
                                    <option value="1" @if(old('is_parent', $shop->is_parent ? '1' : '0') === '1')selected="selected"@endif>Yes - Root Shop</option>
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

<style>
    .bank-card {
        border: 2px solid #e0e0e0;
    }
    .bank-card:hover {
        border-color: #007bff;
        box-shadow: 0 2px 8px rgba(0,123,255,0.2);
        transform: translateY(-2px);
    }
    .bank-card.border-primary {
        border-color: #007bff !important;
        background-color: #e7f3ff !important;
    }
    .bank-card input[type="checkbox"] {
        cursor: pointer;
    }
</style>

<script>
    function toggleBank(bankId) {
        const checkbox = document.getElementById('bank_' + bankId);
        checkbox.checked = !checkbox.checked;
        updateBankCard(bankId);
    }

    function updateBankCard(bankId) {
        const checkbox = document.getElementById('bank_' + bankId);
        const card = checkbox.closest('.bank-card');
        
        if (checkbox.checked) {
            card.classList.add('border-primary', 'bg-light');
        } else {
            card.classList.remove('border-primary', 'bg-light');
        }
    }
</script>
@endsection


