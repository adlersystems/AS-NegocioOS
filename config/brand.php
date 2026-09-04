<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product identity (the app / maker's brand)
    |--------------------------------------------------------------------------
    | This is the brand of the software PRODUCT itself (the maker), shown in
    | the bottom of the sidebar and on auth pages. It is intentionally NOT
    | editable from the UI/generals settings — it is the maker's own brand.
    |
    | This is distinct from the customer's business identity, which IS editable
    | by the user under "Settings" (company name, logo, slogan/tagline) and is
    | rendered at the top of the sidebar / app header.
    |
    | To change the product brand, edit this file directly (or drop a new logo
    | in public/images/ and point 'logo' to it).
    */

    // Hardcoded product name (the app's own brand name).
    'product' => [
        'name' => 'AS-NegocioOS',

        // Static logo for the product brand. Put the file under public/images/
        // and reference it here. When the file does not exist, the UI falls
        // back to the default icon mark.
        'logo' => '/images/logo/logo-icon.svg',

        // Translation key holding the product slogan / tagline.
        'slogan_key' => 'app.app_tagline',
    ],
];
