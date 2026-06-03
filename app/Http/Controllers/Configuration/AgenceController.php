<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\Agence;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AgenceController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->query('sort', 'code');
        if (! in_array($sort, ['code', 'nom'], true)) {
            $sort = 'code';
        }

        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $agences = Agence::query()
            ->with(['chefAgence:id,name,email', 'zone:id,nom,code'])
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $chefCandidates = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'ca'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'agence_id']);

        $zones = Zone::query()
            ->with(['responsables:id,name,email'])
            ->withCount('agences')
            ->orderBy('nom')
            ->get();

        $responsableCandidates = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Configuration/Agences/Index', [
            'agences' => $agences,
            'sort' => $sort,
            'direction' => $direction,
            'chefCandidates' => $chefCandidates,
            'zones' => $zones,
            'responsableCandidates' => $responsableCandidates,
            'siegeAgenceCode' => Agence::CODE_SIEGE,
        ]);
    }

    public function store(Request $request)
    {
        $this->mergeEmptyChefId($request);
        $this->mergeEmptyZoneId($request);

        $validated = $request->validate($this->designationRules($request));

        $validated = $this->normalizeChefPourCode($validated);

        $agence = Agence::create($validated);

        $this->syncChefPourAgence($agence, $validated['chef_agence_user_id'] ?? null);

        return redirect()->back()->with('success', 'Agence créée avec succès.');
    }

    public function update(Request $request, Agence $agence)
    {
        $this->mergeEmptyChefId($request);
        $this->mergeEmptyZoneId($request);

        $previousChefId = $agence->chef_agence_user_id;

        $validated = $request->validate($this->designationRules($request, $agence));

        $validated = $this->normalizeChefPourCode($validated);

        $agence->update($validated);

        $agence->refresh();

        $this->detachFormerChefIfNeeded($agence, $previousChefId, $validated['chef_agence_user_id'] ?? null);
        $this->syncChefPourAgence($agence, $validated['chef_agence_user_id'] ?? null);

        return redirect()->back()->with('success', 'Agence mise à jour avec succès.');
    }

    public function destroy(Agence $agence)
    {
        $agence->delete();

        return redirect()->back()->with('success', 'Agence supprimée avec succès.');
    }

    /**
     * @return array<string, mixed>
     */
    private function designationRules(Request $request, ?Agence $agence = null): array
    {
        $uniqueChef = Rule::unique('agences', 'chef_agence_user_id');
        if ($agence !== null) {
            $uniqueChef = $uniqueChef->ignore($agence->id);
        }

        return [
            'nom' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:agences,code'.($agence ? ','.$agence->id : ''),
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'chef_agence_user_id' => [
                'nullable',
                'integer',
                Rule::prohibitedIf((string) $request->input('code', $agence?->code ?? '') === Agence::CODE_SIEGE),
                'exists:users,id',
                $uniqueChef,
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $agence): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if ((string) $request->input('code', $agence?->code ?? '') === Agence::CODE_SIEGE) {
                        return;
                    }
                    if (! User::whereKey($value)->whereHas('roles', fn ($q) => $q->where('slug', 'ca'))->exists()) {
                        $fail('Le chef d’agence doit être un utilisateur avec le rôle Chef d’agence CA.');
                    }
                },
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeChefPourCode(array $validated): array
    {
        if (($validated['code'] ?? '') === Agence::CODE_SIEGE) {
            $validated['chef_agence_user_id'] = null;
        }

        return $validated;
    }

    private function mergeEmptyChefId(Request $request): void
    {
        if ($request->input('chef_agence_user_id') === '' || $request->input('chef_agence_user_id') === null) {
            $request->merge(['chef_agence_user_id' => null]);
        }
    }

    private function mergeEmptyZoneId(Request $request): void
    {
        $z = $request->input('zone_id');
        if ($z === '' || $z === null) {
            $request->merge(['zone_id' => null]);
        }
    }

    private function detachFormerChefIfNeeded(Agence $agence, ?int $previousChefId, ?int $newChefId): void
    {
        if ($previousChefId === null || $previousChefId === $newChefId) {
            return;
        }

        $prev = User::find($previousChefId);
        if ($prev && (int) $prev->agence_id === (int) $agence->id) {
            $prev->agence_id = null;
            $prev->save();
        }
    }

    private function syncChefPourAgence(Agence $agence, ?int $chefUserId): void
    {
        if ($agence->isSiege()) {
            return;
        }

        if ($chefUserId === null) {
            return;
        }

        $user = User::find($chefUserId);
        if (! $user) {
            return;
        }

        $roleId = Role::where('slug', 'ca')->value('id');

        $user->agence_id = $agence->id;
        if ($roleId) {
            $user->roles()->sync([$roleId]);
            $user->profile = 'monetique';
        }
        $user->save();
    }
}
