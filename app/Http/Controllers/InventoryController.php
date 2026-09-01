<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        return view('placeholder', [
            'module' => __('app.menu.inventory'),
            'description' => __('app.under_construction_desc'),
        ]);
    }
}
