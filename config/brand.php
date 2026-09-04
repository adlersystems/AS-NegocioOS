<?php

return [
    /*
    |--------------------------------------------------------------------------
    | App identity (brand)
    |--------------------------------------------------------------------------
    | The product identity shown in the sidebar (top and bottom) and on the
    | auth pages: a logo image, the company name and a slogan.
    |
    | The logo and company name are stored as editable Settings (used when a
    | value exists); the fallbacks below kick in when no setting row is found.
    */

    // Company name fallback when no Setting row exists.
    'name_default' => 'AS-NegocioOS',

    // Translation key holding the slogan/tagline rendered under the name.
    'slogan_key' => 'app.app_tagline',

    // Fallback slogan text if the translation key is missing.
    'slogan_fallback' => 'Business management system',
];
