@extends('dashboard.body.main')

@section('container')
@include('purchases.partials.show-content', ['in_modal' => false])

@include('components.preview-img-form')
@endsection
