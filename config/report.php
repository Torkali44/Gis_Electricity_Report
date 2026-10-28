<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report branding (PDF / Word)
    |--------------------------------------------------------------------------
    */

    'company_name' => env('REPORT_COMPANY_NAME', 'GIS VALUATION AND EVALUATION'),

    'logo_path' => env('REPORT_LOGO_PATH', 'images/newLog.jpeg'),

    'signature_path' => env('REPORT_SIGNATURE_PATH', 'images/GIS SIGN.png'),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'max_section_images' => (int) env('REPORT_MAX_SECTION_IMAGES', 40),

];
