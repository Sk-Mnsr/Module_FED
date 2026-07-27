<?php

namespace App\Http\Controllers\Reconciliation;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PartenaireController extends Controller
{
    public function index(): Response
    {
        $partenaires = Partenaire::query()
            ->orderBy('nom')
            ->paginate(10)
            ->through(fn (Partenaire $p) => [
                'id' => $p->id,
                'identifiant' => $p->identifiant,
                'nom' => $p->nom,
                'icone' => $p->icone,
                'icone_url' => $p->icone_url,
            ]);

        return Inertia::render('ReconciliationFlexcube/Partenaires/Index', [
            'partenaires' => $partenaires,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifiant' => ['required', 'string', 'max:100', 'unique:partenaires,identifiant'],
            'nom' => ['required', 'string', 'max:255'],
            'icone' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp,svg', 'max:2048'],
        ], [
            'identifiant.required' => 'Veuillez saisir l’identifiant.',
            'identifiant.unique' => 'Cet identifiant est déjà utilisé.',
            'nom.required' => 'Veuillez saisir le nom.',
            'icone.image' => 'L’icône doit être une image.',
            'icone.max' => 'L’icône ne doit pas dépasser 2 Mo.',
        ]);

        $path = null;
        if ($request->hasFile('icone')) {
            $path = $request->file('icone')->store('reconciliation/partenaires', 'public');
        }

        Partenaire::create([
            'identifiant' => $validated['identifiant'],
            'nom' => $validated['nom'],
            'icone' => $path,
        ]);

        return redirect()
            ->route('reconciliation-flexcube.partenaires.index')
            ->with('success', 'Partenaire créé avec succès.');
    }

    public function update(Request $request, Partenaire $partenaire): RedirectResponse
    {
        $validated = $request->validate([
            'identifiant' => ['required', 'string', 'max:100', 'unique:partenaires,identifiant,'.$partenaire->id],
            'nom' => ['required', 'string', 'max:255'],
            'icone' => ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp,svg', 'max:2048'],
        ], [
            'identifiant.required' => 'Veuillez saisir l’identifiant.',
            'identifiant.unique' => 'Cet identifiant est déjà utilisé.',
            'nom.required' => 'Veuillez saisir le nom.',
            'icone.image' => 'L’icône doit être une image.',
            'icone.max' => 'L’icône ne doit pas dépasser 2 Mo.',
        ]);

        $data = [
            'identifiant' => $validated['identifiant'],
            'nom' => $validated['nom'],
        ];

        if ($request->hasFile('icone')) {
            if ($partenaire->icone) {
                Storage::disk('public')->delete($partenaire->icone);
            }
            $data['icone'] = $request->file('icone')->store('reconciliation/partenaires', 'public');
        }

        $partenaire->update($data);

        return redirect()
            ->route('reconciliation-flexcube.partenaires.index')
            ->with('success', 'Partenaire mis à jour avec succès.');
    }

    public function destroy(Partenaire $partenaire): RedirectResponse
    {
        if ($partenaire->icone) {
            Storage::disk('public')->delete($partenaire->icone);
        }

        $partenaire->delete();

        return redirect()
            ->route('reconciliation-flexcube.partenaires.index')
            ->with('success', 'Partenaire supprimé avec succès.');
    }
}
