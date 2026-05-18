<?php

return [
    /*
    | When true, invoice line unit price must be >= the product's buying_price (cost); below cost is blocked.
    | Applies to invoice create/edit (OrderController) and client-side checks on create-invoice view.
    */
    'enforce_unit_price_above_buying' => env('ENFORCE_INVOICE_UNIT_ABOVE_BUYING', false),
];
