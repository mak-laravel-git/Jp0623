<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inquiry notification email
    |--------------------------------------------------------------------------
    |
    | Address that receives an email whenever a member sends an inquiry about an
    | "Established locations & routes for sale" listing. There is intentionally
    | no default: when empty the inquiry is still saved, but no email is sent.
    |
    */

    'inquiry_notification_email' => env('ROUTE_INQUIRY_NOTIFICATION_EMAIL'),

];
