<?php

// Letterhead details printed on the delivery challan (and any future
// company-branded document) header/footer. Centralized here instead of
// hardcoded in the Blade so a franchise/branch change is a config edit, not
// a template edit.
return [
    'name' => env('COMPANY_NAME', 'Arcadia Biotech'),
    'tagline' => env('COMPANY_TAGLINE', 'Tissue Culture Banana Plants'),
    'address' => env('COMPANY_ADDRESS', 'Anand, Gujarat, India'),
    'gst_number' => env('COMPANY_GST_NUMBER'),
    'mobile' => env('COMPANY_MOBILE', '9999999999'),
    'email' => env('COMPANY_EMAIL', 'info@arcadiabiotech.com'),
    'website' => env('COMPANY_WEBSITE', 'www.arcadiabiotech.com'),
    'logo' => env('COMPANY_LOGO_PATH', 'images/company-logo.png'),
    'jurisdiction' => env('COMPANY_JURISDICTION', 'Anand'),
];
