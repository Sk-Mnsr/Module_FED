<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $zone = Zone::create([
            'nom' => $validated['nom'],
            'code' => $this->normalizeCode($validated['code'] ?? null),
        ]);

        $zone->responsables()->sync($validated['responsable_user_ids'] ?? []);

        return redirect()->back()->with('success', 'Zone créée avec succès.');
    }

    public function update(Request $request, Zone $zone)
    {
        $validated = $request->validate($this->rules($zone));

        $zone->update([
            'nom' => $validated['nom'],
            'code' => $this->normalizeCode($validated['code'] ?? null),
        ]);

        $zone->responsables()->sync($validated['responsable_user_ids'] ?? []);

        return redirect()->back()->with('success', 'Zone mise à jour avec succès.');
    }

    public function destroy(Zone $zone)
    {
        $zone->delete();

        return redirect()->back()->with('success', 'Zone supprimée avec succès.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Zone $zone = null): array
    {
        return [
            'nom' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('zones', 'code')->ignore($zone?->id),
            ],
            'responsable_user_ids' => 'nullable|array',
            'responsable_user_ids.*' => 'integer|exists:users,id',
        ];
    }

    private function normalizeCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return trim($code);
    }
}
