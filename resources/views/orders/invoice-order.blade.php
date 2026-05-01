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
                        @php
                            $userShop = auth()->user()->shop ?? null;
                            $logoUrl = $userShop?->logo_url ?? asset('assets/images/login/company-logo.png');
                            $shop = $order->shop ?? $userShop;
                        @endphp
                        <div class="invoice-top">
                            <div class="row">
                                <div class="col-lg-6 col-sm-6">
                                    <div class="logo">
                                        <img class="logo" src="{{ $logoUrl }}" alt="logo">
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
                                <div class="col-sm-6 mb-2">
                                    <div class="invoice-number">
                                        <h4 class="inv-title-1">Invoice date:</h4>
                                        <p class="invo-addr-1">
                                            {{ $order->order_date }}
                                        </p>
                                    </div>
                                </div>
                                <div class="col-sm-6 text-end mb-2">
                                    <h4 class="inv-title-1">Shop</h4>
                                    <p class="inv-from-1">{{ $shop->phone ?? 'POS' }}</p>
                                    <p class="inv-from-2">{{ $shop->owner_name ?? auth()->user()->name }}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6 mb-2">
                                    <h4 class="inv-title-1">Customer</h4>
                                    <p class="inv-from-1">{{ $order->customer->name }}</p>
                                    <p class="inv-from-1">{{ $order->customer->phone }}</p>
                                </div>
                                <div class="col-sm-6 text-end mb-2">
                                    <h4 class="inv-title-1">Details</h4>
                                    <p class="inv-from-1">{{ $order->customer->email ?? '—' }}</p>
                                    <p class="inv-from-2">{{ $order->customer->address ?? '—' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="order-summary">
                            <div class="table-outer">
                                <table class="default-table invoice-table">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Description</th>
                                        <th>Product Code</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($orderDetails as $item)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->product->product_name }}</td>
                                            <td>{{ $item->product->product_code ?? '—' }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>{{ $item->unitcost }}</td>
                                            <td>{{ $item->total }}</td>
                                        </tr>
                                        @endforeach
                                        <tr>
                                            <td></td>
                                            <td><strong class="text-danger">Sub Total</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td><strong class="text-danger">{{ $orderDetails->sum('total') }}</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row mt-3 order-summary align-items-stretch invoice-summary-boxes">
                            @php
                                    $subtotal = $orderDetails->sum('total');
                                    $laborCharges = (float)($order->vat ?? 0);
                                    $invoiceDiscount = (float)($order->invoice_discount ?? 0);
                                    $invoiceTotal = $subtotal + $laborCharges - $invoiceDiscount;
                                @endphp
                            <div class="col-sm-6">
                                <div class="border rounded p-3 bg-light invoice-summary-box h-100">
                                    <p class="inv-from-1 mb-1"><strong>Previous Balance:</strong> {{ isset($customerBalance) ? number_format($customerBalance, 2) : number_format($order->customer?->credit_amount ?? 0, 2) }}</p>
                                    @if(($salePayments ?? collect())->isNotEmpty())
                                    @foreach($salePayments as $payment)
                                    <p class="inv-from-1 mb-1"><strong>{{ $payment->bank_name }}:</strong> {{ number_format($payment->amount, 2) }}</p>
                                    @endforeach
                                    @elseif(in_array(strtolower($order->payment_status ?? ''), ['bank', 'cheque']) && !empty($paymentBankName ?? null))
                                    <p class="inv-from-1 mb-1"><strong>Bank:</strong> {{ $paymentBankName }}</p>
                                    @endif
                                    <p class="inv-from-1 mb-0"><strong>Total Paid:</strong> {{ number_format($order->pay ?? 0, 2) }}</p>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-end border rounded p-3 bg-light invoice-summary-box h-100">
                                    <p class="inv-from-1 mb-1"><strong>Subtotal:</strong> {{ number_format($subtotal, 2) }}</p>
                                    <p class="inv-from-1 mb-1"><strong>Labor Charges:</strong> +{{ number_format($laborCharges, 2) }}</p>
                                    <p class="inv-from-1 mb-1"><strong>Discount on Invoice:</strong> -{{ number_format($invoiceDiscount, 2) }}</p>
                                    <p class="inv-from-1 mb-0"><strong>Invoice Total:</strong> {{ number_format($invoiceTotal, 2) }}</p>
                                </div>
                            </div>
                        </div>
                        @if($order->comment ?? null)
                        <div class="invoice-notes mt-4">
                            <h4 class="inv-title-1">Invoice Notes</h4>
                            <p class="inv-from-1 mb-0" style="white-space: pre-wrap;">{{ $order->comment }}</p>
                        </div>
                        @endif
                        @if($shop && $shop->invoice_policy)
                        <div class="invoice-policy-footer mt-4">
                            <h4 class="inv-title-1">Invoice Policy</h4>
                            <p class="inv-from-1 mb-0" style="white-space: pre-wrap;">{{ $shop->invoice_policy }}</p>
                        </div>
                        @endif
                        {{-- <div class="invoice-informeshon-footer">
                            <ul>
                                <li><a href="https://themeforest.net/user/themevessel/portfolio">www.themevessel.com</a></li>
                                <li><a href="mailto:sales@hotelempire.com">info@themevessel.com</a></li>
                                <li><a href="tel:+088-01737-133959">+088 01737 133959</a></li>
                            </ul>
                        </div> --}}
                    </div>

                    <div class="invoice-btn-section clearfix d-print-none">
                        <div class="btn-group">
                            <button type="button" class="btn btn-lg btn-print dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Print
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="#" id="printA4">
                                    <i class="fas fa-file-alt"></i> A4 Invoice
                                </a>
                                <a class="dropdown-item" href="#" id="printReceipt">
                                    <i class="fas fa-receipt"></i> Receipt
                                </a>
                            </div>
                        </div>
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
    <script>
        $(function () {
            const orderId = {{ $order->id }};
            const $printDropdownToggle = $('.invoice-btn-section .dropdown-toggle');
            const $printDropdownMenu = $('.invoice-btn-section .dropdown-menu');

            // Fallback dropdown handling if Bootstrap dropdown JS is not loaded.
            $printDropdownToggle.on('click', function (e) {
                e.preventDefault();
                $printDropdownMenu.toggleClass('show');
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.invoice-btn-section .btn-group').length) {
                    $printDropdownMenu.removeClass('show');
                }
            });

            $('#printA4').click(function (e) {
                e.preventDefault();
                $printDropdownMenu.removeClass('show');
                window.open('/orders/invoice/print-a4/' + orderId, '_blank');
            });

            $('#printReceipt').click(function (e) {
                e.preventDefault();
                $printDropdownMenu.removeClass('show');
                window.open('/orders/invoice/print-receipt/' + orderId, '_blank');
            });
        });
    </script>
    
    @if(isset($shouldPrint) && $shouldPrint)
    <script>
        $(document).ready(function() {
            // Open A4 print automatically when invoice is flagged for printing.
            setTimeout(function() {
                window.open('/orders/invoice/print-a4/{{ $order->id }}', '_blank');
            }, 500);
        });
    </script>
    @endif
</body>
</html>
