<!DOCTYPE html>
<html lang="zxx">
<head>
    <title>POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">

    <!-- External CSS libraries -->
    <link type="text/css" rel="stylesheet" href="{{ asset('assets/invoice/css/bootstrap.min.css') }}">

    <!-- Google fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Custom Stylesheet -->
    <link type="text/css" rel="stylesheet" href="{{ asset('assets/invoice/css/style.css') }}">
</head>
<body>
    <div class="invoice-16 invoice-content">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="invoice-inner-9" id="invoice_wrapper">
                        <div class="invoice-top">
                            <div class="row">
                                <div class="col-lg-6 col-sm-6">
                                    <div class="logo">
                                        <img class="logo" src="{{ asset('assets/images/logo.png') }}" alt="logo">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-sm-6">
                                    <div class="invoice">
                                        <h1>#<span>{{ $order->invoice_no }}</span></h1>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="invoice-info">
                            <div class="row">
                                <div class="col-sm-6 mb-50">
                                    <div class="invoice-number">
                                        <h4 class="inv-title-1">Invoice date:</h4>
                                        <p class="invo-addr-1">
                                            {{ $order->order_date }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-sm-6 text-end mb-50">
                                    <h4 class="inv-title-1">@php
                                        $shop = $order->shop ?? auth()->user()->shop ?? null;
                                    @endphp</h4>
                                    <p class="inv-from-1">{{ $shop->phone ?? 'POS' }}</p>
                                    <p class="inv-from-2">{{ $shop->owner_name ?? auth()->user()->name }}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6 mb-50">
                                    <h4 class="inv-title-1">Customer</h4>
                                    <p class="inv-from-1">{{ $order->customer->name }}</p>
                                    <p class="inv-from-1">{{ $order->customer->email }}</p>
                                    <p class="inv-from-1">{{ $order->customer->phone }}</p>
                                    <p class="inv-from-2">{{ $order->customer->address }}</p>
                                </div>
                                <div class="col-sm-6 text-end mb-50">
                                    <h4 class="inv-title-1">Details</h4>
                                    <p class="inv-from-1">Payment Status: {{ $order->payment_status }}</p>
                                    <p class="inv-from-1">Total Pay: {{ $order->pay }}</p>
                                    <p class="inv-from-1">Due: {{ $order->due }}</p>
                                </div>
                            </div>
                            @if ($order->payment_status === 'Bank')
                            <div class="row">
                                <div class="col-sm-12 mb-30">
                                    <h4 class="inv-title-1">Bank Information</h4>
                                    @if($order->shop && $order->shop->banks->isNotEmpty())
                                        <p class="inv-from-1 mb-1">Shop: {{ $order->shop->name }}</p>
                                        <ul class="mb-0">
                                            @foreach($order->shop->banks as $bank)
                                                <li>{{ $bank->name }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="inv-from-1 text-muted mb-0">No bank information available for this shop.</p>
                                    @endif
                                </div>
                            </div>
                            @endif
                            @if($shop && $shop->invoice_policy)
                            <div class="row">
                                <div class="col-sm-12 mb-30">
                                    <h4 class="inv-title-1">Invoice Policy</h4>
                                    <p class="inv-from-1" style="white-space: pre-wrap;">{{ $shop->invoice_policy }}</p>
                                </div>
                            </div>
                            @endif
                        </div>
                        <div class="order-summary">
                            <div class="table-outer">
                                <table class="default-table invoice-table">
                                    <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($orderDetails as $item)
                                        <tr>
                                            <td>
                                                {{ $item->product->product_name }}
                                                @if($item->product->product_code)
                                                    <small class="text-muted">({{ $item->product->product_code }})</small>
                                                @endif
                                            </td>
                                            <td>{{ $item->unitcost }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>{{ $item->total }}</td>
                                        </tr>
                                        @endforeach
                                        <tr>
                                            <td><strong class="text-danger">Total</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td><strong class="text-danger">{{ $order->total }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        {{-- <div class="invoice-informeshon-footer">
                            <ul>
                                <li><a href="https://themeforest.net/user/themevessel/portfolio">www.themevessel.com</a></li>
                                <li><a href="mailto:sales@hotelempire.com">info@themevessel.com</a></li>
                                <li><a href="tel:+088-01737-133959">+088 01737 133959</a></li>
                            </ul>
                        </div> --}}
                    </div>

                    <div class="invoice-btn-section clearfix d-print-none">
                        <a href="javascript:window.print()" class="btn btn-lg btn-print">
                            Print Invoice
                        </a>
                        <a id="invoice_download_btn" class="btn btn-lg btn-download">
                            Download Invoice
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="{{ asset('assets/invoice/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/invoice/js/jspdf.min.js') }}"></script>
    <script src="{{ asset('assets/invoice/js/html2canvas.js') }}"></script>
    <script src="{{ asset('assets/invoice/js/app.js') }}"></script>
    
    @if(isset($shouldPrint) && $shouldPrint)
    <script>
        $(document).ready(function() {
            // Wait for page to fully render before triggering print
            setTimeout(function() {
                // Find and click the Print Invoice button programmatically
                const $printButton = $('.btn-print');
                if ($printButton.length > 0) {
                    $printButton[0].click();
                } else {
                    // Fallback: if button not found, trigger print directly
                    window.print();
                }
            }, 1000); // 1 second delay to ensure page is fully rendered
        });
    </script>
    @endif
</body>
</html>
