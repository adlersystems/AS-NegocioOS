<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sidebar navigation links
    |--------------------------------------------------------------------------
    |
    | Each entry maps to a named route rendered in the app sidebar. The "label"
    | is a language key resolved at render time via the __() helper so it
    | follows the active locale, and "icon" matches an alias in the icon
    | component. Order here defines the sidebar order.
    |
    */

    'links' => [
        ['name' => 'dashboard', 'label' => 'app.menu.dashboard', 'icon' => 'dashboard'],
        ['name' => 'clients.index', 'label' => 'app.menu.clients', 'icon' => 'clients'],
        ['name' => 'products.index', 'label' => 'app.menu.products', 'icon' => 'products'],
        ['name' => 'sales.index', 'label' => 'app.menu.sales', 'icon' => 'sales'],
        ['name' => 'inventory.index', 'label' => 'app.menu.inventory', 'icon' => 'inventory'],
        ['name' => 'reports.index', 'label' => 'app.menu.reports', 'icon' => 'reports'],
        ['name' => 'audit.index', 'label' => 'app.menu.audit', 'icon' => 'audit'],
        ['name' => 'settings.index', 'label' => 'app.menu.settings', 'icon' => 'settings'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role visibility
    |--------------------------------------------------------------------------
    |
    | Maps a route name to the list of roles allowed to see it. Routes not
    | listed here are visible to every authenticated role.
    |
    */

    'roles' => [
        'sales.index' => ['admin', 'vendedor', 'encargado'],
        'inventory.index' => ['admin', 'encargado'],
        'reports.index' => ['admin', 'encargado'],
        'audit.index' => ['admin'],
        'settings.index' => ['admin'],
    ],

];
