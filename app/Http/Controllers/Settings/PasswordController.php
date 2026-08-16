<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page,
     * or the dedicated first-login page when a change is required.
     */
    public function edit(Request $request): Response
    {
        if ($request->user()?->password_change_required) {
            return Inertia::render('auth/ForcePasswordChange');
        }

        return Inertia::render('settings/Password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $wasRequired = (bool) $user->password_change_required;

        $user->update([
            'password' => $validated['password'],
            'password_change_required' => false,
        ]);

        if ($wasRequired) {
            return redirect()
                ->route('portal')
                ->with('success', 'Mot de passe mis à jour. Vous pouvez continuer.');
        }

        return back()->with('success', 'Mot de passe mis à jour.');
    }
}
