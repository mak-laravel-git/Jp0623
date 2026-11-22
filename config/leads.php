<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead Price
    |--------------------------------------------------------------------------
    |
    | Flat price (in whole dollars) charged for every location lead. Payments
    | are not wired up yet, so this is only recorded on the purchase.
    |
    */

    'price' => (int) env('LEAD_PRICE', 150),

    /*
    |--------------------------------------------------------------------------
    | Lead return requests
    |--------------------------------------------------------------------------
    |
    | The time limit for sending a lead back is set by the admin in Admin > Lead issues.
    |
    | issue_notification_email: address that receives an email for every new
    | return request. No default: when empty the request is saved without an email.
    |
    */

    'issue_notification_email' => env('LEAD_ISSUE_NOTIFICATION_EMAIL'),

];
