@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="header-title">
                        <h4 class="card-title">Shop Details</h4>
                        <p class="mb-0 text-muted">Review shop info and linked banks.</p>
                    </div>
                    <div class="d-flex">
                        <a class="btn btn-secondary mr-2" href="{{ route('shops.index') }}">Back</a>
                        @can('shop.update')
                            <a class="btn btn-primary" href="{{ route('shops.edit', $shop->id) }}">Edit Shop</a>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            <img class="rounded-circle avatar-120" src="{{ $shop->logo ? asset('storage/shops/'.$shop->logo) : asset('assets/images/user/1.png') }}" alt="{{ $shop->name }}">
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label>Shop Name</label>
                                    <input type="text" class="form-control bg-white" value="{{ $shop->name }}" readonly>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Owner Name</label>
                                    <input type="text" class="form-control bg-white" value="{{ $shop->owner_name }}" readonly>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Phone</label>
                                    <input type="text" class="form-control bg-white" value="{{ $shop->phone }}" readonly>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Status</label>
                                    <input type="text" class="form-control bg-white" value="{{ $shop->status ? 'Enabled' : 'Disabled' }}" readonly>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Type</label>
                                    <input type="text" class="form-control bg-white" value="{{ $shop->is_parent ? 'Root Shop' : 'Child Shop' }}" readonly>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Parent Shop</label>
                                    <input type="text" class="form-control bg-white" value="{{ $shop->parent?->name ?? '—' }}" readonly>
                                </div>
                                <div class="form-group col-md-12">
                                    <label>Address</label>
                                    <textarea class="form-control bg-white" rows="2" readonly>{{ $shop->address }}</textarea>
                                </div>
                                @if($shop->invoice_policy)
                                <div class="form-group col-md-12">
                                    <label>Invoice Policy</label>
                                    <textarea class="form-control bg-white" rows="4" readonly>{{ $shop->invoice_policy }}</textarea>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <label class="mb-2">Linked Banks</label>
                            @if($shop->banks->isNotEmpty())
                                <div class="d-flex flex-wrap">
                                    @foreach($shop->banks as $bank)
                                        <span class="badge badge-primary mr-2 mb-2">{{ $bank->name }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted mb-0">No banks linked to this shop.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

