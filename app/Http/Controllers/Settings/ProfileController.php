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
            'signature' => 'required|string|max:2000000',
        ], [
            'signature.required' => 'Veuillez dessiner ou importer une signature.',
            'signature.max' => 'La signature est trop volumineuse. Utilisez une image plus légère.',
        ]);

        $signature = $this->normalizeSignatureDataUri($validated['signature']);
        if ($signature === null) {
            return back()->withErrors([
                'signature' => 'Format de signature invalide. Utilisez une image PNG, JPEG ou WebP.',
            ]);
        }

        $user = $request->user();
        $user->signature = $signature;
        $user->save();

        return back()->with('status', 'signature-saved');
    }

    /**
     * Accepte un data URI image et normalise en PNG (compatible DomPDF / pièce comptable).
     * Recadre les marges blanches pour éviter le « cadre » gris sur la pièce.
     */
    private function normalizeSignatureDataUri(string $signature): ?string
    {
        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/i', $signature, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            // Fallback sans GD : PNG/JPEG bruts uniquement.
            $type = strtolower($matches[1]);
            if (! in_array($type, ['png', 'jpeg', 'jpg'], true)) {
                return null;
            }
            $mime = $type === 'png' ? 'png' : 'jpeg';

            return 'data:image/'.$mime.';base64,'.base64_encode($binary);
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return null;
        }

        $cropped = $this->trimSignatureWhitespace($source);
        if ($cropped !== $source) {
            imagedestroy($source);
            $source = $cropped;
        }

        imagesavealpha($source, true);
        imagealphablending($source, false);

        ob_start();
        $ok = imagepng($source, null, 6);
        imagedestroy($source);
        $png = ob_get_clean();

        if (! $ok || $png === false || $png === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function trimSignatureWhitespace($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 2 || $height < 2) {
            return $image;
        }

        $threshold = 245;
        $minX = $width;
        $minY = $height;
        $maxX = 0;
        $maxY = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                // Ignore quasi-transparent / quasi-blanc.
                if ($a > 110) {
                    continue;
                }
                if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
                    continue;
                }

                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX <= $minX || $maxY <= $minY) {
            return $image;
        }

        $pad = 4;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($width - 1, $maxX + $pad);
        $maxY = min($height - 1, $maxY + $pad);

        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;
        $cropped = imagecreatetruecolor($cropW, $cropH);
        if ($cropped === false) {
            return $image;
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
        imagefilledrectangle($cropped, 0, 0, $cropW, $cropH, $transparent);
        imagecopy($cropped, $image, 0, 0, $minX, $minY, $cropW, $cropH);

        return $cropped;
    }
}
