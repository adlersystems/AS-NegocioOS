<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('placeholder', [
            'module' => __('app.menu.reports'),
            'description' => __('app.under_construction_desc'),
        ]);
    }
}
