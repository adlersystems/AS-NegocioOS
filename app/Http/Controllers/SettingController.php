<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('placeholder', [
            'module' => __('app.menu.settings'),
            'description' => __('app.under_construction_desc'),
        ]);
    }
}
