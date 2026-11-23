<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receipt seller details
    |--------------------------------------------------------------------------
    |
    | Printed at the top of every receipt page. Anything left empty is simply
    | not shown; the business name falls back to the application name.
    |
    */

    'business_name' => env('RECEIPT_BUSINESS_NAME') ?: env('APP_NAME', 'Laravel'),

    'address' => env('RECEIPT_BUSINESS_ADDRESS'),

    'email' => env('RECEIPT_SUPPORT_EMAIL'),

    'phone' => env('RECEIPT_PHONE'),

];
