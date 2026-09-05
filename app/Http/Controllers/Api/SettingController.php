<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::getAll();

        return response()->json([
            'data' => array_filter($settings, fn ($value) => ! is_null($value)),
            'currency' => [
                'code' => $settings['currency'],
                'symbol' => Setting::currencySymbol($settings['currency']),
                'iva_percentage' => (int) $settings['iva_percentage'],
            ],
        ]);
    }
}
