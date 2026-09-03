<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.edit', [
            'settings' => Setting::getAll(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $this->deleteOldLogo();
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        } else {
            unset($data['logo']);
        }

        Setting::setMany($data);

        return redirect()
            ->route('settings.index')
            ->with('success', __('app.flash.settings_updated'));
    }

    private function deleteOldLogo(): void
    {
        $path = Setting::logoPath();

        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
