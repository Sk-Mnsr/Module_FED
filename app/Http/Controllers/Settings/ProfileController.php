<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('roles');

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'savedSignature' => $user->signature,
            'roles' => $user->roles
                ->map(fn ($role) => [
                    'slug' => $role->slug,
                    'label' => $role->nom ?: ucfirst(str_replace('_', ' ', (string) $role->slug)),
                ])
                ->values(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Update the user's signature.
     */
    public function updateSignature(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signature' => 'required|string',
        ]);

        $signature = $validated['signature'];
        if (! str_starts_with($signature, 'data:image/')) {
            return back()->withErrors(['signature' => 'Format de signature invalide.']);
        }

        $request->user()->update(['signature' => $signature]);

        return back()->with('status', 'signature-saved');
    }
}
