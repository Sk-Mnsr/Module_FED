<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppSettingController extends Controller
{
    public function index()
    {
        $settings = AppSetting::all()->keyBy('key');

        return Inertia::render('settings/AppSettings', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'fed_dga_threshold' => 'required|numeric|min:0',
        ], [
            'fed_dga_threshold.required' => 'Le seuil DGA est requis.',
            'fed_dga_threshold.numeric'  => 'Le seuil doit être un nombre.',
            'fed_dga_threshold.min'      => 'Le seuil doit être positif ou nul.',
        ]);

        AppSetting::set('fed_dga_threshold', (string) $data['fed_dga_threshold']);

        return back()->with('success', 'Paramètres sauvegardés avec succès.');
    }
}
