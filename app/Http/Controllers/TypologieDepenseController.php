<?php

namespace App\Http\Controllers;

use App\Models\TypologieDepense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TypologieDepenseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $typologies = TypologieDepense::orderBy('type')
            ->paginate($perPage);

        return Inertia::render('typologies/Index', [
            'typologies' => $typologies,
        ]);
    }

    public function create()
    {
        return Inertia::render('typologies/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:50|unique:typologie_depenses,type',
            'libelle' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        TypologieDepense::create($validated);

        return redirect()->route('typologies.index')
            ->with('success', 'Typologie créée avec succès.');
    }

    public function edit(TypologieDepense $typology)
    {
        return Inertia::render('typologies/Edit', [
            'typology' => $typology,
        ]);
    }

    public function update(Request $request, TypologieDepense $typology)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:50|unique:typologie_depenses,type,' . $typology->id,
            'libelle' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $typology->update($validated);

        return redirect()->route('typologies.index')
            ->with('success', 'Typologie mise à jour.');
    }

    public function destroy(TypologieDepense $typology)
    {
        $typology->delete();

        return redirect()->route('typologies.index')
            ->with('success', 'Typologie supprimée.');
    }
}
