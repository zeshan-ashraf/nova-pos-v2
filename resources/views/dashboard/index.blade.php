@extends('dashboard.body.main')

@section('specificpagestyles')
@include('dashboard.partials.erp-styles')
@endsection

@section('container')
@include('dashboard.partials.erp-body', [
    'dashboard' => $dashboard ?? [],
    'dateRange' => $dateRange ?? [],
    'revenue_vs_cost' => $revenue_vs_cost ?? [],
])
@endsection

@section('specificpagescripts')
@include('dashboard.partials.erp-scripts', ['currency' => ($dashboard['currency'] ?? '')])
@endsection
